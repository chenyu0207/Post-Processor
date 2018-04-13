<?php
/**
 * 初始设置, 进入文件.
 * User: chenyu
 * Date: 2018/4/12
 * Time: 下午3:11
 */

ini_set('max_execution_time', 0);
set_time_limit(0);
define("_CORE_", dirname(__FILE__));
define("_LOG_", dirname(__FILE__) . '/../log');

require_once(_CORE_.'/../vendor/autoload.php');
require_once(_CORE_.'/db.class.php');
require_once(_CORE_.'/config.php');
require_once(_CORE_.'/redis.class.php');
require_once(_CORE_.'/mysql.class.php');
require_once(_CORE_.'/../deals.calss.php');
require_once(_CORE_.'/../goods.class.php');

