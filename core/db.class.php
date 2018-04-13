<?php

namespace CORE;
/**
 * PostgreSQL 操作类
 * Class PostgreSQL
 */
class PostgreSQL {
    private $linkid; // PostgreSQL连接标识符
    private $host; // PostgreSQL服务器主机
    private $port; // PostgreSQL服务器主机端口
    private $user; // PostgreSQL用户
    private $passwd; // PostgreSQL密码
    private $db; // Postgresql数据库
    private $result; // 查询的结果
    private $querycount; // 已执行的查询总数

    /**
     * 初始化$host、$user、$passwd和$db字段
     * @param $host
     * @param $port
     * @param $db
     * @param $user
     * @param $passwd
     */
    public function __construct($host, $port ,$db, $user, $passwd) {
        extension_loaded('pgsql') or die('您的 PHP 环境没有安装 PostgreSQL 扩展，这对 PHP 来说是必须的。');
        $this->host = $host;
        $this->port = $port;
        $this->user = $user;
        $this->passwd = $passwd;
        $this->db = $db;
    }

    /**
     * 连接Postgresql数据库
     */
    public function connect(){
        try{
            $this->linkid = @pg_connect("host=$this->host port=$this->port dbname=$this->db
user=$this->user password=$this->passwd");
            if(!$this->linkid){
                echo "Could not connect to PostgreSQL server.";
            }
        }
        catch (Exception $e) {
            die($e->getMessage());
        }
    }

    /**
     * 执行数据库查询
     * @param $query
     * @return resource
     */
    public function doSql($query){
        try{
            $this->result = @pg_query($this->linkid, $query);
            if(! $this->result)
                echo $query;
        }
        catch (Exception $e){
            echo $e->getMessage();
        }
        if ( false === $this->result ) {
            return false;
        } else {
            $this->numRows = pg_num_rows($this->result);
            return $this->getAll();
        }
//        $this->querycount++;
//        return $this->result;
    }
    /**
     * 获得所有的查询数据
     * @access private
     * @return array
     */
    private function getAll() {
        //返回数据集
        $result   =  @pg_fetch_all($this->result);
        @pg_result_seek($this->result,0);
        return $result;
    }

    /**
     * 确定受查询所影响的行的总计
     * @return int
     */
    public function affectedRows(){
        $count = @pg_affected_rows($this->linkid);
        return $count;
    }

    /**
     * 确定查询返回的行的总计
     * @return int
     */
    public function numRows(){
        $count = @pg_num_rows($this->result);
        return $count;
    }

    /**
     * 将查询的结果行作为一个对象返回
     * @return object
     */
    public function fetchObject(){
        $row = @pg_fetch_object($this->result);
        return $row;
    }

    /**
     * 将查询的结果行作为一个索引数组返回
     * @return array
     */
    public function fetchRow(){
        $row = @pg_fetch_row($this->result);
        return $row;
    }

    /**
     * 将查询的结果行作为一个关联数组返回
     * @return array
     */
    public function fetchArray(){
        $row = @pg_fetch_array($this->result);
        return $row;
    }

    /**
     * 返回在这个对象的生存期内执行的查询总数。
     * 这不是必须的，但是您也许会感兴趣。
     * @return mixed
     */
    public function numQueries(){
        return $this->querycount;
    }

    /**
     * 启动事务
     */
    public function begin(){
        $this->query('START TRANSACTION');
    }

    /**
     * 事务提交
     */
    public function commit(){
        $this->query('COMMIT');
    }

    /**
     * 事务回滚
     */
    public function rollback(){
        $this->query('ROLLBACK');
    }

    /**
     * 在当前事务里定义一个新的保存点
     * @param $savepointname
     */
    public function setsavepoint($savepointname){
        $this->query("SAVEPOINT $savepointname");
    }

    /**
     * 回滚到一个保存点
     * @param $savepointname
     */
    public function rollbacktosavepoint($savepointname){
        $this->query("ROLLBACK TO SAVEPOINT $savepointname");
    }
}