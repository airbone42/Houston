<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'underworld', // unique identifier
	function () use ($oHouston) {
		echo 'Hello Underworld - ' . getmypid() . "\n";
		$oHouston->triggerEvent('weHaveAProblem');
		echo 'Underworld is still running - ' . getmypid();
	},
	array( // define events
		'weHaveAProblem' => function () {
			echo 'back in parent process ' . getmypid() . "\n";
		}
	)
);

$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid() . "\n";
		$oHouston->runSubprocess('underworld');
	}
);

$oHouston->launch('world');
