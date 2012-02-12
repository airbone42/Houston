<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
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

$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid();
		$oHouston->runSubprocess('underworld');
	}
);

$oHouston->launch('world');
