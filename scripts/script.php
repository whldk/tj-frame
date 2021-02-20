<?php
use vendor\base\ConsoleRequest;
use vendor\base\ConsoleResponse;
use vendor\base\ConsoleUser;

define('AGENT', 'script');

set_time_limit(600);

//init
require_once __DIR__ . '/../init.php';

//autoload
require_once __DIR__ . '/../autoload.php';

//constants
require_once __DIR__ . '/../config/constants.php';

//config
$config = require_once DIR_APP . '/config/config.php';



$config['request'] = new ConsoleRequest();
$config['response'] = new ConsoleResponse();
$config['user'] = new ConsoleUser();

//app
$app = \App::getInstance($config);

/* @var $app \App */
return $app->bootstrap();