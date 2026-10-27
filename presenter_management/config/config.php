<?php
declare(strict_types=1);

/*
 * General application settings.
 * Change BASE_URL if your folder name is different from "presenter_management".
 */

define('APP_NAME', 'Presenter Manager');
define('BASE_URL', '/presenter_management');   // no slash at the end
define('APP_ENV', 'development');              // use 'production' when deployed

// Business rule
define('PRESENTATION_INTERVAL_DAYS', 90);

// Login security
define('MAX_LOGIN_ATTEMPTS', 5);       // wrong passwords before lock
define('LOCKOUT_MINUTES', 15);         // how long the account stays locked
define('SESSION_TIMEOUT_MINUTES', 60); // auto-logout after inactivity

date_default_timezone_set('Asia/Kolkata');

if (APP_ENV === 'development') {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/../logs/php-error.log');
    error_reporting(E_ALL);
}
