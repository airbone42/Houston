<?php
// init houston
require('Houston.php');
$oHouston = new Houston_Processmanager();

// define your processes needed as callables
$iIdUnderworld1 = $oHouston->addProcess(
	function () use ($oHouston) {
		echo 'Hello Underworld ' . getmypid();
	}
);
$iIdUnderworld2 = $oHouston->addProcess(
	function () use ($oHouston) {
		echo 'Hello Underworld ' . getmypid();
	}
);

if (!isset($argv[1])) {
	// launch 
	$oHouston->runProcessInBackground($iIdUnderworld1);
	// launch another
	$oHouston->runProcessInBackground($iIdUnderworld2);
}

// handle events and output of subprocesses
$oHouston->handleEvents();
