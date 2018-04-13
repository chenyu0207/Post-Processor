<?php

namespace Logic;

use Core\PostgreSQL;
use Core\mysqldb;
use Core\Uploader;
use Core\RedisAction;
use Monolog\Logger;
use Monolog\Registry;
use Monolog\Handler\StreamHandler;
use Monolog\Handler\SwiftMailerHandler;
use Monolog\Processor\UidProcessor;
use Monolog\Processor\ProcessIdProcessor;
use Monolog\Formatter\JsonFormatter;

class Goods
{
    /**
     * @var \Monolog\Logger
     */
    public $logger;

    /**
     * @var object $postgresqldb 数据库的instance
     */
    private $pgsqldb;

    /**
     * @var object $mysqldb 数据库的instance
     */
    private $mysqldb;

    /**
     * @var array $config 配置文件
     */
    private $config;

    /**
     * @var object $redis redis的instance
     */
    public $redis;
    /**
     * @var object $source 脚本类型
     */
    public $source;

    /**
     * 初始化
     * coupons_deals constructor.
     * @param array $config
     */
    public function __construct($config = [])
    {

        if (!$config) {
            echo "没有指定配置文件. \n";
            die;
        }
        //配置文件
        $this->config = $config;
        //连接redis
        $this->redis = new RedisAction();
        $this->redis->connect($this->config['redis']);
        //连接mysql
        $this->mysqldb = new mysqldb($this->config['spider_mysql_db']);
        //连接postgresql
        $this->pgsqldb = new PostgreSQL($config['pgsql_db']['host'], $config['pgsql_db']['port'], $config['pgsql_db']['name'], $config['pgsql_db']['user'], $config['pgsql_db']['pass']);
        $this->pgsqldb->connect();
        //来源
        $this->source = $config['source'];
        $this->init();
    }

    /**
     * init
     */
    public function init()
    {
        // monolog 日志系统, 实例化一个日志实例, 参数是 channel name
        $this->logger = new Logger($this->source);
        // 记录用的日志处理器.
        $streamHander = new StreamHandler(__DIR__ . '/log/spider_' . $this->source . '.log', Logger::INFO);
        $this->logger->pushHandler($streamHander);
        // 发送邮件的处理器.
        //TODO隐藏邮件
        $mailer = new \Swift_Mailer((new \Swift_SmtpTransport('smtp.163.com', 25))->setUsername('promopure@163.com')->setPassword('qiuyu123'));
        $message = (new \Swift_Message())->setFrom(['promopure@163.com' => 'promopure'])->setTo(['z9933363z@163.com']);
        $message->setSubject('警告, 快点来看看这个情况.')->setBody('快点来看看这个情况, 需要快点处理一下.');
        $emailHander = new SwiftMailerHandler($mailer, $message);
        $this->logger->pushHandler($emailHander);
        // 日志加工程序
        $this->logger->pushProcessor(new UidProcessor());
        $this->logger->pushProcessor(new ProcessIdProcessor());
        $this->logger->pushProcessor(function ($record) {
            var_dump('[' . date("Y-m-d H:i:s", time()) . '] ' . $record['message']);
            if ($record['context']) {
                var_dump($record['context']);
            }
            return $record;
        });
    }

    /**
     * 开始程序判断
     * @param $argv
     */
    public function start($argv)
    {
        if (count($argv) < 2) {
            die("没有指定参数, 1:继续, 0:重新开始\n");
        }


        if ($argv[1] == 3) {
            $sql = "select * from path";
            $path = $this->pgsqldb->doSql($sql);
            foreach ($path as $k => $v) {
                $title = urlencode($this->getGoodsUrl($v['displayname'], 1));
                $upsql = "update list_page_rule set friendly_url_part = '" . $title . "' where exp_id =" . $v['id'];
                $this->pgsqldb->doSql($upsql);
                echo $upsql;
                echo "\n";
            }
        }

        if (isset($argv[2])) {
            if ($argv[2] == "download") {
                $status = 11;
                $limit = 10;
            } elseif ($argv[2] == "top100") {
                $limit = 0;
                $status = 6;
            } elseif ($argv[2] == "assign") {
                $status = 10;
                $limit = 10;
            } elseif ($argv[2] == "update") {//9999获取更新队列
                $status = 4;
                $limit = 1;
            } elseif ($argv[2] == "test") {
                $status = 3;
                $limit = 1;
            } else {
                $limit = 100;
                $id_file = dirname(__FILE__) . '/log/id' . $argv[2] . '.log';
                $numbstar = $argv[2] * $argv[3];
                $numbend = $numbstar + $argv[3];
                $status = 1;
            }
        } else {
            $limit = 100;
            $status = 0;
            $id_file = dirname(__FILE__) . '/log/newid.log';
        }


        if ($argv[1] == 1) {
            $id = file_get_contents($id_file);
            if ($id > 0) {
                $id = $id - 1;
            }
        } elseif ($argv[1] == 0) {
            $id = 0;
        } else {
            die('参数错误, 参数1（必填） 1:继续, 0:重新开始 / 参数2 1:分段参数 / 参数3 10000:处理条数');
        }
        $n = 0;
        do {
            do {
                $m = $n * $limit;
                if ($status == 11) {
                    $asin_arr = array(
                        '1598038559'
                    );
                    foreach ($asin_arr as $k => $v) {
                        echo $k . " : " . $v . " \n";
                        $data['url'] = 'https://www.amazon.com/dp/' . $v;
                        $this->redis->rpush("amazon_lastActionUpdateQueue", $v);
//                $redis->rpush("amazon_detailQueue", json_encode($data));
                    }

                    die;
                    $sql = 'select DISTINCT(id),* from detail_page_rule  limit 1000 OFFSET 10';
                    $result = $this->pgsqldb->doSql($sql);
                    foreach ($result as $k => $v) {
                        echo $v['id'];
                        echo "\n";
                        $where = "('" . $v['id'] . "','" . $v['goods_id'] . "','" . $v['friendly_url_part'] . "')";
                        $sql = "insert into detail_page_rule (id,goods_id,friendly_url_part) VALUES " . $where;
                        $result = $this->pgsqldb->doSql($sql);
                    }
                } elseif ($status == 6) {//获取top100
                    echo "#top100开始 ||";
                    $infos = $this->redis->rpop('amazon_top100Queue', true);
                    print_r($infos);
                    if ($infos) {
                        $info = json_decode($infos, true);
                        unset($infos);
                        echo $info['top_tag'] . " || ";
                        if ($info['top_tag'] == "2407749011") {
                            $info['category'] = "Electronics > Cell Phones & Accessories > Cell Phones";
                        } else {
                            $info['category'] = "Clothing, Shoes & Jewelry > Men > Clothing > Shirts";
                        }
                        print_r($info);
                        $where = '"' . implode('","', $info['refer_ids']) . '"';
                        $sql = "select * from spider where refer_id in (" . $where . ")";
                    }

                } elseif ($status == 10) {//抓取指定url
                    echo "#抓取指定url开始 || ";
                    $arr = $this->redis->rpop('amazon_adminQueue', true);
                    $array = json_decode($arr, true);
//            $array['refer_id'] = 'B071GBVYCX';
//            $array['fetch_goods_id'] = 5;
                    print_r($array);
                    $sql = "SELECT * FROM new_spider WHERE refer_id = '" . $array['sub_refer_id'] . "'";// AND get_type != 2
                } elseif ($status == 4) {//获取更新队列
                    $ASIN = $this->redis->rpop('amazon_lastActionUpdateQueue', true);//' B01M8MHB7T';//'B005KSW5PA';//
                    echo "#更新队列开始# " . $ASIN . " || ";
                    $sql = "SELECT * FROM new_spider WHERE refer_id = '" . $ASIN . "'";// AND get_type != 2
                    echo $sql;
                    unset($ASIN);
                } elseif ($status == 1) {
                    $sql = "SELECT * FROM new_spider WHERE  id BETWEEN $numbstar and $numbend ORDER BY id LIMIT $m,$limit";
                } elseif ($status == 3) {
                    //        $array = [
                    //            'B01BYRMGCC',
                    // 'B01NCM25K7',
                    // 'B0112M60KI',
                    // 'B0160HYB8S',
                    // 'B01GLS0C2K',
                    //        ];
                    $ASIN = 'B01C8CX77A';////$redis->rpop('amazon_lastActionUpdateQueue',true);//'B01M8MHB7T';//'B005KSW5PA';//
                    echo "#测试开始# " . $ASIN . " || ";
                    $sql = "SELECT * FROM new_spider WHERE refer_id = '" . $ASIN . "'";// AND get_type != 2
                } else {
                    echo "#新增商品开始# ||";
                    $sql = "SELECT * FROM new_spider WHERE id > $id  ORDER BY id LIMIT $limit";
                }
                if (isset($sql)) {
                    $res = $this->mysqldb->doSql($sql);
                } else {
                    $res = "";
                }
//        $db = database($config["db_2"]);
                if ($res) {
                    if ($status == 1) {
                        $this->goods($res, $argv[2]);
                    } elseif ($status == 4) {
                        $this->goods($res, 9999);
                    } elseif ($status == 6) {
                        $this->goods($res, 89999, $info);
                    } elseif ($status == 10) {
                        echo "#开始抓取";
                        $this->goods($res, 99999, $array['refer_id'], $array['fetch_goods_id']);
                    } else {
                        $this->goods($res);
                    }
                }
                $n = $n + 1;
                if ($status == 10 || $status == 6) {

                } elseif ($status == 3) {
                    die;
                } elseif ($status !== 4) {
                    $id = file_get_contents($id_file);
                }
            } while (count($res) == $limit);
            if ($status == 1 || $status == 3) {
                echo "更新结束";
                die;
            }
            if ($status == 4) {
                if (empty($ASIN)) {
                    echo "#" . date("Y-m-d h:i:s", time()) . "当前队列为空, 暂时获取不到数据. 延迟10秒 \n";
                    sleep(10);
                } else {
                    echo "#" . date("Y-m-d h:i:s", time()) . "当前" . $ASIN . "无更新内容.\n";
                }

            } elseif ($status == 6) {
                unset($infos);
                echo "#" . date("Y-m-d h:i:s", time()) . "当前Top100暂时获取不到数据. 延迟10秒 \n";
                sleep(10);
            } elseif ($status == 10) {
                if (empty($ASIN)) {
                    echo "#" . date("Y-m-d h:i:s", time()) . "当前指定url暂无数据. 延迟10秒 \n";
                    sleep(10);
                } else {
                    echo "当前" . $ASIN . "无更新内容.\n";
                }
            } else {
                echo "#" . date("Y-m-d h:i:s", time()) . "当前最大ID是$id, 暂时获取不到数据. 延迟10秒 \n";
                sleep(10);
            }
        } while (true);
    }

