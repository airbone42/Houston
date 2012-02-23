<?php
class Houston {
	private $oProcessmanager;
	private $oCallableSet;
	
	public function __construct(
		Houston_Processmanager_Interface $oProcessmanager = NULL,
		Houston_CallableSet $oCallableSet = NULL
	) {
		if (is_null($oProcessmanager)) {
			$oProcessmanager = new Houston_Processmanager();
		}
		$this->oProcessmanager = $oProcessmanager;
		if (is_null($oCallableSet)) {
			$oCallableSet = new Houston_CallableSet();
		}
		$this->oCallableSet = $oCallableSet;
	}
	
	public function addCallable($sIdentifier, $cCallable, $aEvents = array()) {
		$oFactoryCallable = new Houston_Callable_Factory($sIdentifier, $cCallable, $aEvents);
		$oCallable = $oFactoryCallable->build();
		$this->oCallableSet->add($oCallable);
	}
	
	public function launch($sIdentifier = NULL) {
		global $argv;
		if (isset($argv[1])) {
			$sIdentifier = $argv[1];
			$this->oCallableSet->call($sIdentifier);
		} elseif (!is_null($sIdentifier)) {
			$this->runSubprocess($sIdentifier);
		}
		$this->oProcessmanager->handleSubprocesses();
	}
	
	public function runSubprocess($sIdentifier) {
		//FIXME law of demeter
		$this->oProcessmanager->runSubprocess(
			$this->oCallableSet->get($sIdentifier)->oCallable,
			$this->oCallableSet->get($sIdentifier)->oOutputhandler
		);
	}
		
	public function triggerEvent($sEventName, $aParams = array()) {
		echo Houston_Eventhandler::getEventName($sEventName) .
			json_encode(serialize($aParams)) . "\n";
	}	
}
class Houston_Callable_Factory {
	private $cCallable;
	private $aEvents;
	private $sIdentifier;
	
	public function __construct($sIdentifier = NULL, $cCallable = NULL, $aEvents = NULL) {
		if (isset($sIdentifier)) {
			$this->setIdentifier($sIdentifier);
			if (isset($cCallable)) {
				$this->setCallable($cCallable);
				if (isset($aEvents)) {
					$this->setEvents($aEvents);
				}
			}
		}
	}
	
	public function setCallable($cCallable) {
		if (!is_callable($cCallable)) {
			throw new Exception('not callable');
		}
		$this->cCallable = $cCallable;
	}
	
	public function setIdentifier($sIdentifier) {
		$this->sIdentifier = $sIdentifier;
	}
	
	public function setEvents($aEvents) {
		if (!is_array($aEvents)) {
			throw new Exception('not an array');
		}
		$this->aEvents = $aEvents;
	}
	
	public function build() {
		$oCallable = new Houston_Callable($this->sIdentifier, $this->cCallable);
		$oEvents = new Houston_Events();
		foreach ($this->aEvents as $sIdentifier => $cEvent) {
			$oEvents->add(
				new Houston_Callable($sIdentifier, $cEvent)
			);
		}
		return new Houston_Callable_WithOutputhandler_Struct(
			$oCallable,
			new Houston_Eventhandler($oEvents)
		); 
	}
}

interface Houston_Callable_Identifier_Interface {
	public function getIdentifier();
}

interface Houston_Callable_Interface extends Houston_Callable_Identifier_Interface {
	public function call();
}

class Houston_Callable implements Houston_Callable_Interface {
	private $cCallable;
	private $oIdentifier;
		
	public function __construct($sIdentifier, $cCallable) {
		$this->oIdentifier = new Houston_Callable_Identifier($sIdentifier);
		if (!is_callable($cCallable)) {
			throw new Exception('callable expected');
		}
		$this->cCallable = $cCallable;
	}
	
	public function getIdentifier() {
		return $this->oIdentifier->getIdentifier();
	}

	public function call($aParams = NULL) {
		call_user_func_array(
			$this->cCallable, 
			(
				is_array($aParams) ?
				$aParams :
				array()
			)
		);
	}
}

class Houston_Callable_WithOutputhandler_Struct implements Houston_Callable_Interface {
	public $oCallable;
	public $oOutputhandler;
	
	public function __construct(Houston_Callable $oCallable, Houston_Outputhandler $oOutputhandler) {
		$this->oCallable = $oCallable;
		$this->oOutputhandler = $oOutputhandler;
	}
	
	public function call() {
		$this->oCallable->call();
	}
	
	public function getIdentifier() {
		return $this->oCallable->getIdentifier();
	}
}

class Houston_CallableSet {
	protected $aCallables = array();
	
	public function add(Houston_Callable_Interface $oCallable) {
		if (isset($this->aCallables[$oCallable->getIdentifier()])) {
			throw new Exception('identifier is not unique');
		}
		$this->aCallables[$oCallable->getIdentifier()] = $oCallable;
	}
	
	public function call($sIdentifier, $aParams = NULL) {
		$this->get($sIdentifier)->call($aParams);
	}
	
	public function get($sIdentifier) {
		if (!isset($this->aCallables[$sIdentifier])) {
			throw new Exception('unknown identifier');
		}
		return $this->aCallables[$sIdentifier];
	}
}

interface Houston_Outputhandler {
	public function handleOutput($sOutput);
}

class Houston_Eventhandler implements Houston_Outputhandler {
	const EVENT_SEPARATOR = '###';
	private $oEvents;
	private $aEventParams;
	
