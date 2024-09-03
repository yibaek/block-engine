<?php declare(strict_types=1);

if (!defined('APP_DIR')) {
    define('APP_DIR',  dirname(__DIR__) . '/tests/');
}

if (!defined('APP_ENV')) {
    define('APP_ENV', 'testing');
}


require_once dirname(__DIR__) . '/vendor/autoload.php';
require_once dirname(__DIR__) . '/application/libraries/constant/CommonConst.php';