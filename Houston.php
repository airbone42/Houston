<?php
class Houston_Processmanager {
	const EVENT_SEPARATOR = '###';
	
	private $aProcesses = array();
	private $aPipes = array();
	private $aProcessesRunning = array();
	
	public function addProcess($cProcess, $aEvents = array()) {
		$oProcess = new Houston_Process(
				$cProcess,
				$aEvents
			);
		$this->aProcesses[] = $oProcess;
		end($this->aProcesses);
		$iKeyProcess = key($this->aProcesses);
		global $argv;
		if (isset($argv[1]) AND $argv[1] == $iKeyProcess) {
			$oProcess->run();
		}
		return $iKeyProcess;
	}
	
	
	public function runProcess($sKeyProcess) {
		$this->aProcesses[$sKeyProcess]->run();
	}
	
	public function runProcessInBackground($sKeyProcess) {
		$this->aProcessesRunning[$sKeyProcess] = $this->aProcesses[$sKeyProcess];
		$this->aProcesses[$sKeyProcess]->runInBackground($sKeyProcess);
	}
	
	public function handleEvents() {
		while (count($this->aProcessesRunning)) {
			foreach ($this->aProcessesRunning as $sKeyProcess => $oProcess) {
				if (!$oProcess->isRunning()) {
					unset($this->aProcessesRunning[$sKeyProcess]);
				}
				$sOutput = $oProcess->getOutput();
				foreach ($oProcess->getEventNames() as $sEventName) {
					if (is_integer(strpos($sOutput, self::EVENT_SEPARATOR . $sEventName . self::EVENT_SEPARATOR))) {
						$sOutput = str_replace(self::EVENT_SEPARATOR . $sEventName . self::EVENT_SEPARATOR, '', $sOutput);
						$oProcess->runEvent($sEventName);
					}
				}
				echo $sOutput;
			}
			usleep(100);
		}
	}
	
	public function triggerEvent($sEventName) {
		echo self::EVENT_SEPARATOR . $sEventName . self::EVENT_SEPARATOR;
	}
}

class Houston_Process {
	private $cProcess;
	private $rProcess;
	private $aPipes;
	private $aEvents;
	
	public function __construct($cProcess, $aEvents = array()) {
		$this->cProcess = $cProcess;
		$this->aEvents = $aEvents;
	}
	
	public function run() {
		if (!is_callable($this->cProcess)) {
			throw new Exception($this->cProcess . ' is not callable');
		}
		$cProcess = $this->cProcess;
		$cProcess();
	}
	
	public function runEvent($sEvent) {
		$cEvent = $this->aEvents[$sEvent];
		$cEvent();
	}
	
	public function getEventNames() {
		return array_keys($this->aEvents);
	}
	
	public function runInBackground($sKeyProcess) {
		$aDescriptorspec = array(
		   0 => array("pipe", "r"), // stdin is a pipe that the child will read from
		   1 => array("pipe", "w"), // stdout is a pipe that the child will write to
		   2 => array("pipe", "w"), // stderr is a file to write to
		);
		$this->rProcess = proc_open(
			'php ' . escapeshellarg($_SERVER['SCRIPT_FILENAME']) . ' ' . $sKeyProcess, 
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