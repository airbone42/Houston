<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oCallableFactory = new Houston_Callable_Factory(
	'underworld', // unique identifier
	function () use ($oHouston) {
		$oHouston->triggerEvent('weHaveAProblem');
		echo 'Hello Underworld - ' . getmypid();
	},
	array( // define events
		'weHaveAProblem' => function () {
			echo 'back in parent process ' . getmypid();
		}
	)
);
$oCallable = $oCallableFactory->build();
// add them to houston
$oHouston->addCallable($oCallable);

$oCallableFactory = new Houston_Callable_Factory(
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid();
		$oHouston->runSubprocess('underworld');
	}
);
$oCallable = $oCallableFactory->build();
// add them to houston
$oHouston->addCallable($oCallable);

$oHouston->launch('world');
