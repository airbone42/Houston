<?php
class Houston implements Houston_EventTrigger {
	private $oProcessmanager;
	private $oCallableSet;
	private $sIdentifierCalled;
	private $aParams;
	
	public function __construct(
		Houston_Processmanager_Interface $oProcessmanager = NULL,
		Houston_CallableSet $oCallableSet = NULL
	) {
		$this->initProcessmanager($oProcessmanager);
		$this->initCallableSet($oCallableSet);
		$this->initIdentifierCalled();
		$this->initParams();
	}
	
	private function initProcessmanager(Houston_Processmanager_Interface $oProcessmanager = NULL) {
		if (is_null($oProcessmanager)) {
			$oProcessmanager = new Houston_Processmanager();
		}
		$this->oProcessmanager = $oProcessmanager;
	}
	
	private function initCallableSet(Houston_CallableSet $oCallableSet = NULL) {
		if (is_null($oCallableSet)) {
			$oCallableSet = new Houston_CallableSet();
		}
		$this->oCallableSet = $oCallableSet;
	}
	
	private function initIdentifierCalled() {
		global $argv;
		if (isset($argv[1])) {
			$this->sIdentifierCalled = $argv[1];
		}
	}
	
	private function initParams() {
		if ($this->sIdentifierCalled) {
			$sStdin = fgets(STDIN);
			$this->aParams = unserialize(json_decode($sStdin));
		}
	}
	
	public function addCallable($sIdentifier, $cCallable, $aEvents = array()) {
		$oFactoryCallable = new Houston_Callable_Factory($sIdentifier, $cCallable, $aEvents);
		$oCallable = $oFactoryCallable->build();
		$this->oCallableSet->add($oCallable);
	}
	
	public function launch($sIdentifier = NULL) {
		$this->setIdentifier(
			$this->sIdentifierCalled ?
			$this->sIdentifierCalled :
			$sIdentifier
		);
		$this->launchIdentified();
	}
	
	private function exceptionHandler(Exception $oException) {
		$this->oCallableSet->triggerEvent(
			'exception',
			array(
				$oException->getMessage(),
				$oException->getCode(),
				$oException->getPrevious()
			)
		);
	}
	
	public function runSubprocess($sIdentifier, $aParams = NULL) {
		$this->setIdentifier($sIdentifier);
		$this->runIdentifiedSubprocess($aParams);
	}
	
	private function setIdentifier($sIdentifier) {
		$this->oCallableSet->setIdentifier(
			$sIdentifier
		);
	}
	
	private function launchIdentified() {
		if ($this->sIdentifierCalled) {
			$this->callIdentified();
		} else {
			$this->runIdentifiedSubprocess();
		}
	}
	
	private function callIdentified() {
		try {
			$this->oCallableSet->callIdentified($this->aParams);
		} catch (Exception $oException) {
			$this->exceptionHandler($oException);
		}
	}
	
	private function runIdentifiedSubprocess($aParams = NULL) {
		$this->oProcessmanager->runSubprocess(
			$this->oCallableSet->getIdentifiedCallable(),
			$this->oCallableSet->getIdentifiedOutputhandler(),
			$aParams
		);
		$this->oProcessmanager->handleSubprocesses();
	}
		
