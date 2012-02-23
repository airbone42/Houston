<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'world', // unique identifier
	function () use ($oHouston) {
		echo 'Hello World - ' . getmypid() . "\n";
		$oHouston->triggerEvent(
			'weHaveAProblem', 
			array('foo' => 'bar')
		);
	},
	array(
		'weHaveAProblem' => function ($sFoo) use ($oHouston) {
			var_dump($sFoo);
		}
	)
);

$oHouston->launch('world');
