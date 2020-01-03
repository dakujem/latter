<?php

namespace Dakujem\Latter\Tests;

use Tester\Environment;

error_reporting(E_ALL);
ini_set('display_errors', 1);


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/BaseTest.php';

// tester + errors
Environment::setup();

/*
// error handling
if (PHP_SAPI !== 'cli') {
    Debugger::$strictMode = true;
    Debugger::enable();
    Debugger::$maxDepth = 10;
    Debugger::$maxLength = 500;
}


// dump shortcut
function dump($var, $return = false)
{
    return Debugger::dump($var, $return);
}
*/
