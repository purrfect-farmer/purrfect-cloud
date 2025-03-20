<?php

return [
    'app' => [
        'api_id' => 2496,
        'api_hash' => '8da85b0d5bfe62527e5b244c209159c3',
        'lang_pack' => 'webk',
        'lang_code' => 'en',
        'system_lang_code' => 'en-US',
        'app_version' => '2.2 K',
        'device_model' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/134.0.0.0 Safari/537.36',
        'system_version' => 'Linux x86_64'
    ],
    'database' => [
        'uri' => env('MADELINE_DB_URI', 'tcp://localhost'),
        'database' => env('MADELINE_DB_DATABASE', 'laravel'),
        'username' => env('MADELINE_DB_USERNAME', 'root'),
        'password' => env('MADELINE_DB_PASSWORD', ''),
        'prefix' => 'madeline_'
    ]
];