    /*
     * 商品属性逻辑
     */
    public function goods($data, $page = "", $fetch_refer_id = "", $fetch_goods_id = "")
    {
        echo " #Start";
        echo "\n";
        $fetch = 5;
        if ($page == 99999) {
            $fetch = 1;
        } elseif ($page == 89999) {
            $fetch = 2;
        }
        foreach ($data as $k => $v) {
//        var_dump($v);
            if ($page == "") {
                file_put_contents(dirname(__FILE__) . '/log/newid.log', $v['id']);
            } elseif ($page == 9999) {

            } else {
                file_put_contents(dirname(__FILE__) . '/log/id' . $page . '.log', $v['id']);
            }
            echo " #spiderID:" . $v['id'] . " ";
            //商品表入库信息
            echo " #ASIN:" . $v['refer_id'];
            if (!empty($v['refer_id'])) {
                //标题
                $v['title'] = preg_replace("/[\']+/", "''", $v['title']);
                $rsv[$k]["title"] = $v['title'];
                //来源、url
                if ($v['source'] == 1) {
                    $rsv[$k]["url"] = "https://www.amazon.com/dp/" . $v['refer_id'];
                    $source[$k] = "Amazon";
                    $rsv[$k]['source'] = $source[$k];
                }
                //Asin
                $rsv[$k]['asin'] = $v['refer_id'];
                //描述
                $v['about'] = preg_replace("/[\']+/", "''", $v['about']);
                $rsv[$k]["desc"] = $this->str($v['about']);
                //星等、评论人数
                if ($v['star'] > -1) {
                    $rsv[$k]["star"] = $v['star'];
                    $rsv[$k]["star_num"] = $v['review_total'];
                    if ($v['star'] > 0 || $v['review_total']) {
                        $rsv[$k]['best_weight'] = pow(1.5, $rsv[$k]['star']) * log($rsv[$k]['star_num'], M_E);// = $rsv[$k]["star"]*100+$rsv[$k]["star_num"]*0.1;
                    }
                } else {
                    $rsv[$k]["star"] = 0;
                    $rsv[$k]["star_num"] = 0;
                    $rsv[$k]["is_star"] = 0;
                }
                //图片地址。图片数量
                $imgurl = json_decode($v['img_url'], true);
                // print_r($imgurl);
                $addenda_info = json_decode($v['addenda_info'], true);
                if (isset($imgurl['addenda_img'])) {
                    foreach ($imgurl['addenda_img'] as $color_k => $color_v) {
                        $color_id = $this->imgColor($v['id'], strtolower($this->str($color_k)));
                        if (!empty($color_id)) {
                            if (!empty($color_v)) {
                                $colors[$color_id] = $color_k;
                                $color_img[$color_id] = $color_v[0];
                            }
                        } else {
                            file_put_contents(dirname(__FILE__) . "/log/noColor.log", $rsv[$k]['asin'] . "\n", FILE_APPEND);
                            echo " #附加颜色无匹配";
                        }
                    }
                }

                if (!empty($colors)) {
                    $addenda_info['Color'] = $colors;
                    unset($colors);
                } else {
                    unset($addenda_info['Color']);
                }

                //判断图片数量是否为空
                if (!empty($v['img_url'])) {
                    //判断有无价格数据
                    if ($v['original_price'] > 0) {
                        $rsv[$k]["original_price"] = $v['original_price'];
                        if ($v['deal_price'] == 0) {
                            $rsv[$k]["price"] = $v['original_price'];
                            $rsv[$k]['save'] = 0;
                        } else {
                            $rsv[$k]["price"] = $v['deal_price'];
                            $rsv[$k]['save'] = round((($rsv[$k]['original_price'] - $rsv[$k]['price']) / $rsv[$k]['original_price']) * 100);
                        }
                    } else {
                        if ($v['third_part_lowest_price'] > 0) {
                            $rsv[$k]["original_price"] = $v['third_part_lowest_price'];
                            $rsv[$k]["price"] = $v['third_part_lowest_price'];
                            $rsv[$k]['save'] = 0;
                        }
                    }
                    $sql = "select min(deal_price) as deal_price from history_price_new where refer_id = '" . $v['refer_id'] . "' and deal_price > 0";
                    $deal_price[$k] = $this->mysqldb->doSql($sql);
                    if ($deal_price[$k]) {
                        echo " #历史最低价格：" . $deal_price[$k][0]['deal_price'] . " ";
                        if (isset($rsv[$k]['price'])) {
                            if ($rsv[$k]["price"] > 0) {
                                echo " #当前价格：" . $rsv[$k]["price"] . " ";
                                if ($deal_price[$k][0]['deal_price'] >= $rsv[$k]["price"]) {
                                    $rsv[$k]['minimum_price_statsus'] = 1;
                                } else {
                                    $rsv[$k]['minimum_price_statsus'] = 0;
                                }
                            }
                        }
                    }
                    //是否打折
                    $rsv[$k]["status"] = $v['status'];
                    $v['deal_title'] = preg_replace("/[\']+/", "''", $v['deal_title']);
                    if ($v['is_deal_title_valid'] == 1) {
                        $rsv[$k]["deal_title"] = $v['deal_title'];
                    } else {
                        $rsv[$k]["deal_title"] = '';
                    }
                    $rsv[$k]["is_canonical"] = $v['is_canonical'];
                    //集合
                    $rsv[$k]['is_free'] = $v['free_shipping'];
                    $rsv[$k]["is_sets"] = $v['is_sets'];
                    $rsv[$k]["refer"] = $v["refer"];
                    $rsv[$k]["addenda_info"] = preg_replace("/[\']+/", "''", json_encode($addenda_info));
                    $v['brand'] = preg_replace("/[\']+/", "''", $v['brand']);
                    $rsv[$k]["brand"] = $v['brand'];
                    $rsv[$k]["category_all"] = preg_replace("/[\']+/", "''", $v['category']);
                    $cla[$k] = json_decode($v['category'], true);
                    //生成字符串id
                    do {
                        $stringId = 'G0' . $this->randString('8');
                        $sql = "SELECT id FROM goods WHERE string_id = '" . $stringId . "'";
                        $result = $this->pgsqldb->doSql($sql);
                        if (!$result) {
                            $rsv[$k]["string_id"] = $stringId;
                        }
                    } while ($result);

                    //获取一级分类id
                    $cla = json_decode($v['category'], true);
                    if (!empty($cla)) {
                        for ($i = 0; $i < count($cla); $i++) {
                            if ($i == 0) {
                                $kcate = $i;
                                $catch_cal = $cla[0];
                            }
                            if ($i - 1 >= 0) {
                                if (count($cla[$i]) > count($catch_cal)) {
                                    $kcate = $i;
                                    unset($catch_cal);
                                    $catch_cal = $cla[$i];
                                }
                            }
                        }
                        $sql = "SELECT id FROM tag WHERE tag_name ='" . $cla[$kcate][0] . "' and type_id = 3";
                        $calname = $this->pgsqldb->doSql($sql);
                        if (!empty($calname[0]['id'])) {
                            $rsv[$k]['levelone'] = $calname[0]['id'];
                        }

                        $rsv[$k]['category'] = preg_replace("/[\']+/", "''", implode(" > ", $cla[$kcate]));
                        unset($calname, $kcate);
                    }
                    //判断商品是否存在
                    $nosql = "SELECT * FROM goods where asin = '" . $rsv[$k]["asin"] . "'";
                    $asin[$k] = $this->pgsqldb->doSql($nosql);
                    if (empty($asin[$k])) {
                        if (isset($rsv[$k]["original_price"])) {
                            //新增时间
                            $rsv[$k]["create_time"] = time();
                            //更新时间
                            $rsv[$k]['turnover_time'] = time();
                            //新增sql
                            $sql = 'insert into goods ("' . implode('","', array_keys($rsv[$k])) . '"' . ") values('" . implode("','", array_values($rsv[$k])) . "')";

                            $this->pgsqldb->doSql($sql);
                            //获取当前数据id
                            $asin[$k] = $this->pgsqldb->doSql($nosql);
                            $id[$k] = $asin[$k][0]['id'];
                            $tag[$id[$k]]["category"] = $rsv[$k]['category'];
                            $params['stringId'] = $rsv[$k]["string_id"];
                            echo " #promopureID:" . $rsv[$k]["string_id"];
                            echo " #Goods入库成功ID:" . $id[$k] . " ";
                        } else {
                            $no_price = 1;
                            if ($fetch == 1) {
                                $fetchurl = '价格信息缺失';
                                $sql = "update fetch_goods set promopure_url = '" . $fetchurl . "',status = '-2' where id = " . $fetch_goods_id;
                                $return = $this->pgsqldb->doSql($sql);
                                echo " # " . $fetch_goods_id . "url抓取失败，价格信息缺失";
                                return;
                            }
                        }
                    } else {
                        //判断是否有更新内容
                        if ($rsv[$k]["title"] == $asin[$k][0]['title'] && $rsv[$k]['source'] == $asin[$k][0]['source'] && $rsv[$k]['asin'] == $asin[$k][0]['asin'] && $rsv[$k]["desc"] == $asin[$k][0]['desc'] && $rsv[$k]["star"] == $asin[$k][0]['star'] && $rsv[$k]["star_num"] == $asin[$k][0]['star_num'] && $rsv[$k]["original_price"] == $asin[$k][0]['original_price'] && $rsv[$k]["price"] == $asin[$k][0]['price'] && $rsv[$k]["is_sets"] == $asin[$k][0]['is_sets'] && $rsv[$k]["refer"] == $asin[$k][0]["refer"] && $rsv[$k]["brand"] == $asin[$k][0]['brand']) {

                        } else {
                            //更新时间
                            $rsv[$k]['turnover_time'] = time();
                        }
                        $rsv[$k]["update_time"] = time();
                        //获取当前数据id
                        $id[$k] = $asin[$k][0]['id'];
                        if (!empty($asin[$k][0]['category'])) {
                            $tag[$id[$k]]["category"] = $asin[$k][0]['category'];
                            unset($rsv[$k]['category'], $rsv[$k]['levelone']);
                        } else {
                            if (!empty($rsv[$k]['category'])) {
                                $tag[$id[$k]]["category"] = $rsv[$k]['category'];
                            }
                        }
                        //更新数据
                        $valArr = array();
                        $category_no[$k] = 0;
                        unset($rsv[$k]["string_id"], $rsv[$k]["create_time"]);//,$rsv[$k]['category']
                        foreach ($rsv[$k] as $kval => $valA) {
                            $valArr[] = '"' . $kval . '"=' . "'" . $valA . "'";
                        }
                        $valStr = implode(',', $valArr);
                        //更新sql
                        $sql = "update goods set " . $valStr . " where id =" . $asin[$k][0]['id'];
                        $return = $this->pgsqldb->doSql($sql);
                        $params['stringId'] = $asin[$k][0]["string_id"];

                        echo " #promopureID:" . $asin[$k][0]["string_id"];
                        echo " #Goods更新成功ID:" . $id[$k] . " ";
                        if ($fetch == 1) {
                            $fetchurl = "https://promopure.com/" . $v["friendly_url_part"] . "/p/" . $params['stringId'];
                            $sql = "update fetch_goods set promopure_url = '" . $fetchurl . "',status = 55 where id = " . $fetch_goods_id;
                            $return = $this->pgsqldb->doSql($sql);
                            echo " # " . $fetch_goods_id . "url更新成功";
                        }
                    }
                    if (!isset($no_price)) {
                        if ($v['refer_id'] == $fetch_refer_id) {
                            $fetchurl = "https://promopure.com/" . $v["friendly_url_part"] . "/p/" . $params['stringId'];
                        }
                        //图片转码
//                    $n1 = microtime(true);
//                    $n2 = microtime(true);
//                    echo " 时间：".$n2-$n1."; ";
                        //判断数据是否抓取图片 0；已抓取
                        if ($v['img_over'] > -1) {

                            if (isset($color_img)) {
                                $imgurl['addenda_img'] = $color_img;
                                unset($color_img);
                            } else {
                                unset($imgurl['addenda_img']);
                            }
                            $params['url'] = $imgurl;
                            if (!empty($imgurl)) {
                                $this->imgdown($params, $v['id'], $v['img_over'], strtolower($source[$k]), $asin[$k][0]['image_url']);

                            }
                        } else {
                            echo " #图片已存在 " . $v['img_over'];
                        }
                        //是否加入deals
                        // if ($v['star'] > 4 && $v['review_total'] > 500) {
                        // $redis = redisfunction();
                        // $redis->rpush("amazon_dealsQueue", $v['refer_id']);
                        // }
                        unset($imgurl);
                        //储存friendlyurl
                        $this->friendlyUrl($id[$k], $v["friendly_url_part"], $rsv[$k]["title"], "");
//                    //tag入库信息
                        $tag[$id[$k]]["source"] = $source[$k];
                        $tag[$id[$k]]["brand"] = $this->str($v['brand']);

                        //获取选项卡
                        $attribute = json_decode($v['attribute'], true);
                        $this->newtype($id[$k], $attribute);


                        echo "ID: " . $v['id'];
                        $res[$id[$k]]['id'] = $id[$k];
                        if (isset($rsv[$k]['price'])) {
                            $res[$id[$k]]['price'] = $rsv[$k]['price'];
                        } else {
                            $res[$id[$k]]['price'] = '';
                        }
                        if (isset($rsv[$k]["original_price"])) {
                            $res[$id[$k]]['original_price'] = $rsv[$k]["original_price"];
                        } else {
                            $res[$id[$k]]['original_price'] = '';
                        }
                        if (isset($rsv[$k]['save'])) {
                            $res[$id[$k]]['save'] = $rsv[$k]['save'];
                        } else {
                            $res[$id[$k]]['save'] = '';
                        }
                        $res[$id[$k]]['status'] = $rsv[$k]['status'];
                        $res[$id[$k]]['free_shipping'] = $v['free_shipping'];
                        $res[$id[$k]]['is_today_deals'] = $v['is_today_deals'];
                        if ($v['is_today_deals'] == 1) {
                            $sql = 'update new_spider set is_today_deals = 0';
                            $return = $this->mysqldb->doSql($sql);
                        }
                        $this->tag($res[$id[$k]]);
                        $this->tags($tag[$id[$k]], $id[$k], $v['id']);
                        echo "\n";
                    } else {
                        unset($no_price);
                        echo " #无价格数据，不新增";
                        echo "\n";
                    }


                } else {
                    if ($fetch == 1) {
                        $fetchurl = '图片信息为空';
                        $sql = "update fetch_goods set promopure_url = '" . $fetchurl . "',status = '-2' where id = " . $fetch_goods_id;
                        $return = $this->pgsqldb->doSql($sql);
                        echo " # " . $fetch_goods_id . "url抓取失败，图片信息为空";
                        return;
                    }
                }
//            $rsv[$k]["comment"] = str($v['comment']);

            }

        }
        if (isset($fetch)) {
            if ($fetch == 1) {
                $sql = "update fetch_goods set promopure_url = '" . $fetchurl . "',status = 5 where id = " . $fetch_goods_id;
                $return = $this->pgsqldb->doSql($sql);
                echo " # " . $fetch_goods_id . "url抓取成功";
            } elseif ($fetch == 2) {
                print_r($fetch_refer_id);
                $sql = "select count(*) as count from top100 where top_tag = " . $fetch_refer_id['top_tag'];
                $count = $this->pgsqldb->doSql($sql);
                //TODO 查询pathid
//            $sql = "select * from path where ".'"desc"= '." '".$fetch_refer_id['category']."'";
                $sql = "select * from path_expression where " . '"name"= ' . " '" . $fetch_refer_id['category'] . "'";
                $path_desc = $this->pgsqldb->doSql($sql);
                //
                echo "\n";
                echo $count[0]['count'];
                if ($count[0]['count'] > 0) {
                    $sql = "update top100 set status = 1 where top_tag = " . $fetch_refer_id['top_tag'];
                    $this->pgsqldb->doSql($sql);
                    foreach ($fetch_refer_id['refer_ids'] as $k => $v) {
                        if ($fetch_refer_id['web_refer'] == "amazon") {
                            $url = "https://www.amazon.com/dp/" . $v;
                        }
                        $num[$k] = $k + 1;
                        $sql = "update top100 set " . '"' . "defaultAsin" . '"' . " ='" . $v . "',url='" . $url . "',update_time= " . time() . ",category='" . $fetch_refer_id['category'] . "',path_id = '" . $path_desc[0]['string_id'] . "',status = 0 where top_tag = " . $fetch_refer_id['top_tag'] . " and sort =" . $num[$k];
                        $this->pgsqldb->doSql($sql);
                    }
                    echo " #更新完成top100";
                } else {
                    foreach ($fetch_refer_id['refer_ids'] as $k => $v) {

                    }
                }
            }
        }
        echo "\n\n";
    }

