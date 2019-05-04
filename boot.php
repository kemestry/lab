<?php
/**
 * OpenTHC Lab Application Bootstrap
 */

define('APP_NAME', 'OpenTHC | QA');
define('APP_SITE', 'https://lab.openthc.org');
define('APP_ROOT', __DIR__);
define('APP_SALT', sha1(APP_NAME . APP_SITE . APP_ROOT));
define('APP_BUILD', '420.19.123');

openlog('openthc-lab', LOG_ODELAY|LOG_PID, LOG_LOCAL0);

error_reporting(E_ALL & ~ E_NOTICE);

require_once(APP_ROOT . '/vendor/autoload.php');
