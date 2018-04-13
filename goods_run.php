<?php
/**
 * 入口文件
 * anycodes 站点的抓取.
 * date: 2018-01-19
 */

use Logic\Goods;

require_once dirname(__FILE__) . '/core/init_require.php';
// todo 测试配置，上线隐藏
// $dealsConfig['pgsql_db'] = [
//
//     'host' => '192.168.10.2',
//     'port' => 5432,
//     'user' => 'postgres',
//     'pass' => 'postgres',
//     'name' => 'shops'
// ];
// $dealsConfig['spider_mysql_db'] = [
//     'host' => '144.217.78.76',
//     'port' => '3306',
//     'user' => 'spider',
//     'pass' => 'v2yXU9JbyHetx5sL',
//     'name' => 'spider',
// ];
// // redis配置信息
// $dealsConfig['redis'] = [
//     'host' => '144.217.78.76',
//     'port' => 6379,
//     'pass' => 'LmgHugqDmTpv65vzbGRq',
//     'prefix' => '',
//     'timeout' => 30,
// ];

$dealsConfig['source'] = "goods";
if (!empty($dealsConfig)) {
    $logicObj = new Goods($dealsConfig);
} else {
    echo "config is null \n";
    die;
}
echo "#" . date("Y-m-d h:i:s", time()) . "\n";
$logicObj->isDebug = true;
$flag = 0;
try{
    // register_shutdown_function();
    $logicObj->start($argv);
} catch (Exception $e) {
    // $logicObj->start($argv);
    echo "我报错了我报错了\n";
    echo $e->getMessage();
    $msg = $dealsConfig['source'] ."后处理停止！！！";
    $logicObj->logger->error($msg);
}