    /**
     *
     *
     *
     */

    public function newtype($gid, $attribute)
    {
        $sql = "delete from tag_goods_relevancy where status = 0 and goods_id = " . $gid;
        $this->pgsqldb->doSql($sql);
        $sql = "select * from tag where id in (select tag_id from tag_goods_relevancy where goods_id = " . $gid . " AND status = 1)";
        $reles = $this->pgsqldb->doSql($sql);
        if (!empty($reles)) {
            foreach ($reles as $k => $v) {
                if (in_array($v['type_id'], array(7, 8, 9))) {
                    $sql = "update tag_goods_relevancy set status = 0 where goods_id = " . $gid . " and tag_id = " . $v['id'];
                    $this->pgsqldb->doSql($sql);
                }
            }
        }
        $sql = "delete from tag_goods_relevancy where status = 0 and goods_id = " . $gid;
        $this->pgsqldb->doSql($sql);
        if (!empty($attribute)) {
            foreach ($attribute as $k => $v) {
                $v = preg_replace("/[\']+/", "''", $v);
                $sql = "select * from type where type_name = '" . strtolower($k) . "'";
                $res = $this->pgsqldb->doSql($sql);
                if (strtolower($k) == "color") {
                    $this->color($gid, strtolower($this->str($v)));
                }
                if ($res) {
                    $type_id = $res[0]['id'];
                } else {
                    $sql = "INSERT INTO type (type_name," . '"desc"' . ",create_time,update_time,p_id) VALUES ('" . strtolower($k) . "','" . $k . "'," . time() . "," . time() . ",1) RETURNING id;";
                    $id = $this->pgsqldb->doSql($sql);
                    $type_id = $id[0]['id'];
                }
                $sql = "select id from tag where tag_name = '" . strtolower($this->str($v)) . "'and type_id =" . $type_id;
                $a = $this->pgsqldb->doSql($sql);
                if (empty($a)) {
                    $num = strlen($type_id);
                    do {
                        if ($num == 2) {
                            $stringId = 'T0' . $type_id . $this->randString('6');
                        } else {
                            $stringId = 'T0' . $type_id . $this->randString('7');
                        }
                        $sql = "SELECT id FROM tag WHERE string_id = '" . $stringId . "'";
                        $result = $this->pgsqldb->doSql($sql);
                        if (!$result) {
                            $string_id = $stringId;
                        }
                    } while ($result);

                    $insert = "('" . strtolower($this->str($v)) . "','" . $v . "','" . $string_id . "'," . $type_id . "," . time() . "," . time() . ")";
                    $in_sql = "INSERT INTO tag (tag_name," . '"desc"' . ",string_id,type_id,create_time,update_time) VALUES " . $insert . " RETURNING id";
                    $as = $this->pgsqldb->doSql($in_sql);
//                $as = $this->pgsqldb->doSql($sqla);
                    $tagid = $as[0]['id'];
                } else {
                    echo " #属性tag 已存在 ";
                    $tagid = $a[0]['id'];
                }

                echo " #属性TAGID:" . $tagid . " & ";
                $tgsql = "select id from tag_goods_relevancy where tag_id =" . $tagid . " and goods_id = " . $gid . " AND status = 1";
                $b = $this->pgsqldb->doSql($tgsql);
                if (empty($b)) {
                    $inserts = "(" . $gid . "," . $tagid . "," . time() . "," . time() . ")";
                    $in_sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $inserts . "RETURNING id";
                    $releid = $this->pgsqldb->doSql($in_sql);
                    echo " #属性TAG关系入库成功 ";
                } else {
                    echo " #" . $b[0]['id'] . " 属性TAG关系已存在 ";
                }
            }
        }
    }

