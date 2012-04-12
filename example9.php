<?php
// init houston
require('Houston.php');
$oHoustonProcessmanager = new Houston_Processmanager();

class My_Houston_Callable_Executable 
	extends Houston_Callable_Identifier
	implements Houston_Callable_Executable_Interface {
	private $sUrl;
	
	public function __construct($sIdentifier, $sUrl) {
		parent::__construct($sIdentifier);
		$this->sUrl = $sUrl;
	}
		
	public function getExecutable($sDefinitionFile) {
		return 'curl ' . 
			escapeshellarg($this->sUrl);
	}
}

$oHoustonProcessmanager->runSubprocess(
	new My_Houston_Callable_Executable(uniqid(), 'http://www.google.de')
);
