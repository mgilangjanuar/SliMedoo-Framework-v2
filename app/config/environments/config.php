<?php

$config = [
    'dir' => '/slimedoo',
    'name' => 'SliMedoo Framework v2',
    'defaultFunction' => 'actionIndex',
    'timezone' => 'Asia/Jakarta',
    // 'db'  => [
    //     'database_type' => 'sqlite',
    //     'database_file' => 'app/config/database/db.sqlite'
    // ],
    'db' => [
        'database_type' => 'mysql',
        'database_name' => 'slimedoo',
        'server' => 'localhost',
        'username' => 'root',
        'password' => 'root',
        'charset' => 'utf8',
     ],
];