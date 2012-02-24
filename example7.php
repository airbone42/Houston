<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'underworld', // unique identifier
	function () use ($oHouston) {
		echo 'Hello Underworld - ' . getmypid() . "\n";
		throw new Exception('foobar');
	}
);

$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid() . "\n";
		try {
			$oHouston->runSubprocess('underworld');
		} catch (Exception $oException) {
			echo 'Catched exception from the underworld: ' . $oException->getMessage();
		}
	}
);

$oHouston->launch('world');
