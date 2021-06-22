<?php
namespace ecore\util;

class Request{

    protected static $instance = null;


    public function __construct(){
    }

    public final static function load(){
        if(!self::$instance instanceof self){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function scheme(){
        return $this->isSsl() ? 'https' : 'http';
    }

    public function host($strict = false){
        if (isset($_SERVER['HTTP_X_REAL_HOST'])) {
            return $_SERVER['HTTP_X_REAL_HOST'];
        } else {
            return $this->server('HTTP_HOST');
        }
    }

    public function domain(){
        return $this->scheme() . '://' . $this->host();
    }

    public function absUrl($domain=true){
        if ($this->isCli()) {
            return isset($_SERVER['argv'][1]) ? $_SERVER['argv'][1] : '';
        } elseif (isset($_SERVER['HTTP_X_REWRITE_URL'])) {
            $url_path = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $url_path = $_SERVER['REQUEST_URI'];
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) {
            $url_path = $_SERVER['ORIG_PATH_INFO'] . (!empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '');
        }
        return rtrim($domain ? sprintf("%s/%s",rtrim($this->domain(),'/'),ltrim($url_path,'/')) : $url_path,'/?#');
    }

    public function clientIp() {
        if(getenv('HTTP_CLIENT_IP') && strcasecmp(getenv('HTTP_CLIENT_IP'), 'unknown')) {
            $ip = getenv('HTTP_CLIENT_IP');
        } elseif(getenv('HTTP_X_FORWARDED_FOR') && strcasecmp(getenv('HTTP_X_FORWARDED_FOR'), 'unknown')) {
            $ip = getenv('HTTP_X_FORWARDED_FOR');
        } elseif(getenv('REMOTE_ADDR') && strcasecmp(getenv('REMOTE_ADDR'), 'unknown')) {
            $ip = getenv('REMOTE_ADDR');
        } elseif(isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] && strcasecmp($_SERVER['REMOTE_ADDR'], 'unknown')) {
            $ip = $_SERVER['REMOTE_ADDR'];
        }
        return preg_match ( '/[\d\.]{7,15}/', $ip, $matches ) ? $matches [0] : '';
    }

    public function method($method = false){
        if (isset($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE'])) {
            return strtoupper($_SERVER['HTTP_X_HTTP_METHOD_OVERRIDE']);
        } else {
            return $this->isCli() ? 'GET' : $_SERVER['REQUEST_METHOD'];
        }
    }

    public  function isGet(){
        return $this->method() == 'GET';
    }

    public  function isPost(){
        return $this->method() == 'POST';
    }

    public  function isPut(){
        return $this->method() == 'PUT';
    }

    public  function isDelete(){
        return $this->method() == 'DELETE';
    }

    public  function isHead(){
        return $this->method() == 'HEAD';
    }

    public  function isPatch(){
        return $this->method() == 'PATCH';
    }

    public  function isOptions(){
        return $this->method() == 'OPTIONS';
    }

    public  function isCli(){
        return PHP_SAPI == 'cli';
    }

    public  function isCgi(){
        return strpos(PHP_SAPI, 'cgi') === 0;
    }

    public  function isSsl(){
        $server = $_SERVER;
        if (isset($server['HTTPS']) && ('1' == $server['HTTPS'] || 'on' == strtolower($server['HTTPS']))) {
            return true;
        } elseif (isset($server['REQUEST_SCHEME']) && 'https' == $server['REQUEST_SCHEME']) {
            return true;
        } elseif (isset($server['SERVER_PORT']) && ('443' == $server['SERVER_PORT'])) {
            return true;
        } elseif (isset($server['HTTP_X_FORWARDED_PROTO']) && 'https' == $server['HTTP_X_FORWARDED_PROTO']) {
            return true;
        }
        return false;
    }

    public function isAjax(){
        $server = $_SERVER;
        return 'xmlhttprequest' == strtolower(isset($server['HTTP_X_REQUESTED_WITH']) ? $server['HTTP_X_REQUESTED_WITH'] : '');
    }

    public function isMobile(){
        if (isset($_SERVER['HTTP_VIA']) && stristr($_SERVER['HTTP_VIA'], "wap")) {
            return true;
        } elseif (isset($_SERVER['HTTP_ACCEPT']) && strpos(strtoupper($_SERVER['HTTP_ACCEPT']), "VND.WAP.WML")) {
            return true;
        } elseif (isset($_SERVER['HTTP_X_WAP_PROFILE']) || isset($_SERVER['HTTP_PROFILE'])) {
            return true;
        } elseif (isset($_SERVER['HTTP_USER_AGENT']) && preg_match('/(blackberry|configuration\/cldc|hp |hp-|htc |htc_|htc-|iemobile|kindle|midp|mmp|motorola|mobile|nokia|opera mini|opera |Googlebot-Mobile|YahooSeeker\/M1A1-R2D2|android|iphone|ipod|mobi|palm|palmos|pocket|portalmmm|ppc;|smartphone|sonyericsson|sqh|spv|symbian|treo|up.browser|up.link|vodafone|windows ce|xda |xda_)/i', $_SERVER['HTTP_USER_AGENT'])) {
            return true;
        } else {
            return false;
        }
    }

    public function env($name=null,$filter='stripslashes'){
        return $this->input($name,$_ENV,$filter);
    }
    public function get($name=null,$filter='stripslashes'){
        return $this->input($name,$_GET,$filter);
    }
    public function post($name=null,$filter='stripslashes'){
        return $this->input($name,$_POST,$filter);
    }
    public function cookie($name=null,$filter='stripslashes'){
        return $this->input($name,$_COOKIE,$filter);
    }
    public function session($name=null,$filter='stripslashes'){
        return $this->input($name,$_SESSION,$filter);
    }
    public function header($name=null,$filter="stripslashes"){
        return $this->input(strtolower(sprintf("HTTP_%S",$name)),$_SERVER,$filter);
    }
    public function server($name=null,$filter='stripslashes'){
        return $this->input($name,$_SERVER,$filter);
    }
    public function param($name=null,$filter='stripslashes'){
        return $this->input($name,$_REQUEST,$filter);
    }

    /**
     * 获取变量 支持过滤和默认值
     * @param string|false  $name 字段名
     * @param array         $data 数据源
     * @param string|array  $filter 过滤函数
     * @return mixed
     */
    public function input($name = '' ,$data = array() , $filter = 'stripslashes'){
        if (false === $name) {
            // 获取原始数据
            return $data;
        }
        $name = (string) $name;
        if ('' != $name) {
            // 按.拆分成多维数组进行判断
            foreach (explode('.', $name) as $val) {
                if (isset($data[$val])) {
                    $data = $data[$val];
                } else {
                    return null;
                }
            }
            if (is_object($data)) {
                return $data;
            }
        }

        if (is_array($data)) {
            array_walk_recursive($data, array($this, 'filterValue'), $filter);
            reset($data);
        } else {
            $this->filterValue($data, $name,$filter);
        }
        return $data;
    }

    /**
     * 递归过滤给定的值
     * @param mixed     $value 键值
     * @param mixed     $key 键名
     * @param array     $filters 过滤方法+默认值
     * @return mixed
     */
    public function filterValue(&$value, $key, $filters=array()){
        $_filters = is_array($filters) ? (array)$filters : explode("|",trim((string)$filters,"|"));
        foreach($_filters as $filter){
            if (is_callable($filter)) {
                // 调用函数或者方法过滤
                $value = call_user_func($filter, $value);
            } elseif (is_scalar($value)) {
                if (false !== strpos($filter, '/')) {
                    // 正则过滤
                    if (!preg_match($filter, $value)) {
                        // 匹配不成功返回默认值
                        $value = null;
                        break;
                    }
                } elseif (!empty($filter)) {
                    // filter函数不存在时, 则使用filter_var进行过滤
                    // filter为非整形值时, 调用filter_id取得过滤id
                    $value = filter_var($value, is_int($filter) ? $filter : filter_id($filter));
                    if (false === $value) {
                        $value = null;
                        break;
                    }
                }
            }
        }
        return $value;
    }
}