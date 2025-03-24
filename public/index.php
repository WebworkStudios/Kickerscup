<?php

declare(strict_types=1);

/**
 * Application Entry Point
 */

// Define the application root directory
define('APP_ROOT', dirname(__DIR__));

// Load Composer autoloader
require APP_ROOT . '/vendor/autoload.php';

// Load environment variables
//$dotenv = new \Symfony\Component\Dotenv\Dotenv();
//$dotenv->loadEnv(APP_ROOT . '/.env');

// Bootstrap the application
$app = require APP_ROOT . '/bootstrap/app.php';


// Run the application
$response = $app->run();

// Send the response
$response->send();