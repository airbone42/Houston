<?php
// init houston
require('Houston.php');
$oHouston = new Houston();

// define your processes needed as callables
$oHouston->addCallable(	
	'world', // unique identifier
	new HelloWorld()
);

$oHouston->launch('world');

class HelloWorld {
	public function __invoke() {
		echo 'Hello World - ' . getmypid();
	}
}