    /**
     * 往商品tag 表中打上 推荐tag
     */
    public function color($gid, $tag, $type_id = 0)
    {

        $colsql = "select id,tag_name from tag where type_id = 13 ";
        $cole = $this->pgsqldb->doSql($colsql);

        foreach ($cole as $k => $v) {
            $nos = strpos(strtolower($tag), $v['tag_name']);
            if ($nos != false) {
                $godssql = "select goods_id from tag_goods_relevancy where goods_id = " . $gid . " AND tag_id = " . $v['id'] . " AND status = 1";
                $godsno = $this->pgsqldb->doSql($godssql);
                if (empty($godsno)) {
                    $NUM = "(" . $gid . "," . $v['id'] . "," . time() . "," . time() . ")";
                    $nsql = "insert into tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $NUM;
//                        print_r($nsql);
                    $god = $this->pgsqldb->doSql($nsql);
                    $upsql = "UPDATE goods SET color_id = " . $v['id'] . " where id= " . $gid;
//                        print_r($upsql);
                    $ups = $this->pgsqldb->doSql($upsql);
                    echo $gid . " #COLOR成功 ";
                } else {
                    echo " #COLOR存在 ";
                }
                echo "\n";
            }
        }
    }

    /**
     * 往商品tag 表中打上 推荐tag
     */
    public function imgColor($gid, $tag, $type_id = 0)
    {

        $colsql = "select id,tag_name from tag where type_id = 13 ";
        $cole = $this->pgsqldb->doSql($colsql);
        foreach ($cole as $k => $v) {
            $nos = strpos(strtolower($tag), $v['tag_name']);
            if ($nos !== false) {
                $godssql = "select goods_id from tag_goods_relevancy where goods_id = " . $gid . " AND tag_id = " . $v['id'] . " AND status = 1";
                $godsno = $this->pgsqldb->doSql($godssql);
                if (empty($godsno)) {
//                $NUM = "(".$gid.",".$v['id'].",".time().",".time().")";
//                $nsql = "insert into tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES ".$NUM;
//                $god = $this->pgsqldb->doSql($nsql);
//                return $v['id'];
                } else {
//               return false;
                }
                return $v['id'];
            }
        }
    }

