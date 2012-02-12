<?php
// init houston
require('Houston.php');
$oHouston = new Houston_Processmanager();

// define your processes needed as callables
$iIdWorld = $oHouston->addProcess(
	function () use ($oHouston) {
		$oHouston->triggerEvent('weHaveAProblem');
		echo 'Hello World ' . getmypid();
	},
	// define events
	array(
		'weHaveAProblem' => function () {
			echo 'back in parent process ' . getmypid();
		}
	)
);

// only on first call
if (!isset($argv[1])) {
	// launch
	$oHouston->runProcessInBackground($iIdWorld);
}

// handle events and output of subprocesses
$oHouston->handleEvents();
