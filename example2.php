<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'underworld', // unique identifier
	function () {
		echo 'Hello Underworld - ' . getmypid();
	}
);

$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid() . "\n";
		$oHouston->runSubprocess('underworld');
	}
);

$oHouston->launch('world');