    /**
     * 获取本站详情的URL
     * @param $title $dealtitle $friendly_url_part
     * @param $id
     * @return string
     */
    public function friendlyUrl($id, $url, $title, $dealtitle)
    {
        if (empty($url)) {
            if (!empty($title)) {
                $title = $this->getGoodsUrl($title, $id);
            } elseif (!empty($dealtitle)) {
                $title = $this->getGoodsUrl($dealtitle, $id);
            }
        } else {
            $title = $url;
        }
        $title = urlencode($title);
        //查询是否已存在friendlyurl
        $sql = "SELECT friendly_url_part FROM detail_page_rule WHERE goods_id = " . $id;
        $result = $this->pgsqldb->doSql($sql);
        if (empty($result)) {
            $insert_sql = "INSERT INTO detail_page_rule (goods_id,friendly_url_part) VALUES (" . $id . ",'" . $title . "')";
            $result = $this->pgsqldb->doSql($insert_sql);
            echo " #FRIENDLYURL存储成功 ";
        } else {
            $insert_sql = "UPDATE detail_page_rule SET " . '"friendly_url_part"' . " = '" . $title . "' WHERE goods_id = " . $id;
            $result = $this->pgsqldb->doSql($insert_sql);
            echo " #FRIENDLYURL更新成功 ";
//        echo " #FRIENDLYURL已存在 ";
        }
    }

    /**
     * 获取本站详情的URL
     * @param $title
     * @param $id
     * @return string
     */
    public function getGoodsUrl($title, $id)
    {
        // 标题, 首字母大写, 单词用-连接.
        $title = preg_replace("/[\'\‘\’]+/", "", $title);
        $title = preg_replace("/[\s\`\~\!\@\#\$\%\^\&\*\(\)\_\-\=\+\<\>\?\;\:\"\{\}\,\.\\/\'\[\]\|]+/", "-", $title);
        $title = rtrim($title, '-');
        return $title;
    }

    public function tagsd($gid, $tag, $typeid)
    {
        if (!empty($tag)) {
            $sqla = "select id from tag where tag_name = '" . strtolower(str($tag)) . "'and type_id =" . $typeid;
//    print_r($sqla);
            $a = $this->pgsqldb->doSql($sqla);
            if (empty($a)) {
                do {
                    $stringId = 'T04' . $this->randString('7');
                    $sql = "SELECT id FROM tag WHERE string_id = '" . $stringId . "'";
                    $result = $this->pgsqldb->doSql($sql);
                    if (!$result) {
                        $string_id = $stringId;
                    }
                } while ($result);

                $insert = "('" . strtolower($this->str($tag)) . "','" . $tag . "','" . $string_id . "',4," . time() . "," . time() . ")";

                $in_sql = "INSERT INTO tag (tag_name," . '"desc"' . ",string_id,type_id,create_time,update_time) VALUES " . $insert;
                $this->pgsqldb->doSql($in_sql);
                $as = $this->pgsqldb->doSql($sqla);
                $tagid = $as[0]['id'];
            } else {
                $tagid = $a[0]['id'];
            }
            echo " #TAGID:" . $tagid . " &";
            $tgsql = "select id from tag_goods_relevancy where tag_id =" . $tagid . " and goods_id = " . $gid . " AND status = 1";
//    print_r($tgsql);
            $b = $this->pgsqldb->doSql($tgsql);
            if (empty($b)) {
                $inserts = "(" . $gid . "," . $tagid . "," . time() . "," . time() . ")";
                $in_sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $inserts;
                $this->pgsqldb->doSql($in_sql);
                echo " 厂商关系入库成功 ";
            } else {
                echo " 厂商关系已存在 ";
            }
        }
    }

    public function best($data)
    {
        if ($data == "No_Element") {
            return "";
        } elseif ($data == "") {
            return "";
        } else {
            $a = explode("%", $data);
            $as = explode(" ", $a[0]);
            return $as[2];
        }
    }

    public function types($gid, $tag, $typeid)
    {
        if (!empty($tag)) {
            $sqla = "select id from tag where tag_name = '" . strtolower($this->str($tag)) . "'and type_id =" . $typeid;
            $a = $this->pgsqldb->doSql($sqla);
            if (empty($a)) {

                do {
                    $stringId = 'T0' . $typeid . $this->randString('7');
                    $sql = "SELECT id FROM tag WHERE string_id = '" . $stringId . "'";
                    $result = $this->pgsqldb->doSql($sql);
                    if (!$result) {
                        $string_id = $stringId;
                    }
                } while ($result);

                $insert = "('" . strtolower($this->str($tag)) . "','" . $tag . "','" . $string_id . "'," . $typeid . "," . time() . "," . time() . ")";
                $in_sql = "INSERT INTO tag (tag_name," . '"desc"' . ",string_id,type_id,create_time,update_time) VALUES " . $insert;
                $this->pgsqldb->doSql($in_sql);
                $as = $this->pgsqldb->doSql($sqla);
                $tagid = $as[0]['id'];
            } else {
                echo " #tag 已存在 ";
                $tagid = $a[0]['id'];
            }
            echo " #TAGID:" . $tagid . " & ";
            $tgsql = "select id from tag_goods_relevancy where tag_id =" . $tagid . " and goods_id = " . $gid . " AND status = 1";
//    print_r($tgsql);
            $b = $this->pgsqldb->doSql($tgsql);
            if (empty($b)) {
                $inserts = "(" . $gid . "," . $tagid . "," . time() . "," . time() . ")";
                $in_sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $inserts;
                $this->pgsqldb->doSql($in_sql);
                echo " #TAG关系入库成功 ";
            } else {
                echo " #TAG关系已存在 ";
            }
        }
    }