	public function triggerEvent($sEventName, $aParams = NULL) {
		$this->oCallableSet->triggerEvent($sEventName, $aParams);
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

class Houston_Callable_WithOutputhandler_Struct implements Houston_Callable_Interface, Houston_EventTrigger {
	public $oCallable;
	public $oOutputhandler;
	
	public function __construct(Houston_Callable $oCallable, Houston_Outputhandler $oOutputhandler) {
		$this->oCallable = $oCallable;
		$this->oOutputhandler = $oOutputhandler;
	}
	
	public function call($aParams = NULL) {
		$this->oCallable->call($aParams);
	}
	
	public function triggerEvent($sEventName, $aParams = NULL) {
		$this->oOutputhandler->triggerEvent($sEventName, $aParams);
	}
	
	public function getIdentifier() {
		return $this->oCallable->getIdentifier();
	}
}

class Houston_CallableSet implements Houston_EventTrigger {
	protected $aCallables = array();
	private $sIdentifier = NULL;
	
	public function add(Houston_Callable_Interface $oCallable) {
		if (isset($this->aCallables[$oCallable->getIdentifier()])) {
			throw new Exception('identifier is not unique');
		}
		$this->aCallables[$oCallable->getIdentifier()] = $oCallable;
	}
	
	public function setIdentifier($sIdentifier) {
		if (isset($this->aCallables[$sIdentifier])) {
			$this->sIdentifier = $sIdentifier;
		} else {
			throw new Exception('unknown identifier');
		}
	}
	
	public function callIdentified($aParams = NULL) {
		$this->validateIdentifier();
		$this->aCallables[$this->sIdentifier]->call($aParams);
	}
	
	public function triggerEvent($sEventName, $aParams = NULL) {
		$this->validateIdentifier();
		if ($this->aCallables[$this->sIdentifier] instanceof Houston_EventTrigger) {
			$this->aCallables[$this->sIdentifier]->triggerEvent($sEventName, $aParams);
		} else {
			throw new Exception('callable is not instance of Houston_EventTrigger');
		}
	}
	
	private function validateIdentifier() {
		if (is_null($this->sIdentifier)) {
			throw new Exception('set identifier first');
		}
	}
	
	public function getIdentifiedCallable() {
		$this->validateIdentifier();
		return $this->aCallables[$this->sIdentifier]->oCallable;
	}
	
	public function getIdentifiedOutputhandler() {
		$this->validateIdentifier();
		return $this->aCallables[$this->sIdentifier]->oOutputhandler;
	}
}

interface Houston_Outputhandler extends Houston_EventTrigger {
	public function handleOutput($sOutput);
}

class Houston_Eventhandler implements Houston_Outputhandler {
	const EVENT_SEPARATOR = '###';
	private $oEvents;
	private $aEventParams;
	private $oException;
	
	public function __construct(Houston_Events $oEvents = NULL) {
		$this->oEvents = $oEvents;
	}
	
	public function handleOutput($sOutput) {
		$sExceptionNameMasked = $this->getEventName('exception');
		$iPosException = $this->getEventPos($sOutput, $sExceptionNameMasked);
		if (is_integer($iPosException)) {
			$aParams = $this->getParams($sOutput, $sExceptionNameMasked, $iPosException);
			$sOutput = substr($sOutput, 0, $iPosException);
			$this->oException = new Exception($aParams[0], $aParams[1], $aParams[2]);
		}
		if (!is_null($this->oEvents)) {
			foreach ($this->oEvents->getEventNames() as $sEventName) {
				$sEventNameMasked = $this->getEventName($sEventName);
				$iPosEvent = $this->getEventPos($sOutput, $sEventNameMasked);
				if (is_integer($iPosEvent)) {
					$aParams = $this->getParams($sOutput, $sEventNameMasked, $iPosEvent);
					$sOutput = substr($sOutput, 0, $iPosEvent);
					ob_start();
					$this->oEvents->setIdentifier($sEventName);
					$this->oEvents->callIdentified($aParams);
					$sOutput .= ob_get_contents();
					ob_end_clean();
				}
			}
		}
		return $sOutput;
	}
	
	private function getParams($sOutput,$sEventNameMasked, $iPosEvent) {
		$sParams = substr($sOutput, $iPosEvent + strlen($sEventNameMasked));
		return unserialize(json_decode($sParams));
	}
	
	private function getEventPos($sOutput, $sEventNameMasked) {
		return strpos($sOutput, $sEventNameMasked);
	}
	
	private function getEventName($sEventName) {
		return self::EVENT_SEPARATOR . $sEventName . self::EVENT_SEPARATOR;
	}

	public function triggerEvent($sEventName, $aParams = NULL) {
		echo $this->getEventName($sEventName) .
			json_encode(serialize($aParams)) . "\n";
	}
	
	public function getException() {
		return $this->oException;
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
	private $aParams;
	
	public function __construct(Houston_Callable_Identifier_Interface $oCallable, $aParams = NULL) {
		$this->oCallable = $oCallable;
		$this->sDefinitionFile = $_SERVER['SCRIPT_FILENAME'];
		$this->aParams = $aParams;
	}

	public function runSubprocess() {
		$aDescriptorspec = array(
		   0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		   2 => array("pipe", "w"), // stderr is a file to write to
		);
		$this->rProcess = proc_open(
			'php ' . 
				escapeshellarg($this->sDefinitionFile) . ' ' . 
				$this->oCallable->getIdentifier(), 
			$aDescriptorspec, 
			$this->aPipes
 		);
 		fwrite($this->aPipes[0], json_encode(serialize($this->aParams)));
 		fclose($this->aPipes[0]);
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
	private $oProcess;
	private $oOutputhandler;
	
	public function __construct(Houston_Process $oProcess, Houston_Outputhandler $oOutputhandler = NULL) {
		$this->oProcess = $oProcess;
		$this->oOutputhandler = $oOutputhandler;
	}
	
	public function isRunning() {
		return $this->oProcess->isRunning();
	}
	
	public function getOutput() {
		$sOutput = $this->oProcess->getOutput();
		if (!is_null($this->oOutputhandler)) {
			$sOutput = $this->oOutputhandler->handleOutput($sOutput);
			if ($this->oOutputhandler->getException()) {
				throw $this->oOutputhandler->getException();
			}
		}
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
	
	public function runSubprocess(Houston_Callable_Identifier_Interface $oCallable, Houston_Outputhandler $oOutputhandler = NULL, $aParams = NULL) {
		$oProcess = new Houston_Process($oCallable, $aParams);
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

interface Houston_EventTrigger {
	public function triggerEvent($sEventName, $aParams = NULL);
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