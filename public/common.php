<?php
/**
 * Includes the composer autoloader
 */
include __DIR__ . '/../vendor/autoload.php';

/**
 * Define the base directory of the whole project
 */
define('BASE_DIR', dirname(__DIR__) . '/');

/**
 * Define the base directory of the current application
 */
define('APP_DIR', BASE_DIR . '/application/');

/**
 * Define the vendor directory of the current application
 */
define('VENDOR_DIR', BASE_DIR . '/vendor/');

/**
 * Define Application environment
 *
 * For development and production
 */
define('APP_ENV_PRODUCTION', 'production');
define('APP_ENV_STAGE', 'stage');
define('APP_ENV_DEVELOPMENT', 'development');
define('APP_ENV_DEVELOPMENT_DOCKER', 'development_docker');

/**
 * For service environment to development and production
 */
define('APP_ENV_SERVICE_PRODUCTION', 'production');
define('APP_ENV_SERVICE_STAGE', 'stage');
define('APP_ENV_SERVICE_DEVELOPMENT', 'development');

/**
 * Define the current environment
 */
if (!defined('APP_ENV')) {
    define('APP_ENV', $_SERVER['CI_ENV'] ?? APP_ENV_DEVELOPMENT_DOCKER);
}

/**
 * Define the service environment of current
 */
if (!defined('APP_ENV_SERVICE')) {
    if (APP_ENV === APP_ENV_PRODUCTION) {
        define('APP_ENV_SERVICE', APP_ENV_SERVICE_PRODUCTION);
    } elseif (APP_ENV === APP_ENV_STAGE) {
        define('APP_ENV_SERVICE', APP_ENV_SERVICE_STAGE);
    } else {
        define('APP_ENV_SERVICE', APP_ENV_SERVICE_DEVELOPMENT);
    }
}

/**
 * An example of a project-specific implementation.
 *
 * After registering this autoload function with SPL, the following line
 * would cause the function to attempt to load the \Foo\Bar\Baz\Qux class
 * from /path/to/project/src/Baz/Qux.php:
 *
 *      new \Foo\Bar\Baz\Qux;
 *
 * @param string $class The fully-qualified class name.
 * @return void
 */
spl_autoload_register(static function ($class) {
    // get the relative class name
    $relativeClass = $class;

    // replace the namespace prefix with the base directory, replace namespace
    // separators with directory separators in the relative class name, append
    // with .php
    if (false !== strpos($class, 'Ntuple\\Synctree')) {
        $version = explode('.', PLAN_VERSION);
        $relativeClass = str_replace('Ntuple\\Synctree', 'src/'.implode('/', $version), $relativeClass);
        $file = APP_DIR . '../' . str_replace('\\', '/', $relativeClass) . '.php';
    } else {
        $file = APP_DIR . str_replace('\\', '/', $relativeClass) . '.php';
    }

    // if the file exists, require it
    if (file_exists($file)) {
        require $file;
    }
});

// notices handler
set_error_handler(static function ($severity, $message, $file, $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting, so ignore it
        return;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});
