<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid() . "\n";
		$oHouston->runSubprocess('helloParam', array('Underworld'));
	}
);
$oHouston->addCallable(	
	'helloParam', // unique identifier
	function ($sWorld = 'World') use ($oHouston) {
		echo 'Hello ' . $sWorld . ' - ' . getmypid();
	}
);

$oHouston->launch('world');