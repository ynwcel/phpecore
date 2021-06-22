<?php
namespace ecore\bin;

class Redis extends AbsBase{
    protected $config = array();

    private $handler = null;

    /**
     * 构造函数
     * @access public
     */
    public function __construct($args=array()){
        parent::__construct();
        if(!extension_loaded('redis')){
            throw new \Exception("Require Extension：<Redis>",500);
        }

        if(is_array($args)){
            $this->config = array_merge($this->config,$args);
        }
    }

    public function handler(){
        if(!$this->handler || !$this->handler instanceof \Redis){
            $this->handler = new \Redis;
            
            $host = isset($this->config['host']) ? $this->config['host'] : '127.0.0.1';
            $port = isset($this->config['port']) ? $this->config['port'] : '6379';
            $timeout = isset($this->config['timeout']) ? $this->config['timeout'] : '1';
            $database = isset($this->config['database']) ? $this->config['database'] : '0';

            if (isset($this->config['persistent']) && $this->config['persistent']) {
                $this->handler->pconnect($host, intval($port), intval($timeout), 'persistent_id_' . $database);
            } else {
                $this->handler->connect($host, intval($port), intval($timeout));
            }

            if (isset($this->config['password']) &&  '' != $this->config['password']) {
                $this->handler->auth($this->config['password']);
            }

            if (0 != $database) {
                $this->handler->select($database);
            }
        }
        return $this->handler;
    }


    public function __call($method,$args=array()){
        $this_cls = get_class($this);
        $handler = $this->handler();
        if(is_callable(array($handler,$method))){
            return call_user_func_array(array($handler,$method),$args);
        }else{
            return parent::__call($method,$args);
        }
    }



    /**
     * 判断缓存
     * @access public
     * @param string $key 缓存变量名
     * @return bool
     */
    public function has($key)    {
        return $this->handler()->get($key) ? true : false;
    }

    /**
     * 读取缓存
     * @access public
     * @param string $key 缓存变量名
     * @param mixed  $default 默认值
     * @return mixed
     */
    public function get($key, $default = false){
        $value = $this->handler()->get($key);
        if (is_null($value) || false === $value) {
            return $default;
        }

        try {
            $result = 0 === strpos($value, 'ecore_json:') ? json_decode(substr($value, 11)) : $value;
        } catch (\Exception $e) {
            $result = $default;
        }

        return $result;
    }

    /**
     * 写入缓存
     * @access public
     * @param string            $key 缓存变量名
     * @param mixed             $value  存储数据
     * @param integer|\DateTime $expire  有效时间（秒）
     * @return boolean
     */
    public function set($key, $value, $expire = null)
    {
        if (is_null($expire)) {
            $expire = $this->config['expire'];
        }

        $value = is_scalar($value) ? $value : 'ecore_json:' . json_encode($value,JSON_UNESCAPED_UNICODE);
        if ($expire) {
            return $this->handler()->setex($key, $expire, $value);
        } else {
            return $this->handler()->set($key, $value);
        }
    }

    /**
     * 自增缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function inc($key, $step = 1){
        return $this->handler()->incrby($key, $step);
    }

    /**
     * 自减缓存（针对数值缓存）
     * @access public
     * @param  string    $key 缓存变量名
     * @param  int       $step 步长
     * @return false|int
     */
    public function dec($key, $step = 1){
        return $this->handler()->decrby($key, $step);
    }

    /**
     * 删除缓存
     * @access public
     * @param string $key 缓存变量名
     * @return boolean
     */
    public function rm($key){
        return $this->handler()->delete($key);
    }

    /**
     * 清除缓存
     * @access public
     * @param string $tag 标签名
     * @return boolean
     */
    public function clear($pattern = null){
        if ($pattern) {
            $it = null;
            $count = 1000;
            while (true) {
                $keys = call_user_func_array(array($this->handler(), 'scan'), array(&$it, $pattern, $count));
                if(!is_array($keys) || count($keys)<=0){
                    break;
                }
                foreach ($keys as $key) {
                    $this->handler()->delete($key);
                }
            }
            return true;
        }else{
            return false;
            return $this->handler()->flushDB();
        }
    }
}