    /*
     * tag逻辑
     */
    public function tags($tag, $id)
    {
        $tag_s[$id] = $tag;
        foreach ($tag_s as $k => $v) {
            echo "\n#商品ID:" . $k . " ";
            while ($key = key($v)) {
                $name = $v[$key];
                //判断tag类型
                switch ($key) {
                    case "source":
                        if (!empty(trim($name))) {
                            $data['tag_name'] = trim($name);
                            $data['desc'] = "source";
                            $data['type_id'] = 1;
                            $this->types($k, $data['tag_name'], $data['type_id']);
                            unset($data);
                        }
                        break;

                    case "category":
                        $goods_config = [
                            'categoryFile' => [
                                'Kindle eBooks' => 'Books & Magazines > Kindle Store > Kindle eBooks',
                                'Books' => 'Books & Magazines',
                                'Movies & TV' => 'Movies, Music & Games > Movies & TV',
                            ],
                        ];
                        $name = preg_replace("/[\']+/", "''", $name);
                        var_dump($name);
                        $sql = "SELECT id,after FROM tag_filter_path WHERE befor ='" . trim($name) . "'";
                        $after = $this->pgsqldb->doSql($sql);
                        if (empty($after[0]['after'])) {
                            foreach ($goods_config['categoryFile'] as $categoryk => $categoryv) {
                                if (strpos($name, $categoryk)) {
                                    $after[0]['after'] = $categoryv;
                                    break;
                                }
                            }
                        }
                        var_dump($after[0]['after']);
                        //判断是否有对应关系
                        if (!empty($after[0]['after'])) {
                            $desc[$k] = $after[0]['after'];
                            $sqls = "SELECT id FROM path_expression WHERE " . '"name"' . " ='" . trim(preg_replace("/[\']+/", "''", $desc[$k])) . "' and type = 1";
                            $path_id = $this->pgsqldb->doSql($sqls);
                            if (!empty($path_id)) {
                                $upd_sql = "UPDATE goods SET path_id = " . $path_id[0]['id'] . " WHERE id = " . $k;
                                $this->pgsqldb->doSql($upd_sql);
                                echo " #path_id更新成功#" . $path_id[0]['id'] . " ";
                                unset($path_id);
                            }
                        } else {
                            //没有对应关系插入空
                            if (empty($after)) {
                                $sql = "INSERT INTO tag_filter_path (befor,refer) VALUES ('" . $name . "','amazon')";
                                $this->pgsqldb->doSql($sql);
                            }
                        }
                        unset($after);
                        //判断promopure分类
                        if (!empty($desc[$k])) {
                            //分类tag生成
                            $desc_category[$k] = explode(">", $desc[$k]);
                            foreach ($desc_category[$k] as $k_desc => $v_desc) {
                                $sql = "select * from tag where tag_name = '" . trim(preg_replace("/[\']+/", "''", trim($v_desc))) . "'";
                                $tag_id[$k_desc] = $this->pgsqldb->doSql($sql);
                                if (!empty($tag_id[$k_desc])) {
                                    echo " #分类tag存在 ";
                                } else {
                                    echo " #分类tag不存在 ";
                                    do {
                                        $stringId = 'T03' . $this->randString('7');
                                        $sql = "SELECT id FROM tag WHERE string_id = '" . $stringId . "'";
                                        $result = $this->pgsqldb->doSql($sql);
                                        if (!$result) {
                                            $string_id = $stringId;
                                        }
                                    } while ($result);
                                    $sql = 'insert into tag (tag_name,string_id,"desc",type_id,create_time,update_time) VALUES (' . "'" . preg_replace("/[\']+/", "''", trim($v_desc)) . "','" . $string_id . "','category',3," . time() . "," . time() . ")";
                                    $result = $this->pgsqldb->doSql($sql);
                                    echo " #分类tag生成成功 ";
                                    $sql = "select * from tag where tag_name = '" . trim($v_desc) . "'";
                                    $tag_id[$k_desc] = $this->pgsqldb->doSql($sql);
                                }

                                $sql = "select count(*) as count from tag_goods_relevancy where goods_id= " . $k . " and tag_id =" . $tag_id[$k_desc][0]['id'] . " and status = 1";
                                $relevancy[$k_desc] = $this->pgsqldb->doSql($sql);
                                if ($relevancy[$k_desc][0]['count'] == 0) {
                                    $sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES (" . $k . "," . $tag_id[$k_desc][0]['id'] . "," . time() . "," . time() . ")";
                                    $this->pgsqldb->doSql($sql);
                                    echo " #分类关系新增 ";
                                } else {
                                    echo " #分类关系已存在 ";
                                }

                            }
                            unset($tag_id, $relevancy, $desc[$k]);
                        }
                        unset($name);
                        break;
                    case "brand":
                        if (!empty(trim($name))) {
                            $data['tag_name'] = trim($name);
                            $data['desc'] = "brand";
                            $data['type_id'] = 4;
                            if (!empty($data['tag_name'])) {
                                $this->types($k, $data['tag_name'], $data['type_id']);
                            }
                            unset($data);
                        }
                        break;
                }
                next($v);
            }

            if (!empty($keyword[$k])) {
//处理分类关键字
                foreach ($keyword[$k] as $vel) {
                    $name = $vel['tag_name'];
                    if (!empty(trim($name))) {
                        $data['tag_name'] = trim($name);
                        $data['desc'] = "keywords";
                        $data['type_id'] = 6;
                        if (!empty($data['tag_name'])) {
                            $this->types($k, $data['tag_name'], $data['type_id']);
                        }
                        unset($data);
                    }
                }
            }
        }
        //tag_sets判断入库更新
        $sets_sql = "SELECT tag_id FROM tag_goods_relevancy WHERE goods_id = " . $id . " AND status = 1";
        $sets_Tag = $this->pgsqldb->doSql($sets_sql);
        foreach ($sets_Tag as $ktag => $vtag) {
            $tag_set[] = $vtag['tag_id'];
        }
        sort($tag_set);
        $tag_sets = "{" . implode(",", $tag_set) . "}";
        if (!empty($tag_sets)) {
            $sets_update = "UPDATE goods SET tag_sets = '" . $tag_sets . "' WHERE id =" . $id;
            $this->pgsqldb->doSql($sets_update);
            echo " #tag_sets更新成功 ";
        }
        echo "\n\n";
    }

    /*
     * 字符转义
     */
    public function str($data)
    {
        if ($data == '"No_Element"') {
            $data = "";
        } elseif ($data == 'There are no customer reviews yet.') {
            $data = "";
        }
        return $data;
    }

    /*
     * 价格格式筛选器
     */
    public function price($data, $n)
    {
        if ($n == 1) {
            //匹配千位以上
            preg_match('/\d{1,10}\,\d{1,3}\.\d{1,2}/', $data, $res);
            if (!empty($res[0])) {
                //去除千位以上的逗号
                return str_replace(array(","), "", $res[0]);
            } else {
                preg_match('/\d{1,10}\.\d{1,2}/', $data, $a);
                if (!empty($a[0])) {
                    return $a[0];
                } else {
                    return 0.00;
                }
            }

        } elseif ($n == 2) {
            preg_match('/\d{1,2}\%/', $data, $a);
            if (!empty($a[0])) {
                preg_match('/\d{1,2}/', $a[0], $as);
                return $as[0];
            } else {
                return 0.00;
            }
        } else {
            preg_match('/\d{1,1}.\d{1,1}/', $data, $a);
        }
        if (!empty($a[0])) {
            return $a[0];
        } else {
            return $data;
        }
    }

    /*
     * 处理供应商
     */
    public function ship($data)
    {
        if ($data != "No_Element") {
            $str = strstr($data, "Ships from and sold by Amazon.com");
            if ($str) {
                $return["sold"] = "Amazion";
                $return["ship"] = "Amazion";
            } else {
                $rs = str_replace(strstr($data, "and"), "", $data);
                $sold = str_replace("Sold by ", "", $rs);
                $re = strstr($data, " Fulfilled by Amazon");
                if ($re) {
                    $return["sold"] = $sold;
                    $return["ship"] = "Amazion";
                } else {
                    $nz = strstr($data, "Ships from and sold by ");
                    if ($nz) {
                        $sold = str_replace("Ships from and sold by ", "", $nz);
                        $return["sold"] = $sold;
                        $return["ship"] = $sold;
                    } else {
                        $return = false;
                    }
                }
            }
        } else {
            $return = false;
        }
        return $return;
    }

    public function tag($data)
    {
        $goodsId = $data['id'];
        $price = $data['price'];
        $originalPprice = $data['original_price'];
        $save = $data['save'];
        $status = $data['status'];
        $is_free = $data['free_shipping'];
        $is_today_deals = $data['is_today_deals'];
//    $bestSelling = $data['bestSelling'];
        $clean_sql = "update tag_goods_relevancy set status = 0 where tag_id in (0,163,164,165,166,167,168,169,170,171,172,173,174) AND goods_id = " . $goodsId;
        $this->pgsqldb->doSql($clean_sql);
//    if ($bestSelling > 80) {
//        $arr[] = 194;
//    }
        if ($price > 0) {
            if (0 < $price && $price < 25) {
                $arr[] = 163;
            } elseif (25 < $price && $price < 50) {
                $arr[] = 164;
            } elseif (50 < $price && $price < 100) {
                $arr[] = 165;
            } elseif (100 < $price && $price < 200) {
                $arr[] = 166;
            } elseif (200 < $price && $price < 500) {
                $arr[] = 167;
            } elseif (500 < $price) {
                $arr[] = 168;
            }
        } else {
            if (0 < $originalPprice && $originalPprice < 25) {
                $arr[] = 163;
            } elseif (25 < $originalPprice && $originalPprice < 50) {
                $arr[] = 164;
            } elseif (50 < $originalPprice && $originalPprice < 100) {
                $arr[] = 165;
            } elseif (100 < $originalPprice && $originalPprice < 200) {
                $arr[] = 166;
            } elseif (200 < $originalPprice && $originalPprice < 500) {
                $arr[] = 167;
            } elseif (500 < $originalPprice) {
                $arr[] = 168;
            }
        }


        if (70 < $save) {
            $arr[] = 173;
            $arr[] = 172;
            $arr[] = 171;
            $arr[] = 170;
        } elseif (50 < $save) {
            $arr[] = 172;
            $arr[] = 171;
            $arr[] = 170;
        } elseif (25 < $save) {
            $arr[] = 171;
            $arr[] = 170;
        } elseif (10 < $save) {
            $arr[] = 170;
        }
        if ($status == 1) {
            $arr[] = 169;
        }
        if ($is_free == 1) {
            $arr[] = 174;
        }
        if ($is_today_deals == 1) {
            $arr[] = 0;
        }
        if (isset($arr) || !empty($arr)) {
            $where_in = implode(",", $arr);
            $sql = "SELECT tag_id FROM tag_goods_relevancy WHERE goods_id = " . $goodsId . " AND tag_id in (" . $where_in . ") AND status = 1";
            $ids = $this->pgsqldb->doSql($sql);
            if (empty($ids)) {
                foreach ($arr as $val) {
                    $str[] = "(" . $goodsId . "," . $val . "," . time() . "," . time() . ")";
                }
                $arr_str = implode(",", $str);
                $arr_sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $arr_str;
                $a = $this->pgsqldb->doSql($arr_sql);
                echo " #新增关系成功#1";
            } else {
                foreach ($ids as $v) {
                    $arrs[] = $v['tag_id'];
                }
                $diff = array_diff($arr, $arrs);
                if (empty($diff)) {
                    echo " #关系已存在#1";
                } else {
                    foreach ($diff as $val) {
                        $str[] = "(" . $goodsId . "," . $val . "," . time() . "," . time() . ")";
                    }
                    $arr_str = implode(",", $str);
                    $arr_sql = "INSERT INTO tag_goods_relevancy (goods_id,tag_id,create_time,update_time) VALUES " . $arr_str;
                    $a = $this->pgsqldb->doSql($arr_sql);
                    echo " #增加关系成功#2";
                }
            }
        }
    }

