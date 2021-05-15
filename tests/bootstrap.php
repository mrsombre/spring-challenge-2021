<?php

$_ENV['APP_ENV'] = 'test';

require_once __DIR__ . '/../main.php';
require_once __DIR__ . '/functions.php';

set_error_handler(
    function ($errno, $errstr, $errfile, $errline) {
        throw new ErrorException($errstr, $errno, 1, $errfile, $errline);
    }
);
