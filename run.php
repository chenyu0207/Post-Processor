<?php
/**
 * 入口文件
 * anycodes 站点的抓取.
 * date: 2018-01-19
 */

use Logic\Deals;

require_once dirname(__FILE__) . '/core/init_require.php';
// todo 测试配置，上线隐藏
$dealsConfig['pgsql_db'] = [

    'host' => '192.168.10.2',
    'port' => 5432,
    'user' => 'postgres',
    'pass' => 'postgres',
    'name' => 'shops'
];
$dealsConfig['mysql_db'] = [
    'host' => '144.217.78.76',
    'port' => '3306',
    'user' => 'spider',
    'pass' => 'v2yXU9JbyHetx5sL',
    'name' => 'coupons_deals',
];
// redis配置信息
$dealsConfig['redis'] = [
    'host' => '144.217.78.76',
    'port' => 6379,
    'pass' => 'LmgHugqDmTpv65vzbGRq',
    'prefix' => '',
    'timeout' => 30,
];

if (!empty($dealsConfig)) {
    if ($argv[1] == "deals") {
        $dealsConfig['source'] = 'deals';
    } else {
        $dealsConfig['source'] = 'coupons';
    }
    try {
        $logicObj = new Deals($dealsConfig);
    } catch (Exception $e) {
        $msg = "1.[" . date("Y-m-d H:i:s", time()) . "] 错误 " . $dealsConfig['source'] . "后处理停止！！！[" . $e->getMessage() . "]";
        echo $msg;
    }
} else {
    echo "config is null \n";
    die;
}
echo "#" . date("Y-m-d h:i:s", time()) . "\n";
$logicObj->isDebug = true;
$flag = 0;
try {
    $logicObj->start($argv);
} catch (Exception $e) {
    // $logicObj->start($argv);
    $msg = $dealsConfig['source'] . "后处理停止！！！[" . $e->getMessage() . "]";
    $logicObj->logger->error($msg);
}


