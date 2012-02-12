<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'world', // unique identifier
	function () {
		echo 'Hello World - ' . getmypid();
	}
);

$oHouston->launch('world');
