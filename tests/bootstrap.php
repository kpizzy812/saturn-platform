<?php

// Suppress E_DEPRECATED to prevent PHP 8.5 segfault caused by
// PHPUnit/Pest collecting thousands of ReflectionMethod::setAccessible() notices
error_reporting(E_ALL & ~E_DEPRECATED);

require __DIR__.'/../vendor/autoload.php';
