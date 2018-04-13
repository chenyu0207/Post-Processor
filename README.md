[![Post-Processor]()]()

# Post-Processor 后处理系统（DEALS & GOODS）
### ”promopure后处理，dealspure后处理.”


## prompure后处理

####启动

php goods_run.php 0

####指定参数

1. $argv[1] : 0 / 重新开始|无操作 (isset($argv[2]))；1 / 接上次id继续.
2. $argv[2] : test / 测试；update / 更新队列；assign / 指定抓取队列.

## dealspure后处理

####启动

php run.php 0

####指定参数

1. $argv[1] : 0 / coupon队列 ； deals / deals队列 ；  test / 测试 ；
2. $argv[2] : amazon / ebay / bestbuy /walmart / newegg(参数1为deals，参数2指定商城)