    /**
     * 生成随机字符串
     *
     * @access public
     * @param integer $length 字符串长度
     * @param string $specialChars 是否有特殊字符
     * @return string
     */
    public function randString($length, $specialChars = false)
    {
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
        if ($specialChars) {
            $chars .= '!@#$%^&*()';
        }

        $result = '';
        $max = strlen($chars) - 1;
        for ($i = 0; $i < $length; $i++) {
            $result .= $chars[rand(0, $max)];
        }
        return $result;
    }

    /*
     * 图片下载
     */
    public function imgdown($params, $am_id, $imgstatus, $source, $oldurl = '')
    {

        echo " #图片开始抓取 ";
        $n1 = time();
        $secret = 'promopure.com';
        if (!empty($oldurl)) {
            if ($imgstatus == 0) {
                unset($params['url']['normal_img'], $params['url']['primary_img']);
                if (!empty($params['url']['addenda_img'])) {
                    if (!empty($oldurl)) {
                        $oldurlimg = json_decode($oldurl, true);
                        if (!empty($oldurlimg['addenda_img'])) {
                            foreach ($params['url']['addenda_img'] as $k => $v) {
                                foreach ($oldurlimg['addenda_img'] as $ok => $ov) {
                                    if (strstr($ov, "t" . $k)) {
                                        unset($params['url']['addenda_img'][$k]);
                                    }
                                }
                            }
                        }

                    }
                    if (empty($params['url']['addenda_img'])) {
                        $params['url'] = '';
                    }
                }

            }
        }
        if (!empty($params['url']['normal_img'])) {
            $img_url = json_encode($params['url']);
            $stringId = $params['stringId'];
            $webRefer = $source;

            $config = [
                'resize' => array(
                    'title' => array(50, 200),
                ),
                'imagePath' => '/home/spider/spider/instance/script/image',
                'imagePathAlias' => array(
                    'amazon' => 'a',
                )
            ];
            $goods_img_url = $this->downloadImage($config, $img_url, $stringId, $webRefer);
            if (!empty($oldurl)) {
                $oldurlimg = json_decode($oldurl, true);
                foreach ($goods_img_url['addenda_img'] as $gk => $gv) {
                    $oldurlimg['addenda_img'][] = $gv;
                }
                unset($goods_img_url);
                $goods_img_url = $oldurlimg;
            }

            if ($goods_img_url == false) {
                $status['error'] = 2;
            } else {
                $status['error'] = 0;
                $img = json_encode($goods_img_url);
            }
            $updateImg = $this->get_bandle_picture($config['imagePath'] . "/", $goods_img_url);
            $sql = "UPDATE new_spider SET img_over = " . $status['error'] . " WHERE id = " . $am_id;
            $this->mysqldb->doSql($sql);
            if ($updateImg['code'] == 200) {
                if (isset($img) && !empty($img)) {
                    $sqls = "UPDATE goods SET image_url = '" . $img . "' WHERE string_id = '" . $params['stringId'] . "'";
                    $this->mysqldb->doSql($sqls);
                    echo " #图片存储成功 ";
                } else {
                    echo " #图片存储失败 ";
                }
            } else {
                echo " #" . $updateImg['msg'];
            }
        } else {
            echo " #图片无更新变化 ";
        }
    }

    /**
     * 模拟提交参数，支持https提交 可用于各类api请求
     * @param string $url ： 提交的地址
     * @param array $data :POST数组
     * @param string $method : POST/GET，默认GET方式
     * @return mixed
     */
    public function http($url, $data = '', $method = 'GET')
    {
        $curl = curl_init(); // 启动一个CURL会话
        curl_setopt($curl, CURLOPT_URL, $url); // 要访问的地址
        curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查
        curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($curl, CURLOPT_USERAGENT, 'Mozilla/4.5 [en] (X11; U; Linux 2.2.9 i586).'); // 模拟用户使用的浏览器
        curl_setopt($curl, CURLOPT_FOLLOWLOCATION, 1); // 使用自动跳转
        curl_setopt($curl, CURLOPT_AUTOREFERER, 1); // 自动设置Referer
        if ($method == 'POST') {
            curl_setopt($curl, CURLOPT_POST, 1); // 发送一个常规的Post请求
            if ($data != '') {
                curl_setopt($curl, CURLOPT_POSTFIELDS, $data); // Post提交的数据包
            }
        }
        curl_setopt($curl, CURLOPT_TIMEOUT, 30); // 设置超时限制防止死循环
        curl_setopt($curl, CURLOPT_HEADER, 0); // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1); // 获取的信息以文件流的形式返回
        $tmpInfo = curl_exec($curl); // 执行操作
        curl_close($curl); // 关闭CURL会话
        return $tmpInfo; // 返回数据
    }

    public function downloadImage($config, $img_url, $stringId, $webRefer)
    {


        // get image resize rule
        $params_json = json_encode([$config, $img_url, $stringId, $webRefer]);

        $resizeRule = $config['resize'];
        // 获取参数
        $params['url'] = json_decode($img_url, true);
        // print_r($params['url']);
        $params['stringId'] = $stringId;
        $params['webRefer'] = $webRefer;
        if (!$params['url'] || !$params['stringId'] || !$params['webRefer']) {
            file_put_contents('./log/download_img.log', "params_error: " . $params_json . "\n", FILE_APPEND | LOCK_EX);
            echo "params error. \n";
            return false;//die;
        }
        // 获取保存图片的的路径
        $path = $config['imagePath'] . '/' . $config['imagePathAlias'][$params['webRefer']];
        $asinMd5 = md5($params['stringId']);
        $path_1 = substr($asinMd5, 0, 1);
        $path_2 = substr($asinMd5, 1, 1);
        $imgWebPath = $config['imagePathAlias'][$params['webRefer']] . '/' . $path_1 . '/' . $path_2;
        $urlPath = $path . '/' . $path_1 . '/' . $path_2;
        if (!$urlPath) {
            file_put_contents('./log/download_img.log', "get image save path error \n", FILE_APPEND | LOCK_EX);
            echo "get image save path error \n";
//        die;
            return false;
        }
        // 获取文件名
        $fileNameBase = md5(md5($params['stringId']));
        $imageUrls = array();
        // 下载图片.
        foreach ($params['url'] as $k => $v) {
            if ($v == false) {
                continue;
            }
            foreach ($v as $key => $url) {
                if (strstr($url, 'jpg')) {
                    $postfix = 'jpg';
                } elseif (strstr($url, 'png')) {
                    $postfix = 'png';
                }
                if ($k == 'primary_img') {
                    $fileName = $fileNameBase . '_title_primary.' . $postfix;
                } elseif ($k == 'normal_img') {
                    $fileName = $fileNameBase . '_title_' . $key . '.' . $postfix;
                } elseif ($k == 'addenda_img') {
                    $fileName = $fileNameBase . '_title_t' . $key . '.' . $postfix;
                }
                $log_tmp = "\n " . date('Y-m-d H:i:s', time()) . "url:----" . $url . '---imgSavePath:' . $urlPath . '----fileName:' . $fileName . "\n";
                $res = $this->getImage($url, $config['imagePath'], $fileName, $type = 0);
                // $res = getImage($url, $urlPath, $fileName, $type = 0);
                if ($res['error'] > 0) {
                    file_put_contents('./log/download_img.log', $log_tmp, FILE_APPEND | LOCK_EX);
                    return false;//die("download img error \n");
                } else {
                    $imageUrls[$k][] = str_replace($config['imagePath'], $imgWebPath, $res['save_path']);
                    // 生成缩略图.
                    foreach ($resizeRule['title'] as $key => $height) {
//                    $im = @imagecreatefromjpeg($res['save_path']);
                        if ($postfix == 'png') {
                            // var_dump(function_exists('imagecreatefrompng'));
                            // return false;
                            $im = @imagecreatefrompng($res['save_path']);
                        } elseif ($postfix == 'jpg') {
                            $im = @imagecreatefromjpeg($res['save_path']);
                        } else {
                            file_put_contents('./log/download_img_postfix.log', $res['save_path'], FILE_APPEND | LOCK_EX);
                            return false;//die("download img error \n");
//                        $im = @imagecreatefromgif($res['save_path']);
                        }
                        $name = str_replace('.' . $postfix, '_' . $height, $res['save_path']);
                        $this->resizeImage($im, '', $height, $name, '.' . $postfix);
                    }
                }
            }
        }
        // echo "\n";
        // print_r($imageUrls);
        return $imageUrls;
    }

