<?php

return [
	'components' => [
		'request', 'response'
	],
    'response' => [
        'class' => '\vendor\base\ApiResponse'
    ],
    'request' => [
        'class' => '\vendor\base\ApiRequest',
        'usePostCookies' => true,
        'enableCookieValidation' => true,
        'cookieValidationKey' => 'kjiefJNKK:_(*&^%@!&_{+?:!',
    ]
];
