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

class Deals
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
        $this->mysqldb = new mysqldb($this->config['mysql_db']);
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
            die("没有指定参数, deals:deals队列, 0:coupon队列\n");
        }
        $limit = 20;
        $n = 0;
        do {
            do {
                $m = $n * $limit;
                if ($argv[1] == 'amazon') {
                    $asin = $this->redis->rpop('amazon_dealsQueue', true);
                    echo $asin;
                    echo "\n";
                    if ($asin) {
                        $id = 6;
                        $lim = $limit;
                    } else {
                        $lim = 0;
                    }
                } elseif ($argv[1] == "deals") {
                    // todo 测试
                    if (!empty($argv[2])) {
                        $store_name = $argv[2];
                    } else {
                        $store_name = $this->redis->rpop('storeDealsQueue', true);
                    }
                    echo $store_name;
                    echo "\n";
                    if ($store_name) {
                        // $store_name = $argv[2];

                        $sql = "select * from deals where source ='" . $store_name . "'";
                        echo $sql;
                        echo "\n";
                        $store_arr = ['ebay' => 30, 'newegg' => 21, 'walmart' => 11, 'bestbuy' => 14, 'amazon' => 6];
                        foreach ($store_arr as $k => $v) {
                            if ($store_name == $k) {
                                $id = $v;
                            }
                        }
                        $res = $this->mysqldb->doSql($sql);
                        if ($res) {
                            $this->PostProcessing($res, $id, 'deals');
                            $lim = $limit;
                        } else {
                            echo " #原始数据为空 ";
                            $lim = 0;
                        }
                    } else {
                        $lim = 0;
                    }

                } elseif ($argv[1] == "test") {
                    $store_id_arr = array(
                        21, 11, '30', '14', '6'
                        // '10899','204','7','9105','385','13','737',
                        // '301','3','517','15','308','21','2','9',
                        // '12792','8476','79','12','11'
                    );

                    foreach ($store_id_arr as $k => $v) {
                        $sql = "select * from coupons_deals where store_id = " . $v . " and source in ('retailmenot','everafterguide','dealsplus')";
                        echo "\n# 商城原始ID" . $v . " ";
                        $res = $this->mysqldb->doSql($sql);
                        $this->PostProcessing($res, $v);
                    }
                    die;
                } else {
                    $id = $this->redis->rpop('coupons_deals', true);
                    if ($id) {
                        $sql = "select source from store where id = " . $id . " and source in ('retailmenot','everafterguide','dealsplus')";
                        $store_info = $this->mysqldb->doSql($sql);
                        if (!empty($store_info)) {
                            echo "# 原始商城ID " . $id . " #原始商城来源 " . $store_info[0]['source'] . " ";
                            $sql = "select * from coupons_deals where store_id = " . $id . " and source = 'everafterguide'";
                            $res = $this->mysqldb->doSql($sql);
                            $this->PostProcessing($res, $id);
                            $lim = $limit;
                        } else {
                            echo "#原始商城ID " . $id . " 来源不属于('retailmenot','everafterguide') ";
                            $lim = 0;
                        }

                    } else {
                        $lim = 0;
                    }
                }
                $n = $n + 1;
            } while ($lim == $limit);
            echo "#" . date("Y-m-d h:i:s", time()) . " #当前商城ID$id, 暂时获取不到数据. 延迟10秒 \n";
            sleep(10);
        } while (true);
    }

    /**
     * 处理过滤文件
     * @param $url
     */
    public function paperFilter($url)
    {
        //解析url
        $sql = "select * from file_url order by id";
        $file_details = $this->pgsqldb->doSql($sql);
        if (!empty($url_arr['query'])) {
            $url_arr['query'] = str_replace('&amp;', '&', $url_arr['query']);
            //解析query参数
            parse_str($url_arr['query'], $query);
            $sql = "select * from file_url order by id";
            $file_details = $this->pgsqldb->doSql($sql);
            foreach ($file_details as $k => $v) {
                $tmp[0] = $v['name'];
                $tmp[1] = $v['argument'];
                //匹配商城
                if ($tmp[0] == '*') {
                    $filter = trim($tmp[1]);
                } elseif (strpos($url, $tmp[0])) {
                    $filter = $filter . ',' . trim($tmp[1]);
                    break;
                }
            }
            $filter_arr = [];
            foreach (explode(',', $filter) as $value) {
                if (!in_array(trim($value), $filter_arr)) {
                    $filter_arr[] = trim($value);
                }

            }
            if (!empty($filter_arr)) {
                foreach ($query as $k => $v) {
                    if (in_array($k, $filter_arr)) {
                        unset($query[$k]);
                    } else {
                        $query_str[] = $k . "=" . $v;
                    }
                }
            }
            if (!empty($query_str)) {
                $url_arr['query'] = implode('&', $query_str);
            } else {
                unset($url_arr['query']);
            }
            $new_url = $this->http_build_url($url_arr);
            echo " # ";
            print_r($new_url);
            return $new_url;
        } else {
            return $url;
        }
    }

    /**
     * 根据parse_url格式的数组生成完整的url
     * @param url_arr
     * @return string
     */
    function http_build_url($url_arr)
    {
        $new_url = $url_arr['scheme'] . "://" . $url_arr['host'];
        if (!empty($url_arr['port']))
            $new_url = $new_url . ":" . $url_arr['port'];
        $new_url = $new_url . $url_arr['path'];
        if (!empty($url_arr['query']))
            $new_url = $new_url . "?" . $url_arr['query'];
        if (!empty($url_arr['fragment']))
            $new_url = $new_url . "#" . $url_arr['fragment'];
        return $new_url;
    }

    /**
     * 后处理
     * @param $node
     * @return string
     */
    public function PostProcessing($node, $store_id, $type = '')
    {
        // todo 测试

        //查询原始store信息
        $store_sql = "select * from store where id =" . $store_id;
        $store_info = $this->mysqldb->doSql($store_sql);
        //过滤字段中单引号
        $store_info = $this->replace($store_info);
        $node = $this->replace($node);
        //处理商城tag
        $arr_info = $this->processTag($store_info[0]);
        $curl_where['domain'] = $store_info[0]['store_domain'];
        $curl_res = $this->curlCustom($curl_where);
        if ($curl_res['code'] == 200) {
            if ($curl_res['data']) {
                $proxy_status = 1;
            } else {
                $proxy_status = 2;
            }
        } else {
            $proxy_status = 3;
        }

        foreach ($node as $k => $v) {
            echo "\n" . "source :" . $v['source'];
            $data['title'] = $v['title'];
            $data['string_id'] = $this->generatingString('A', 9);
            $data['deal_pure_string_id'] = $this->generatingString('D1', 8);

            //处理title 最大百分比
            $pattern = '/[0-9]*\%/';
            preg_match($pattern, $v['title'], $match);
            if ($match) {
                $store_save[] = str_replace("%", "", $match[0]);
                $json['save'] = str_replace("%", "", $match[0]);
            }

            if (empty($type)) {
                //关键词
                $data['keywords'] = $v['keywords'];
                //处理过期时间
                $expire_time = strtotime(str_replace("Expires: ", "", $v['expire_time']));
                if ($expire_time) {
                    $data['expire_time'] = $expire_time;
                }
                //处理weight数据
                if ($v['status'] == 1) {
                    $json['title'] = $v['title'];
                    $json['third_part_id'] = $v['third_part_id'];
                    $json['keywords'] = $v['keywords'];
                    $json['description'] = str_replace("Details: ", "", $v['description']);
                    $json['code'] = $v['code'];
                    $json['link'] = htmlspecialchars_decode($v['link']);
                    $json['expire_time'] = $expire_time;
                    $json['status'] = (float)$v['status'];
                    $json['source'] = $v['source'];
                    $json['verify'] = (float)$v['verify'];
                    $json['path_string_id'] = $arr_info['string_id'];
                    $json['path_deal_pure_string_id'] = $arr_info['deal_pure_string_id'];
                    $json['deal_type'] = 1;
                    //获取商城信息
                    $json['source_display_name'] = $store_info[0]['source_display_name'];
                    $json['store_display_name'] = $store_info[0]['display_name'];
                    $data['jsonb'] = json_encode($json);
                }
                //处理coupon deals
                if (empty($v['code'])) {
                    $tag_arr[] = 208061;
                } else {
                    $tag_arr[] = 208060;
                }
                $tag_arr[] = 207376;
                $data['category'] = 'A4WD9EKEXG';
                //包邮tag
                if ($v['keywords'] == 'FREE SHIPPING') {
                    $tag_arr[] = 174;
                }
            } elseif ($type == 'deals') {
                $json['title'] = $v['title'];
                $json['third_part_id'] = $v['third_id'];
                $json['tag'] = $v['tag'];
                $json['description'] = str_replace("Details: ", "", $v['description']);
                $json['link'] = htmlspecialchars_decode($v['link']);
                $json['link_type'] = $v['link_type'];
                $json['price'] = (float)$v['price'];
                $json['original_price'] = (float)$v['original_price'];
                if ($json['original_price'] > 0) {
                    if ($json['original_price'] > $json['price']) {
                        $json['save'] = round((($json['original_price'] - $json['price']) / $json['original_price']) * 100);
                    } else {
                        $json['original_price'] = 0;
                        $json['save'] = 0;
                    }
                } else {
                    $json['save'] = 0;
                }
                $json['image_url'] = str_replace("https://img.promopure.com", '', $v['s3_image_url']);
                $json['pickup'] = $v['pickup'];
                $json['star'] = (float)$v['star'];
                $json['status'] = 1;
                $json['review'] = (float)$v['review'];
                $json['source'] = $v['source'];
                $json['brand'] = $v['brand'];
                $json['price_note'] = $v['price_note'];
                $json['info'] = $v['info'];
                $json['shipping'] = $v['shipping'];
                $json['path_string_id'] = $arr_info['string_id'];
                $json['path_deal_pure_string_id'] = $arr_info['deal_pure_string_id'];
                $json['deal_type'] = 2;
                // todo 测试
                //获取商城信息
                $json['source_display_name'] = $store_info[0]['source_display_name'];
                $json['store_display_name'] = $store_info[0]['display_name'];
                $tag_arr[] = 251180;//251180
                $tag_arr[] = 262062;
                $data['category'] = 'A44X1AWPEE';
                if (!empty($v['shipping'])) {
                    $tag_arr[] = 174;
                }
            }
            $tag_arr[] = 162;
            $tag_arr[] = $arr_info['tag_id'];
            if (!empty($json['third_part_id'])) {
                $source_id = $json['third_part_id'] . $store_info[0]['source'];
            } else {
                $source_id = md5($json['title']) . $store_info[0]['source'];
            }
            $link = $json['link'];
            $data['jsonb'] = json_encode($json);
            $data['tag_sets'] = "{" . implode(",", $tag_arr) . "}";
            $data['html'] = '<div class="widget"><!----4,' . json_encode($json) . '----></div>';
            echo " #".$data['category']." ";
            if (((strpos($link, 'not_responding')) == false)) {
                //判断文章是否存在
                $sql = "select * from source_id where source_id = '" . $source_id . "'";
                $no_article = $this->pgsqldb->doSql($sql);
                if ($no_article) {
                    $string_id_arr[] = $no_article[0]['article_id'];
                    $string_source_id_arr[] = $no_article[0]['id'];
                    $sql = 'select string_id,id,jsonb from article where id =' . $no_article[0]['article_id'];
                    $string_id_arrs = $this->pgsqldb->doSql($sql);
                    $sql = 'select * from tag_article_relevancy where article_id = ' . $string_id_arrs[0]['id'] . " and status = 1";
                    $relevancy = $this->pgsqldb->doSql($sql);
                    if ($relevancy) {
                        $tag_arr[] = 195;
                        $data['tag_sets'] = "{" . implode(",", $tag_arr) . "}";
                    }
                    if (!empty(json_decode($string_id_arrs[0]['jsonb'], true))) {
                        $out1 = array_diff(json_decode($string_id_arrs[0]['jsonb'], true), json_decode($data['jsonb'], true));
                        $out2 = array_diff(json_decode($data['jsonb'], true), json_decode($string_id_arrs[0]['jsonb'], true));
                        $out3 = array_merge($out1, $out2);
                    }
                    if (empty($out3)) {
                        $msg = " #无更新内容 ";
                        $sql = "update article set jsonb = '" . $data['jsonb'] . "',tag_sets = '" . $data['tag_sets'] . "',is_show = 1 where id =" . $no_article[0]['article_id'];
                    } else {
                        $sql = "update article set jsonb = '" . $data['jsonb'] . "',tag_sets = '" . $data['tag_sets'] . "',is_show = 1,update_time = " . time() . " where id =" . $no_article[0]['article_id'];
                        if ($data['category'] == 'A44X1AWPEE') {
                            $msg = "#DEAL_STRING_ID:" . $data['deal_pure_string_id'] . " #更新时间:" . date('Y-m-d H:i:s', time()) . " #变化字段" . json_encode($out3) . "\n";
                            $address = dirname(__FILE__) . "/log/" . $v['source'] . "_update";
                            file_put_contents($address, $msg, FILE_APPEND);
                        } else {
                            $msg = "#STRING_ID:" . $data['string_id'] . " #更新时间:" . date('Y-m-d H:i:s', time()) . " #变化字段" . json_encode($out3) . "\n";;
                        }
                    }
                    unset($out1, $out2, $out3);
                    $article_id = $this->pgsqldb->doSql($sql);

                    $article_ids = $no_article[0]['article_id'];
                    $string_id = $string_id_arrs[0]['string_id'];
                    echo " #" . $no_article[0]['id'] . " 文章已存在 ";
                } else {
                    $data['create_time'] = time();
                    $data['update_time'] = time();
                    $sql = $this->insert("article", $data);
                    $article_id = $this->pgsqldb->doSql($sql);
                    $string_id_arr[] = $article_id[0]['id'];
                    $article_ids = $article_id[0]['id'];

                    $source_where['source_id'] = $source_id;
                    $source_where['article_id'] = $article_id[0]['id'];
                    $source_where['store_id'] = $arr_info['tag_id'];
                    $sql = $this->insert("source_id", $source_where);
                    $source_return_id = $this->pgsqldb->doSql($sql);
                    if ($data['category'] == 'A44X1AWPEE') {
                        $msg = "#DEAL_STRING_ID:" . $data['deal_pure_string_id'] . " #新增时间:" . date('Y-m-d H:i:s', time()) . "\n";
                        $address = dirname(__FILE__) . "/log/" . $v['source'] . "_create";
                        file_put_contents($address, $msg, FILE_APPEND);
                    }
                    $string_id = $data['string_id'];
                    $string_source_id_arr[] = $source_return_id[0]['id'];
                    echo " #" . $article_id[0]['id'] . " " . $data['string_id'] . " 文章新增成功 ";
                }
                echo " " . $msg;
                if (!empty($json['save'])) {
                    $store_save_id[$json['save']] = $article_ids;
                }
                unset($tag_arr, $json, $article_ids);

                //处理load_URL
                $link = $this->paperFilter($v['link']);
                $load_where['string_id'] = $string_id;
                $load_where['url'] = htmlspecialchars_decode($link);
                $load_where['type'] = 2;
                $load_where['proxy_status'] = $proxy_status;
                //判断amazon单独处理
                if (strpos($link, 'amazon.com')) {
                    $amazo_url = parse_url($link);
                    $amazon_deal_url = parse_url($link);
                    if (!empty($amazo_url['query'])) {
                        $amazo_url['query'] = $amazo_url['query'] . '&tag=promopure-20&camp=1789&creative=9325&linkCode=as2&th=1&psc=1';
                        $amazon_deal_url['query'] = $amazon_deal_url['query'] . '&tag=dealspure-20&camp=1789&creative=9325&linkCode=as2&th=1&psc=1';
                    } else {
                        $amazo_url['query'] = 'tag=promopure-20&camp=1789&creative=9325&linkCode=as2&th=1&psc=1';
                        $amazon_deal_url['query'] = 'tag=dealspure-20&camp=1789&creative=9325&linkCode=as2&th=1&psc=1';
                    }
                    $load_where['proxy_redirect_url'] = $this->http_build_url($amazo_url);
                    $load_where['deals_proxy_redirect_url'] = $this->http_build_url($amazon_deal_url);
                } elseif (strpos($link, 'ebay.com')) {
                    $load_where['proxy_redirect_url'] = 'https://rover.ebay.com/rover/1/711-53200-19255-0/1?icep_id=114&ipn=icep&toolid=20004&campid=5338288750&mpre=' . urlencode($link);
                    $load_where['deals_proxy_redirect_url'] = 'https://rover.ebay.com/rover/1/711-53200-19255-0/1?icep_id=114&ipn=icep&toolid=20004&campid=5338288750&mpre=' . urlencode($link);
                } else {
                    if ($proxy_status == 1) {
                        $load_where['proxy_redirect_url'] = 'http://redirect.viglink.com?key=b016be6457962bc59579ad843d6b6d73&type=bk&u=' . urlencode($link);
                        $load_where['deals_proxy_redirect_url'] = 'http://redirect.viglink.com?key=b016be6457962bc59579ad843d6b6d73&type=bk&u=' . urlencode($link);
                    } else {
                        $load_where['proxy_redirect_url'] = "";
                        $load_where['deals_proxy_redirect_url'] = "";
                    }
                }
                //todo 新站deal url处理
                $sql = "select count(*) as count from load_url where string_id = '" . $string_id . "'";
                $load_url_count = $this->pgsqldb->doSql($sql);
                if ($load_url_count[0]['count'] > 0) {
                    $sql = "update load_url set proxy_status = " . $proxy_status . ", proxy_redirect_url = '" . $load_where['proxy_redirect_url'] . "',deals_proxy_redirect_url ='" . $load_where['deals_proxy_redirect_url'] . "',url ='" . $load_where['url'] . "' where string_id = '" . $string_id . "'";
                } else {
                    $sql = $this->insert("load_url", $load_where);
                    $sql = str_replace("RETURNING id", '', $sql);

                }
                $load_return = $this->pgsqldb->doSql($sql);
                echo " #" . $string_id . " Load_Url 处理成功 ";
            } else {
                echo "#link错误" . $link . "\n";
                unset($link);
            }


        }
        //更新商城最大save
        if (!empty($store_save)) {
            rsort($store_save);
            krsort($store_save_id);
            if (!empty($store_save_id[$store_save[0]])) {
                $sql = "update store set save =" . $store_save[0] . ",save_coupon_id = " . $store_save_id[$store_save[0]] . ",update_time = " . time() . " where path_expression_id = " . $arr_info['path_id'];
                $no_store_save = $this->pgsqldb->doSql($sql);
                echo "\n # 最大折扣 " . $store_save[0] . "% COUPON_ID: " . $store_save_id[$store_save[0]] . " ";
            } else {
                echo "\n # 最大折扣无变化";
            }
        }
        if (empty($type)) {
            //过滤无效cpupon_deal
            $string_id_str = implode(',', $string_id_arr);
            $sql = "update article set is_show = 0 where tag_sets&&array[162]::int8[] AND tag_sets&&array[207376]::int8[] AND tag_sets&&array[" . $arr_info['tag_id'] . "]::int8[] AND id not in (" . $string_id_str . ") ";
            $no_article_show = $this->pgsqldb->doSql($sql);
            $string_source_id_str = implode(',', $string_source_id_arr);
            $sql = "update source_id set is_show = 0 where store_id = " . $arr_info['tag_id'] . " AND id not in (" . $string_source_id_str . ") ";
            $no_source_show = $this->pgsqldb->doSql($sql);
            echo "\n # 无效coupon过滤完毕 \n\n";
            unset($store_save, $string_id_arr, $string_source_id_arr);
        }
    }

    /**
     * 处理商城tag
     * @param $store_info
     * @return string
     */
    function processTag($store_infos)
    {
        $store_info = $this->replace($store_infos);

        //生成tag
        $sql = "select * from tag where tag_name ='" . $store_info['lower_name'] . "' and type_id = 1";
        $no_tag = $this->pgsqldb->doSql($sql);
        if ($no_tag) {
            $tag_id = $no_tag[0]['id'];
            echo "  #" . $tag_id . " tag已存在  ";
        } else {
            $tag_where['tag_name'] = $store_info['lower_name'];
            $tag_where['string_id'] = $this->generatingString('T1', 8);
            $tag_where['desc'] = $store_info['display_name'];
            $tag_where['type_id'] = 1;
            $tag_where['create_time'] = time();
            $tag_where['update_time'] = time();

            $sql = $this->insert("tag", $tag_where);
            $in_tag = $this->pgsqldb->doSql($sql);
            $tag_id = $in_tag[0]['id'];
            echo "  #" . $tag_id . " tag新增成功  ";
        }

        //生成表达式
        $sql = "select * from path_expression where path_expression ='" . "T" . $tag_id . "+T162' and type = 6";
        $no_path = $this->pgsqldb->doSql($sql);
        if ($no_path) {
            $path_id = $no_path[0]['id'];
            $string_id = $no_path[0]['string_id'];
            $deal_pure_string_id = $no_path[0]['deal_pure_string_id'];
            echo "  #" . $path_id . " 路径表达式已存在  ";
        } else {
            //生成路径表达式
            $path_where['path_expression'] = "T" . $tag_id . "+T162";
            $path_where['string_id'] = $this->generatingString('S1', 8);
            $path_where['deal_pure_string_id'] = $this->generatingString('P1', 8);
            $path_where['name'] = $store_info['display_name'];
            $path_where['type'] = 6;

            $sql = $this->insert("path_expression", $path_where);
            $in_path = $this->pgsqldb->doSql($sql);
            $path_id = $in_path[0]['id'];
            $string_id = $path_where['string_id'];
            $deal_pure_string_id = $path_where['deal_pure_string_id'];
            echo "  #" . $path_id . " 路径表达式新增成功  ";
        }
        if (!empty($store_info['display_name'])) {

            //生成list_rule
            $sql = "select * from list_rule where path_expression_id =" . $path_id;
            $no_rule = $this->pgsqldb->doSql($sql);
            if (!$no_rule) {
                //生成list_rule数据
                $rule_where['path_expression_id'] = $path_id;
                $rule_where['friendly_url_part'] = $this->getGoodsUrl($store_info['display_name']) . "-Promo-Codes-Coupons";
                $rule_where['tree_name_part'] = $store_info['display_name'];
                $rule_where['display_name'] = $store_info['display_name'];

                $sql = insert("list_rule", $rule_where);
                $in_rule = $this->pgsqldb->doSql($sql);
                echo "  #" . $in_rule[0]['id'] . " 路径参数新增成功  ";
            } else {
                echo "  #" . $no_rule[0]['id'] . " 路径参数已存在  ";
            }
            //生成商城信息表
            $sql = "select * from store where lower_name = '" . $store_info['lower_name'] . "'";
            $no_store = $this->pgsqldb->doSql($sql);
            if (!$no_store) {
                //生成商城信息
                $store_where['source_display_name'] = $store_info['source_display_name'];
                $store_where['display_name'] = $store_info['display_name'];
                $store_where['lower_name'] = $store_info['lower_name'];
                $store_where['desc'] = $store_info['desc'];
                $store_where['img_url'] = $store_info['promopure_img_url'];
                $store_where['store_domain'] = $store_info['store_domain'];
                $store_where['path_expression_id'] = $path_id;
                $store_where = $this->replace($store_where);
                $sql = $this->insert("store", $store_where);
                $in_store = $this->pgsqldb->doSql($sql);
                echo "  #" . $in_store[0]['id'] . " 商城新增成功  \n";
            } else {
                echo "  #" . $no_store[0]['id'] . " 商城已存在  \n";
            }

        }
        $return['tag_id'] = $tag_id;
        $return['path_id'] = $path_id;
        $return['string_id'] = $string_id;
        $return['deal_pure_string_id'] = $deal_pure_string_id;
        //返回tagID
        return $return;
    }

    /**
     *
     */
    public function replace($node)
    {
        foreach ($node as $k => $v) {
            $node[$k] = preg_replace("/[\']+/", "''", $v);
        }
        return $node;
    }

    /**
     * 生成friendly_url
     * @param $config
     * @return db
     */
    public function getGoodsUrl($title)
    {
        // 标题, 首字母大写, 单词用-连接.
        $title = preg_replace("/[\'\‘\’]+/", "", $title);
        $title = preg_replace("/[\s\`\~\!\@\#\$\%\^\&\*\(\)\_\-\=\+\<\>\?\;\:\"\{\}\,\.\\/\'\[\]\|]+/", "-", $title);
        $title = rtrim($title, '-');
        return $title;
    }

    /**
     * @param $config
     * @return db
     */
    public function generatingString($prefix, $num)
    {
        //生成字符串id
        do {
            $stringId = $prefix . $this->randString($num);
            $sql = "SELECT id FROM goods WHERE string_id = '" . $stringId . "'";
            $result = $this->pgsqldb->doSql($sql);
            if (!$result) {
                return $stringId;
            }
        } while ($result);
    }

    /*
     * insert转换
     * @param $db_name $wheres
     */
    public function insert($db_name, $wheres)
    {
        $where = $this->replace($wheres);
        $sql = "insert into " . $db_name . " (" . '"' . implode('","', array_keys($where)) . '"' . ") VALUES ('" . implode("','", $where) . "') RETURNING id";
        return $sql;
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

    /**
     * 第三方获取tag
     * @param array $params ['domain' => 'nike.com']
     * @return array|string
     */
    public function curlCustom($params)
    {
        $secret_key = 'cfe55d99f840d826feae3cf6ecdab44818af0af6';
        $str = [];
        $uri = '';
        if ($params) {
            foreach ($params as $key => $val) {
                $str[] = $key . '=' . $val;
            }
            $uri = implode('&', $str);
        }
        $url = 'https://publishers.viglink.com/api/merchant/search';
        if ($uri) {
            $url .= '?' . $uri;
        }

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $url);
        // 允许 cURL 函数执行的最长秒数
        curl_setopt($curl, CURLOPT_TIMEOUT, 30);
        // 获取的信息以文件流的形式返回, 如果不设置, 则直接输出了返回的结果. 设置后, 可以把结果保存到变量里面.
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        // 显示返回的Header区域内容
        curl_setopt($curl, CURLOPT_HEADER, false);

        curl_setopt($curl, CURLOPT_HTTPHEADER, array('Authorization: secret ' . $secret_key));
        // 不返回请求体内容
        curl_setopt($curl, CURLOPT_NOBODY, false);
        // TRUE 时追踪句柄的请求字符串，从 PHP 5.1.3 开始可用。这个很关键，就是允许你查看请求header
        curl_setopt($curl, CURLINFO_HEADER_OUT, true);
        // curl_setopt($curl, CURLOPT_SSLVERSION, 3);

        $tmpInfo = curl_exec($curl);
        if (curl_errno($curl)) {
            return ['code' => 404, 'data' => curl_error($curl)];
        }

        $info = curl_getinfo($curl);
        curl_close($curl);

        if ($info['http_code'] == 200) {
            $res = json_decode($tmpInfo, true);
            if ($res['merchants']) {
                return ['code' => 200, 'data' => $res];
            } else {
                return ['code' => 200, 'data' => false];
            }
        } else {
            return ['code' => 333, 'data' => $tmpInfo];
        }
    }
}