    /**
     * 功能：php完美实现下载远程图片保存到本地
     * 参数：文件url,保存文件目录,保存文件名称，使用的下载方式
     * 当保存文件名称为空时则使用远程文件原来的名称
     */
    public function getImage($url, $save_dir = '', $filename = '', $type = 0)
    {
        $url = urldecode($url);
        print_r($url);
        if (trim($url) == '') {
            return array('file_name' => '', 'save_path' => '', 'error' => 1);
        }
        if (trim($save_dir) == '') {
            $save_dir = './';
        }
        if (trim($filename) == '') {//保存文件名
            $ext = strrchr($url, '.');
            // if ($ext != '.gif' && $ext != '.jpg') {
            if ($ext != '.jpg') {
                return array('file_name' => '', 'save_path' => '', 'error' => 3);
            }
            $filename = time() . $ext;
        }
        if (0 !== strrpos($save_dir, '/')) {
            $save_dir .= '/';
        }
        //创建保存目录
        if (!file_exists($save_dir) && !mkdir($save_dir, 0777, true)) {
            return array('file_name' => '', 'save_path' => '', 'error' => 5);
        }
        //获取远程文件所采用的方法
        if ($type) {
            $ch = curl_init();
            $timeout = 5;
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $timeout);
            $img = curl_exec($ch);
            curl_close($ch);
        } else {
            ob_start();
            readfile($url);
            $img = ob_get_contents();
            ob_end_clean();
        }
        //$size=strlen($img);
        //文件大小
        $fp2 = @fopen($save_dir . $filename, 'w');
        if ($fp2 === false) {
            return array('file_name' => $filename, 'save_path' => $save_dir . $filename, 'error' => 11);
        } else {
            $res = fwrite($fp2, $img);
            if ($res == false) {
                return array('file_name' => $filename, 'save_path' => $save_dir . $filename, 'error' => 22);
            }
            fclose($fp2);
        }
        unset($img, $url);
        return array('file_name' => $filename, 'save_path' => $save_dir . $filename, 'error' => 0);
    }


    /**
     * @param $im 图片对象，应用函数之前，你需要用imagecreatefromjpeg()读取图片对象，如果PHP环境支持PNG，GIF，也可使用imagecreatefromgif()，imagecreatefrompng()；
     * @param $maxwidth 定义生成图片的最大宽度（单位：像素）
     * @param $maxheight 生成图片的最大高度（单位：像素）
     * @param $name 生成的图片名
     * @param $filetype 最终生成的图片类型（.jpg/.png/.gif）
     */
    public function resizeImage($im, $maxwidth, $maxheight, $name, $filetype)
    {
        $pic_width = imagesx($im);
        $pic_height = imagesy($im);

        if (($maxwidth && $pic_width > $maxwidth) || ($maxheight && $pic_height > $maxheight)) {
            if ($maxwidth && $pic_width > $maxwidth) {
                $widthratio = $maxwidth / $pic_width;
                $resizewidth_tag = true;
            }

            if ($maxheight && $pic_height > $maxheight) {
                $heightratio = $maxheight / $pic_height;
                $resizeheight_tag = true;
            }

            if (isset($resizewidth_tag) && $resizewidth_tag && $resizeheight_tag) {
                if ($widthratio < $heightratio)
                    $ratio = $widthratio;
                else
                    $ratio = $heightratio;
            }

            if (isset($resizewidth_tag) && $resizewidth_tag && (!isset($resizeheight_tag) || !$resizeheight_tag))
                $ratio = $widthratio;
            if (isset($resizeheight_tag) && $resizeheight_tag && (!isset($resizewidth_tag) || !$resizewidth_tag))
                $ratio = $heightratio;

            $newwidth = $pic_width * $ratio;
            $newheight = $pic_height * $ratio;

            if (function_exists("imagecopyresampled")) {
                $newim = imagecreatetruecolor($newwidth, $newheight);
                imagecopyresampled($newim, $im, 0, 0, 0, 0, $newwidth, $newheight, $pic_width, $pic_height);
            } else {
                $newim = imagecreate($newwidth, $newheight);
                imagecopyresized($newim, $im, 0, 0, 0, 0, $newwidth, $newheight, $pic_width, $pic_height);
            }

            $name = $name . $filetype;
            imagejpeg($newim, $name);
            imagedestroy($newim);
        } else {
            $name = $name . $filetype;
            imagejpeg($im, $name);
        }
    }

//图片上传
    public function get_bandle_picture($pre_path, $arr)
    {
        require_once './core/aws.phar';
        $s3 = new \Aws\S3\S3Client([
            'version' => 'latest',
            'region' => 'us-east-1',
            'endpoint' => 'http://66.70.176.130:9000',
            'use_path_style_endpoint' => true,
            'credentials' => [
                'key' => 'MJZYGKNN300E0HOSNX8B',
                'secret' => 'HKkPCTISKgPuNpca4h7Pxj+NhXeJJwhzNk+t1RrD',
            ],
        ]);


        if (is_array($arr) && !empty($arr)) {
            foreach ($arr as $key => $value) {
                foreach ($value as $k => $v) {
                    //判断文件后缀
                    $str = substr(strrchr($v, '.'), 1);
                    $file_except_type = str_replace("." . $str, "", $v);
                    //200 和 50 的缩略图
                    $file = $v;
                    $file_two = $file_except_type . "_200" . "." . $str;
                    $file_five = $file_except_type . "_50" . "." . $str;
                    $bool1 = $this->upload_minio($pre_path, $file, $s3);
                    $bool2 = $this->upload_minio($pre_path, $file_two, $s3);
                    $bool3 = $this->upload_minio($pre_path, $file_five, $s3);

                }
            }
            if ($bool1 && $bool2 && $bool3) {
                return array("code" => 200, "msg" => "上传成功");
            } else {
                return array("code" => 100, "msg" => "上传失败");
            }
        } else {
            return array("code" => 100, "msg" => "数据不合法");
        }
    }

    public function upload_minio($pre_path, $file, $class)
    {

        //因为a/b/c 是名字实际不存在
        $real_file = substr($file, 6);


        try {
            $class->putObject([
                'Bucket' => 'promopure',
                'Key' => $file,
                'Body' => fopen($pre_path . $real_file, 'r'),
                'ACL' => 'public-read-write',
            ]);
        } catch (Exception $e) {
//        echo "There was an error uploading the file.\n";
            return false;
        }
//    echo "OK";
        return true;
    }
}