	public function __construct(Houston_Events $oEvents = NULL) {
		$this->oEvents = $oEvents;
	}
	
	public function handleOutput($sOutput) {
		if (!is_null($this->oEvents)) {
			foreach ($this->oEvents->getEventNames() as $sEventName) {
				$sEventNameMasked = self::getEventName($sEventName);
				$iPosEvent = strpos($sOutput, $sEventNameMasked);
				if (is_integer($iPosEvent)) {
					$sParams = substr($sOutput, $iPosEvent + strlen($sEventNameMasked));
					$aParams = unserialize(json_decode($sParams));
					$sOutput = substr($sOutput, 0, $iPosEvent);
					ob_start();
					$this->oEvents->call($sEventName, $aParams);
					$sOutput .= ob_get_contents();
					ob_end_clean();
				}
			}
		}
		return $sOutput;
	}
	
	public static function getEventName($sEventName) {
		return self::EVENT_SEPARATOR . $sEventName . self::EVENT_SEPARATOR;
	}
}

class Houston_Events extends Houston_CallableSet {
	public function getEventNames() {
		return array_keys($this->aCallables);
	}
}

class Houston_Process implements Houston_Processhandler_Interface {
	private $oCallable;
	private $rProcess;
	private $aPipes;
	private $sDefinitionFile;
	
	public function __construct(Houston_Callable_Identifier_Interface $oCallable) {
		$this->oCallable = $oCallable;
		$this->sDefinitionFile = $_SERVER['SCRIPT_FILENAME'];
	}

	public function runSubprocess() {
		$aDescriptorspec = array(
		   0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		   2 => array("pipe", "w"), // stderr is a file to write to
		);
		$this->rProcess = proc_open(
			'php ' . escapeshellarg($this->sDefinitionFile) . ' ' . $this->oCallable->getIdentifier(), 
			$aDescriptorspec, 
			$this->aPipes
 		);
	}
	
	public function isRunning() {
		if ($this->rProcess) {
			$aStatus = proc_get_status($this->rProcess);
			return $aStatus['running'];
		}
		return false;
	}
	
	public function getOutput() {
		return fgets($this->aPipes[1]);
	}
	
	public function setDefinitionFile($sDefinitionFile) {
		$this->sDefinitionFile = $sDefinitionFile;
	}
}

class Houston_Processhandler implements Houston_Processhandler_Interface {
	public $oProcess;
	public $oOutputhandler;
	
	public function __construct(Houston_Process $oProcess, Houston_Outputhandler $oOutputhandler) {
		$this->oProcess = $oProcess;
		$this->oOutputhandler = $oOutputhandler;
	}
	
	public function isRunning() {
		return $this->oProcess->isRunning();
	}
	
	public function getOutput() {
		$sOutput = $this->oProcess->getOutput();
		$sOutput = $this->oOutputhandler->handleOutput($sOutput);
		return $sOutput;
	}
}

interface Houston_Processhandler_Interface {
	public function isRunning();
	public function getOutput();
}

class Houston_Processmanager implements Houston_Processmanager_Interface {
	private $aPipes = array();
	private $aSubprocessesRunning = array();
	private $aOutputhandlers = array();
	private $sDefinitionFile = NULL;
	
	public function runSubprocess(Houston_Callable_Identifier_Interface $oCallable, Houston_Outputhandler $oOutputhandler = NULL) {
		$oProcess = new Houston_Process($oCallable);
		if (!is_null($this->sDefinitionFile)) {
			$oProcess->setDefinitionFile($this->sDefinitionFile);
		}
		$oProcess->runSubprocess();
		$oProcesshandler = new Houston_Processhandler(
			$oProcess, 
			$oOutputhandler
		);
		$this->aSubprocessesRunning[] = $oProcesshandler;
	}
	
	public function handleSubprocesses() {
		while (count($this->aSubprocessesRunning)) {
			foreach ($this->aSubprocessesRunning as $iKeyProcess => $oProcess) {
				if (!$oProcess->isRunning()) {
					unset($this->aSubprocessesRunning[$iKeyProcess]);
				}
				$sOutput = $oProcess->getOutput();
				echo $sOutput;
			}
			usleep(100);
		}
	}
	
	public function setDefinitionFile($sDefinitionFile) {
		$this->sDefinitionFile = $sDefinitionFile;
	}
}

interface Houston_Processmanager_Interface {
	public function handleSubprocesses();
	public function runSubprocess(Houston_Callable_Identifier_Interface $oCallable, Houston_Outputhandler $oOutputhandler);
}

class Houston_Callable_Identifier implements Houston_Callable_Identifier_Interface {
	private $sIdentifier;
	
	public function __construct($sIdentifier) {
		$this->sIdentifier = $sIdentifier;
	}
	
	public function getIdentifier() {
		return $this->sIdentifier;
	}
}

class Houston_Launcher {
	private $sDefinitionFile;
	
	public function __construct($sDefinitionFile) {
		$this->sDefinitionFile = $sDefinitionFile;
	}
	
	public function launch($sIdentifier) {
		$oProcessmanager = new Houston_Processmanager();
		$oProcessmanager->setDefinitionFile($this->sDefinitionFile);
		$oProcessmanager->runSubprocess(
			new Houston_Callable_Identifier($sIdentifier)
		);
		$oProcessmanager->handleSubprocesses();
		
	}
}