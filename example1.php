<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oCallableFactory = new Houston_Callable_Factory(
	'world', // unique identifier
	function () {
		echo 'Hello World - ' . getmypid();
	}
);
$oCallable = $oCallableFactory->build();
// add them to houston
$oHouston->addCallable($oCallable);

$oHouston->launch('world');
