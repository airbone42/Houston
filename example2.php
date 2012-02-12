<?php
// init houston
require('Houston.php');
$oHouston = new Houston_Processmanager();

// define your processes needed as callables
$iIdUnderworld = $oHouston->addProcess(
	function () use ($oHouston) {
		echo 'Hello Underworld ' . getmypid();
	}
);
$iIdWorld = $oHouston->addProcess(
	function () use ($oHouston, $iIdUnderworld) {
		// launch from subprocess
		$oHouston->runProcessInBackground($iIdUnderworld);
		echo 'Hello World ' . getmypid();
	}
);

// only on first call
if (!isset($argv[1])) {
	// launch
	$oHouston->runProcessInBackground($iIdWorld);
}

// handle events and output of subprocesses
$oHouston->handleEvents();
