<?php
define('AGENT', 'http');

//init
require_once __DIR__ . '/init.php';

//autoload
require_once __DIR__ . '/autoload.php';

//config
$config = require_once DIR_APP . '/config/config.php';

//app
$app = \App::getInstance($config);

/* @var $app \App */
return $app->bootstrap();