<?php

use libraries\auth\Request;

if (PHP_SAPI === 'cli-server') {
    // To help the built-in PHP dev server, check if the request was actually for
    // something which should probably be served as a static file
    $url  = parse_url($_SERVER['REQUEST_URI']);
    $file = __DIR__ . $url['path'];
    if (is_file($file)) {
        return false;
    }
}

/**
 * Include common setup
 */
include __DIR__ . '/common.php';

/**
 * Start PHP session
 */
if (!isset($_SESSION)) {
    session_start();
}

/**
 * Instantiate Slim Framework
 * Loads the setting for current environment
 */
$config = include APP_DIR . 'config/' . APP_ENV . '.php';
$app = new \Slim\App($config);

/**
 * Set up dependencies
 */
require APP_DIR . 'dependencies/containers.php';
require APP_DIR . 'dependencies/repositories.php';

/**
 * Register routers
 */
$routers = glob(APP_DIR . '/routes/*.router.php');
foreach ($routers as $route) {
    include $route;
}
unset($route, $routers);

/* add gen router */
$routers = glob(APP_DIR . '/routes/gen/*.router.php');
foreach ($routers as $route) {
    include $route;
}
unset($route, $routers);

/**
 * Run app
 */
$app->run();
