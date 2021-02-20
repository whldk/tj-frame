<?php
return [
	'access' => [
	    '*' => []
	],
	'components' => [
		'db', 'session', 'user', 'route', 'request', 'response', 'upload', 'Logger', 'htmlpurifier', 'security'
	],
	'db' => [
		'class' => '\vendor\db\Db',
        'dsn' => 'mysql:host='.CONF_HOST.';dbname='.CONF_DB_NAME.';charset=UTF8',
        'username' => CONF_USERNAME,
        'passwd' => CONF_PASSWORD,
        'logger' => [
            'enable_log' => CONF_ENABLE_LOG,
				'logFile' => DIR_LOG . DIRECTORY_SEPARATOR . 'db.log'
		],
	],
	'session' => [
		'class' => '\vendor\base\DbSession',
		'name' => CONF_SESSION_NAME,
		'sessionTable' => 'session',
		'timeout' => CONF_SESSION_TIMEOUT,
	],
	'user' => [
		'enableAutoLogin' => true,
        'identityCookie' => CONF_IDENT_COOKIE,
		'cookieLifetime' => 2592000,	//30 * 24 * 60 * 60
		'identityClass' => 'user\models\IdentityModel',
		'enableExtraCookies' => true
	],
	'htmlpurifier' => [
		'class' => 'HTMLPurifier'
	],
	'security' => [
		'class' => '\vendor\base\Security'
	],
	'request' => [
		'enableCookieValidation' => true,
		'cookieValidationKey' => 'kjiefJNKK:_(*&^%@!&_{+?:!',
		'validateCookies' => [CONF_SESSION_NAME]
	]
];
