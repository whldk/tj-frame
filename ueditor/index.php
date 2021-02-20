<?php
define('AGENT', 'http');

//init
require_once __DIR__ . '/../init.php';

//autoload
require_once __DIR__ . '/../autoload.php';

//config
$config = require_once __DIR__ . '/../config/config.php';

$config['access'] = ['*' => 'admin'];
$config['route']['module'] = 'ueditor';
$config['route']['controller'] = 'ueditor';

//app
$app = \App::getInstance($config);

/* @var $app \App */
return $app->bootstrap();