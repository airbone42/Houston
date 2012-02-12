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
	
	public function launch($sIdentifier) {
		global $argv;
		if (isset($argv[1])) {
			$sIdentifier = $argv[1];
			$this->oCallableSet->call($sIdentifier);
		} else {
			$this->runSubprocess($sIdentifier);
		}
		$this->oProcessmanager->handleSubprocesses();
	}
	
	public function runSubprocess($sIdentifier) {
		$this->oProcessmanager->runSubprocess(
			$this->oCallableSet->get($sIdentifier)
		);
	}
		
	public function triggerEvent($sEventName) {
		echo Houston_Eventhandler::getEventName($sEventName);
	}	
}
class Houston_Callable_Factory {
	private $cCallable;
	private $aEvents = array();
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
		return new Houston_Callable_WithEvents_Struct(
			$oCallable,
			$oEvents
		); 
	}
}
class Houston_Callable implements Houston_Callable_Interface {
	private $cCallable;
	private $sIdentifier;
		
	public function __construct($sIdentifier, $cCallable) {
		$this->sIdentifier = $sIdentifier;
		if (!is_callable($cCallable)) {
			throw new Exception('callable expected');
		}
		$this->cCallable = $cCallable;
	}
	
	public function getIdentifier() {
		return $this->sIdentifier;
	}
	
	public function setIdentifier($sIdentifier) {
		$this->sIdentifier = $sIdentifier;
	}
	
	public function call() {
		$cCallable = $this->cCallable;
		$cCallable();
	}
}

interface Houston_Callable_Interface {
	public function call();
	public function getIdentifier();
}

class Houston_Callable_WithEvents_Struct implements Houston_Callable_Interface {
	public $oCallable;
	public $oEvents;
	
	public function __construct(Houston_Callable $oCallable, Houston_Events $oEvents) {
		$this->oCallable = $oCallable;
		$this->oEvents = $oEvents;
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
	
	public function call($sIdentifier) {
		$this->get($sIdentifier)->call();
	}
	
	public function get($sIdentifier) {
		if (!isset($this->aCallables[$sIdentifier])) {
			throw new Exception('unknown identifier');
		}
		return $this->aCallables[$sIdentifier];
	}
}

class Houston_Eventhandler {
	const EVENT_SEPARATOR = '###';
	private $oEvents;
	
	public function __construct(Houston_Events $oEvents) {
		$this->oEvents = $oEvents;
	}
	
	public function handleOutput($sOutput) {
		foreach ($this->oEvents->getEventNames() as $sEventName) {
			if (is_integer(strpos($sOutput, self::getEventName($sEventName)))) {
				$sOutput = str_replace(self::getEventName($sEventName), '', $sOutput);
				$this->oEvents->call($sEventName);
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
	
	public function __construct(Houston_Callable $oCallable) {
		$this->oCallable = $oCallable;
	}

	public function runSubprocess() {
		$aDescriptorspec = array(
		   0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		   2 => array("pipe", "w"), // stderr is a file to write to
		);
		$this->rProcess = proc_open(
			'php ' . escapeshellarg($_SERVER['SCRIPT_FILENAME']) . ' ' . $this->oCallable->getIdentifier(), 
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
		return stream_get_contents($this->aPipes[1]);
	}
}

class Houston_Processhandler implements Houston_Processhandler_Interface {
	public $oProcess;
	public $oEventhandler;
	
	public function __construct(Houston_Process $oProcess, Houston_Eventhandler $oEventhandler) {
		$this->oProcess = $oProcess;
		$this->oEventhandler = $oEventhandler;
	}
	
	public function isRunning() {
		return $this->oProcess->isRunning();
	}
	
	public function getOutput() {
		$sOutput = $this->oProcess->getOutput();
		$sOutput = $this->oEventhandler->handleOutput($sOutput);
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
	private $aEventhandlers = array();
	
	public function runSubprocess(Houston_Callable_WithEvents_Struct $oCallableStruct) {
		$oProcess = new Houston_Process($oCallableStruct->oCallable);
		$oProcess->runSubprocess();
		$oEventhandler = new Houston_Eventhandler($oCallableStruct->oEvents);
		$oProcesshandler = new Houston_Processhandler(
			$oProcess, 
			$oEventhandler
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
}

interface Houston_Processmanager_Interface {
	public function handleSubprocesses();
	public function runSubprocess(Houston_Callable_WithEvents_Struct $oCallableStruct);
}
