<?php

echo 'do anything in your main application' . "\n";

// init houston
require('Houston.php');
$oHouston = new Houston_Launcher('example5_definition.php');
$oHouston->launch('world');

// continues after all subprocesses are finished
echo "\n";
echo 'end your main application';