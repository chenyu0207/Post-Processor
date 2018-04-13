<?php

$dealsConfig = [
    //mysql配置deals数据库
    "mysql_db" => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'user' => 'spider',
        'pass' => 'v2yXU9JbyHetx5sL',
        'name' => 'coupons_deals',
    ],
    //mysql配置spider数据库
    "spider_mysql_db" => [
        'host' => '127.0.0.1',
        'port' => '3306',
        'user' => 'spider',
        'pass' => 'v2yXU9JbyHetx5sL',
        'name' => 'spider',
    ],
    //postgresql配置
    "pgsql_db" => [
        'host' => '66.70.176.130',
        'port' => 5432,
        'user' => 'postgres',
        'pass' => 'A7lmylG2DYnG1iCM',
        'name' => 'shops'
    ],
    // redis配置信息
    'redis' => [
        'host' => '127.0.0.1',
        'port' => 6379,
        'pass' => 'LmgHugqDmTpv65vzbGRq',
        'prefix' => '',
        'timeout' => 30,
    ],
];