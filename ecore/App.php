<?php
namespace ecore;

use Exception;
use ErrorException;

final class App{
    private static $instance = null;

    private $web_root = "";
    private $app_root = "";
    private $app_name = "";
    private $app_pool = array();
    private $app_logs = array();

    private $app_config = array();
    private $app_router = array();
    private $app_dispatch = array();

    /**
     * 单一实例初始化函数
     */
    private final function __construct(){
        version_compare(PHP_VERSION,'5.4','gt') or exit('Require PHP5.4+!');
        extension_loaded('PDO_MYSQL') or exit('Require Extension:<PDO_MYSQL>');

        if(defined('WEB_ROOT')){
            $this->web_root = rtrim(str_replace("\\", '/', WEB_ROOT),'/');
            if(defined('APP_ROOT')){
                $this->app_root = rtrim(str_replace("\\", '/', APP_ROOT),'/');
            }else{
                $this->app_root = rtrim(dirname($this->web_root),'/');
            }
            $this->app_name = preg_replace("/[^\w]+/",'', basename($this->app_root));
        }else{
            exit('Undefined Constant Name:<WEB_ROOT>');
        }

        define('ECORE_APP_START_TIME',microtime(true));
        define('ECORE_APP_START_MEMORY',memory_get_usage());

        spl_autoload_register(array($this,'autoloadHandler'));
        set_error_handler(array($this,'errorHandler'));
        set_exception_handler(array($this,'exceptionHandler'));
        register_shutdown_function(array($this,'shutdownHandler'));

        ob_start();
        $this->config(require __DIR__.'/vendor/config.php');

        $config_file = $this->fpath('core/config.php');
        $user_config = array();
        if(file_exists($config_file)){
            $user_config = require_once $config_file;
            $user_config = is_array($user_config) ? array_change_key_case($user_config) : array();
        }
        $this->config($user_config);

        ini_set('display_errors',$this->config('app.debug',false));
        date_default_timezone_set($this->config('app.timezone','PRC',false));
        session_name($this->config('app.session_save_name',sprintf('%sssid',$this->getAppName()),false));
        $this->config('app.session_save_handler') && ini_set('session.save_handler',$this->config('app.session_save_handler'));
        $this->config('app.session_save_path') && ini_set('session.save_path',$this->config('app.session_save_path'));

        if(isset($_REQUEST[session_name()]) && $_REQUEST[session_name()]){
            session_id($_REQUEST[session_name()]);
        }

        if($this->config('app.session_auto_start',true) && PHP_SESSION_ACTIVE != session_status()){
            session_start();
        }

        $this->import('core/define.php');
        $this->import('core/common.php');
        $this->import('core/bootstrap.php');
    }

    public function __get($var){
        if(isset($this->app_pool[$var])){
            return $this->app_pool[$var];
        }else{
            return null;
        }
    }

    public function __set($var,$val=null){
        return $this->app_pool[$var] = $val;
    }

    /**
     * 载入单一实例
     * @return ecore\App
     */
    public static final function load(){
        if(!self::$instance instanceof self){
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * 项目回调
     * @param  [type] $name [description]
     * @param  array  $args [description]
     * @return [type]       [description]
     */
    public function hook($name,$args=array()){
        $hook_class = sprintf("\\core\\bin\\AppHook");
        if(class_exists($hook_class)){
            if(is_callable(array($hook_class,$name))){
                return call_user_func_array(array($hook_class,$name), $args);
            }
        }
        return false;
    }

    /**
     * 返回 web root 路径
     * @return string
     */
    public function getWebRoot(){
        return rtrim(str_replace("\\","/",$this->web_root),'/');
    }

    /**
     * 返回 app root 路径
     * @return string
     */
    public function getAppRoot(){
        return rtrim(str_replace("\\",'/',$this->app_root),'/');
    }


    /**
     * 返回 app name
     * @return string
     */
    public function getAppName(){
        return $this->app_name;
    }

    /**
     * 框架运行
     * @return null
     */
    public function run(){
        if(defined('ECORE_APP_RUN')){
            return ECORE_APP_RUN;
        }
        define('ECORE_APP_RUN',true);
        try{

            $dispatch = $this->dispatch();

            list($group,$action,$method,$args) = array_values($this->dispatch);

            $preg = "/^[a-z][a-z0-9_]+$/i";
            if(!$group || !preg_match($preg,$group)){
                throw new Exception("Invalid Group Name:<$group>",404);
            }
            if(!$action || !preg_match($preg,$action)){
                throw new Exception("Invalid Action Name:<$action>",404);
            }

            if(!$method || !preg_match($preg,$method)){
                throw new Exception("Invalid Method Name:<$method>",404);
            }

            $action_name_source = $this->alias($action);
            $method_name_source = $this->alias($method);

            $action_class = sprintf("\\app\\%s\\%s",strtolower($group),ucfirst($action_name_source));
            $this->addLog('ActionClass::'.$action_class);
            if(class_exists($action_class)){
                $method_name1 = sprintf("%s%s",strtolower($_SERVER['REQUEST_METHOD']),ucfirst($method_name_source));
                $method_name2 = sprintf('do%s',ucfirst($method_name_source));
                $flag1 = method_exists($action_class, $method_name1);
                $flag2 = method_exists($action_class, $method_name2);
                if($flag1 || $flag2){
                    $action_method_name = $flag1 ? $method_name1 : $method_name2;
                    $ref_method = new \ReflectionMethod($action_class,$action_method_name);
                    if($ref_method && $ref_method->isPublic()){
                        $this->addLog('ActionMethod::'.var_export(array($action_class,$action_method_name),true));
                        $action_obj = new $action_class();
                        $hook_args = array(
                            'action_class'=>$action_class,
                            'action_object'=>$action_obj,
                            'action_method'=>$action_method_name,
                            'reflection_method'=>$ref_method,
                        );
                        $this->hook('app_action_prefix',$hook_args);
                        $result = $ref_method->invokeArgs($action_obj,$args);
                        $hooke_result = $this->hook('app_action_suffix',array('action_result'=>$result));
                        if(false !== $hooke_result){
                            $result = $hooke_result;
                        }
                        return $result;
                    }else{
                        throw new Exception("Not Access Action Method:<$action_class::$action_method_name()>",404);
                    }
                }else{
                    throw new Exception("Not Found Action Method:<$action_class::$method_name1()> or <$action_class::$method_name2()>",404);
                }
            }else{
                throw new Exception("Not Found Action Class:<$action_class>",404);
            }
        }catch(Exception $err){
            return $this->exceptionHandler($err);
        }
    }

    /**
     * 获取/设置配置信息
     * @param  string/Array  $key           传递数组表示设置，传递字符串表示获取配置
     * @param  mixed         $default       默认值
     * @param  boolean       $empty_val     检查配置值是否为空
     * @return mixed                        配置值 / 数组
     */
    public function config($key=null,$default=null,$empty_val = true){
        if(!$key){
            return $this->app_config;
        }elseif(is_array($key)){
            $user_config = array_change_key_case($key);
            foreach($user_config as $key=>&$val){
                if(is_array($val)){
                    $val = array_change_key_case($val);
                    if(!isset($this->app_config[$key])){
                        $this->app_config[$key] = array();
                    }
                    $this->app_config[$key] = array_merge($this->app_config[$key],$val);
                }else{
                    $this->app_config[$key] = $val;
                }
            }
            return $this->app_config;
        }else{
            $key = strtolower($key);
            $value = $default;
            if(isset($this->app_config[$key])){
                $value = $this->app_config[$key];
            }elseif(isset($this->app_config['user_params'][$key])){
                $value = $this->app_config['user_params'][$key];
            }else{
                $key = trim($key,'.');
                if(strpos($key, '.')>0){
                    list($subgroup,$subkey) = explode('.', $key,2);
                    if(isset($this->app_config[$subgroup][$subkey])){
                        $value = $this->app_config[$subgroup][$subkey];
                    }elseif(isset($this->app_config['user_params'][$subgroup][$subkey])){
                        $value = $this->app_config['user_params'][$subgroup][$subkey];
                    }
                }
            }
            return $empty_val || !empty($value) ? $value : $default;
        }
    }

    /**
     * 解析别名（以"."分割路径,以"-_"分割单词）
     * @param  [type] $alias [description]
     * @return [type]        [description]
     */
    public function alias($alias){
        $alias = trim($alias,'.');
        $info = explode(".", $alias);
        $name = array_pop($info);

        $info_str = strtolower(implode("\\", $info));
        $name_str = implode("", array_map('ucfirst',preg_split("/[\-_]/", $name)));
        return trim(sprintf("%s\\%s",$info_str,$name_str),"\\");
    }

    /**
     * 格式化文件路径
     * @param  string $_file 相对路径(相对于APP_ROOT)
     * @return string        绝对路径
     */
    public function fpath($_file){
        if(strpos($_file, $this->getAppRoot())===0){
            if(file_exists($_file)){
                return $_file;
            }else{
                $_file = substr($_file,strlen($this->getAppRoot())-1);
            }
        }
        $file = str_replace('\\','/', $_file);
        return sprintf("%s/%s",rtrim($this->getAppRoot(),'/'),ltrim($file,'/'));
    }

    /**
     * 引入文件
     * @param  string           $_file      引入文件
     * @return mixed|bool                   如果文件可引入，返回引入结果，或者返回 false
     */
    public function import($_file){
        $file = $this->fpath($_file);
        if(file_exists($file) && !is_dir($file) && strtolower(substr($file, -4))=='.php'){
            return require_once $file;
        }
        return false;
    }

    /**
     * URL 生成
     * @param  string       $url        url路径
     * @param  array        $params     参数
     * @return string                   生成的url
     */
    public function url($url,$params=array()){
        if(!$url){
            $url = isset($_SERVER['HTTP_REFERER']) && $_SERVER['HTTP_REFERER'] ? $_SERVER['HTTP_REFERER'] : '/';
        }
        //处理站外url 【http(s)://】
        if(strpos($url,'://')>0){
            $url_params = parse_url($url);
            if(isset($url_params['query'])){
                parse_str($url_params['query'],$query);
            }else{
                $query = array();
            }
            $path_query = http_build_query(array_merge($query,$params));
            $fragment = isset($url_params['fragment'])?$url_params['fragment'] : '';
            return trim(sprintf("%s?%s#%s",current(explode("?", $url)),$path_query,$fragment),"?#");
        }

        //处理站内url
        list($_g,$_a,$_m,$args) = array_values($this->dispatch());
        
        list($path_info,$query) = array('',array());
        $url_params = parse_url($url);

        if(isset($url_params['query'])){
            parse_str($url_params['query'],$query);
        }
        if(isset($url_params['path']) && trim($url_params['path'],'/')){
            $sub_page_info = explode("/", trim($url_params['path'],'/'));
            if(count($sub_page_info)==1){
                $path_info = sprintf("$_g/$_a/%s",array_shift($sub_page_info));    
            }elseif(count($sub_page_info)==2){
                $path_info = sprintf("$_g/%s/%s",array_shift($sub_page_info),array_shift($sub_page_info));    
            }else{
                $path_info = implode("/", $sub_page_info);
            }
        }else{
            $path_info = "$_g/$_a/$_m";
        }

        /*if(strpos($url,'?')===0){
            $args_str = count($args)>0 ? "/".implode('/',$args) : '';
            $url = "/$_g/$_a/$_m{$args_str}$url";
        }elseif(strpos($url,'/')!==0){
            $url = sprintf("/%s/%s",$_g,$url);
        }
        $url_params = parse_url($url);
        if(isset($url_params['query'])){
            parse_str($url_params['query'],$query);
        }else{
            $query = array();
        }
        $path_info = isset($url_params['path']) ? $url_params['path'] : '';
        $path_query = http_build_query(array_merge($query,$params));
        */
        $path_query = http_build_query(array_merge($query,$params));
        $path_info = trim($path_info,'/');
        $path_query = $path_query ? "?$path_query" : "";
        $url_base = trim($this->config('app.url_base','/'),'/');
        $url_rewrite = $this->config('app.url_rewrite',true);
        $url_split = $this->config('app.url_split','/',false);
        $url_suffix = $this->config('app.url_suffix','');
        $url_rules = $this->config('app.url_rules',array(),false);
        $url_suffix = $url_suffix && strpos($url_suffix,'.')!==0 ? ".$url_suffix" : $url_suffix;
        $build_url = array();
        if($path_info){
            $path_info = sprintf("/%s/",trim($path_info,'/'));
            if(is_array($url_rules)){
                foreach($url_rules as $_path=>$_rule){
                    $_rule = sprintf("/%s/",trim(str_replace($url_split, '/', $_rule),'/'));
                    $_path = sprintf("/%s/",trim(str_replace($url_split, '/', $_path),'/'));
                    $_rule_preg = $_rule;
                    #while(preg_match('/\/(:\w+)\//', $_rule_preg)){
                    #    $_rule_preg = preg_replace('/(\/(:\w+)\/)+/','/(\w+)/', $_rule_preg);
                    #}
                    #$_rule_preg = sprintf("#^%s$#i",$_rule_preg);
                    $_rule_preg = "#^".preg_replace('/(:\w+)/','(\w+)', $_rule)."$#i";
                    if(@preg_match($_rule_preg,$path_info)){
                        preg_match_all('/:\w+/', $_rule, $_maths1);
                        if(isset($_maths1[0])){
                            foreach($_maths1[0] as $key=>$val){
                                $_path = str_replace("/$val/", '/${'.($key+1)."}/", $_path);
                            }
                        }
                        $path_info = preg_replace($_rule_preg, $_path, $path_info);
                        break;
                    }
                }
            }
            $path_info = trim($path_info,'/');
            if($path_info && $url_suffix){
                $path_info = $path_info.$url_suffix;
            }
            if(!$url_rewrite){
                $url_script = $this->config('app.url_script','index.php',false);
                $build_url[] = trim($url_script,'/');
            }
            $build_url[] = trim(str_replace('/',$url_split,$path_info),$url_split);
        }
        $build_url_str = trim(implode('/',$build_url),'/');
        $build_url_str = $build_url_str ? sprintf("%s/%s",$url_base,$build_url_str) : '';
        $build_url_str = strpos(strtolower($build_url_str),'http://')===0 ? $build_url_str : '/'.ltrim($build_url_str,'/');
        return $build_url_str.$path_query;
    }

    /**
     * 路由解析
     * @return array('group','action','method','args')
     */
    public function dispatch(){
        if(is_array($this->dispatch) && count($this->dispatch)==4){
            return $this->dispatch;
        }
        $path_info = trim($this->router(),'/');

        $this->hook('app_dispatch_prefix');

        $app_modules = $this->config('app.url_modules',array());
        $url_split = $this->config('app.url_split','/',false);
        $url_rules = $this->config('app.url_rules',array(),false);
        $app_modules = is_array($app_modules) ? array_map('strtolower', $app_modules) : array();
        if(count($app_modules)<=0){
            throw new Exception("Invalid Config Item:<app.url_modules>",404);
        }
        $group = $action = $method = null;
        $router_args = $params = array();
        $path_info = sprintf("/%s/",trim(str_replace($url_split, '/', $path_info) ,'/'));
        if(is_array($url_rules)){
            foreach($url_rules as $_rule=>$_path){
                $_rule = sprintf("/%s/",trim(str_replace($url_split, '/', $_rule),'/'));
                $_path = sprintf("/%s/",trim(str_replace($url_split, '/', $_path),'/'));
                #$_rule_preg = $_rule;
                #while(preg_match('/\/(:\w+)\//', $_rule_preg)){
                #    $_rule_preg = preg_replace('/(\/(:\w+)\/)+/','/(\w+)/', $_rule_preg);
                #}
                #$_rule_preg = sprintf("#^%s$#i",$_rule_preg);
                $_rule_preg = "#^".preg_replace('/(:\w+)/','(\w+)', $_rule)."$#i";
                if(@preg_match($_rule_preg,$path_info)){
                    preg_match_all('/:\w+/', $_rule, $_maths1);
                    if(isset($_maths1[0])){
                        foreach($_maths1[0] as $key=>$val){
                            $_path = str_replace("/$val/", '/${'.($key+1)."}/", $_path);
                        }
                    }
                    $path_info = preg_replace($_rule_preg, $_path, $path_info);
                    break;
                }
            }
        }
        $params = parse_url($path_info);
        $path_info = isset($params['path']) ? $params['path'] : '';
        if(isset($params['query'])){
            parse_str($params['query'],$query);
        }else{
            $query = array();
        }
        $_GET = array_merge($query,$_GET);
        $path_info = trim($path_info,'/');
        if(!defined('__GROUP__')){
            $group_info = explode('/', $path_info,2);
            $tmp_group = strtolower($group_info[0]);
            if(in_array($tmp_group, $app_modules)){
                $group = $tmp_group;
                $path_info = isset($group_info[1]) ? trim($group_info[1],'/') : '';
                //}elseif(!$tmp_group || !in_array($tmp_group, $app_modules)){
            }else{
                $group = current($app_modules);
            }
        }elseif(defined('__GROUP__')){
            $group = __GROUP__;
        }

        if(!in_array(strtolower($group),$app_modules)){
            throw new Exception("Invalid Group Name:<$group>",404);
        }
        if($path_info){
            $types = explode('/', $path_info);
            for($i=0,$j=count($types);$i<$j;$i++){
                if($i==0){
                    $action = $types[$i];
                }elseif($i==1){
                    $method = $types[$i];
                }else{
                    $router_args[] = $types[$i];
                }
            }
        }

        $action = $action ? $action : 'index';
        $method = $method ? $method : 'index';

        $dispatch = array($group,$action,$method,$router_args);
        $this->addLog('ecore dispatch::'.var_export($dispatch,true));

        $result = $this->hook('app_dispatch_suffix',$dispatch);
        if(is_array($result) && count($result)==4){
            $dispatch = $result;
            $this->addLog(' hook dispatch::'.var_export($dispatch,true));
        }
        return $this->dispatch = $dispatch;
    }

    /**
     * Router 解析(返回 pathinfo)
     */
    public function router(){
        if (NULL != $this->router) {
            return $this->router;
        }
        $this->hook('app_router_prefix');
        //参考Zend Framework对pahtinfo的处理, 更好的兼容性
        $pathInfo = NULL;
        if(isset($_SERVER['PATH_INFO'])){
            $pathInfo = $_SERVER['PATH_INFO'];
        }
        //处理requestUri
        $requestUri = NULL;
        if (isset($_SERVER['HTTP_X_REWRITE_URL'])) { // check this first so IIS will catch
            $requestUri = $_SERVER['HTTP_X_REWRITE_URL'];
        } elseif (isset($_SERVER['IIS_WasUrlRewritten']) && $_SERVER['IIS_WasUrlRewritten'] == '1' && isset($_SERVER['UNENCODED_URL']) && $_SERVER['UNENCODED_URL'] != '') { // IIS7 with URL Rewrite: make sure we get the unencoded url (double slash problem)
            $requestUri = $_SERVER['UNENCODED_URL'];
        } elseif (isset($_SERVER['REQUEST_URI'])) {
            $requestUri = $_SERVER['REQUEST_URI'];
            if (isset($_SERVER['HTTP_HOST']) && strstr($requestUri, $_SERVER['HTTP_HOST'])) {
                $parts       = @parse_url($requestUri);
                if (false !== $parts) {
                    $requestUri  = (empty($parts['path']) ? '' : $parts['path'])
                        . ((empty($parts['query'])) ? '' : '?' . $parts['query']);
                }
            }
        } elseif (isset($_SERVER['ORIG_PATH_INFO'])) { // IIS 5.0, PHP as CGI
            $requestUri = $_SERVER['ORIG_PATH_INFO'];
            if (!empty($_SERVER['QUERY_STRING'])) {
                $requestUri .= '?' . $_SERVER['QUERY_STRING'];
            }
            //} else {
            //    return '/';
        }

        //处理baseUrl
        $filename = (isset($_SERVER['SCRIPT_FILENAME'])) ? basename($_SERVER['SCRIPT_FILENAME']) : '';
        // Backtrack up the script_filename to find the portion matching
        // php_self
        $path    = isset($_SERVER['PHP_SELF']) ? $_SERVER['PHP_SELF'] : '';
        //$file    = isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '';
        $file    = str_replace('\\','/',isset($_SERVER['SCRIPT_FILENAME']) ? $_SERVER['SCRIPT_FILENAME'] : '');
        $segs    = explode('/', trim($file, '/'));
        $segs    = array_reverse($segs);
        $index   = 0;
        $last    = count($segs);
        $baseUrl = '';
        do {
            $seg     = $segs[$index];
            $baseUrl = '/' . $seg . $baseUrl;
            ++$index;
        } while (($last > $index) && (false !== ($pos = strpos($path, $baseUrl))) && (0 != $pos));
        if(!$baseUrl){
            if (isset($_SERVER['SCRIPT_NAME']) && basename($_SERVER['SCRIPT_NAME']) === $filename) {
                $baseUrl = $_SERVER['SCRIPT_NAME'];
            } elseif (isset($_SERVER['PHP_SELF']) && basename($_SERVER['PHP_SELF']) === $filename) {
                $baseUrl = $_SERVER['PHP_SELF'];
            } elseif (isset($_SERVER['ORIG_SCRIPT_NAME']) && basename($_SERVER['ORIG_SCRIPT_NAME']) === $filename) {
                $baseUrl = $_SERVER['ORIG_SCRIPT_NAME'];
            }
        }
        // Does the baseUrl have anything in common with the request_uri?
        $finalBaseUrl = NULL;
        if (0 === strpos($requestUri, $baseUrl)) {
            // full $baseUrl matches
            $finalBaseUrl = $baseUrl;
        } else if (0 === strpos($requestUri, dirname($baseUrl))) {
            // directory portion of $baseUrl matches
            $finalBaseUrl = rtrim(dirname($baseUrl), '/');
        } else if (!strpos($requestUri, basename($baseUrl))) {
            // no match whatsoever; set it blank
            $finalBaseUrl = '';
        } else if ((strlen($requestUri) >= strlen($baseUrl)) && ((false !== ($pos = strpos($requestUri, $baseUrl))) && ($pos !== 0))){
            // If using mod_rewrite or ISAPI_Rewrite strip the script filename
            // out of baseUrl. $pos !== 0 makes sure it is not matching a value
            // from PATH_INFO or QUERY_STRING
            $baseUrl = substr($requestUri, 0, $pos + strlen($baseUrl));
        }


        $finalBaseUrl = (NULL === $finalBaseUrl) ? rtrim($baseUrl, '/') : $finalBaseUrl;
        // Remove the query string from REQUEST_URI
        if ($pos = strpos($requestUri, '?')) {
            $requestUri = substr($requestUri, 0, $pos);
        }

        if ((NULL !== $finalBaseUrl) && (false === ($pathInfo = substr($requestUri, strlen($finalBaseUrl))))) {
            // If substr() returns false then PATH_INFO is set to an empty string
            $pathInfo = '/';
        } elseif (NULL === $finalBaseUrl) {
            $pathInfo = $requestUri;
        }

        if (!empty($pathInfo)) {
            //针对iis的utf8编码做强制转换
            //参考http://docs.moodle.org/ja/%E5%A4%9A%E8%A8%80%E8%AA%9E%E5%AF%BE%E5%BF%9C%EF%BC%9A%E3%82%B5%E3%83%BC%E3%83%90%E3%81%AE%E8%A8%AD%E5%AE%9A
            if ((stripos($_SERVER['SERVER_SOFTWARE'], 'Microsoft-IIS') !== false || stripos($_SERVER['SERVER_SOFTWARE'], 'ExpressionDevServer') !== false) && function_exists('mb_convert_encoding')) {
                $pathInfo = mb_convert_encoding($pathInfo, 'UTF-8', 'auto');
            }
        } else {
            $pathInfo = '/';
        }
        // fix issue 456
        $pathInfo = '/' . ltrim(urldecode($pathInfo), '/');
        $params = parse_url($pathInfo);
        $pathInfo = isset($params['path']) ? $params['path'] : '';
        if(isset($params['query'])){
            parse_str($params['query'],$query);
        }else{
            $query = array();
        }
        $_GET = array_merge($query,$_GET);
        $url_suffix = $this->config('app.url_suffix','');
        $url_suffix = $url_suffix && strpos($url_suffix,'.')!==0 ? ".$url_suffix" : $url_suffix;
        if($url_suffix && $pathInfo && strtolower($url_suffix) == strtolower(substr($pathInfo, -strlen($url_suffix)))){
            $pathInfo  = substr($pathInfo, 0,-strlen($url_suffix));
        }
        $this->addLog(sprintf("ecore router:%s",$pathInfo));
        $result = $this->hook('app_router_suffix',array('router'=>$pathInfo));
        if(is_string($result) && strlen($result)>0){
            $pathInfo = $result;
            $this->addLog(sprintf(" hook router:%s",$pathInfo));
        }
        return $this->router = $pathInfo;
    }

    /**
     * 增加日志
     * @param [type] $msg  [description]
     * @param string $type [description]
     */
    public function addLog($msg,$type="info"){
        if($this->config('app.logs',true)){
            $this->app_logs[] = array(microtime(true),$type,$msg);
        }
        return true;
    }

    /**
     * 自动加载回调
     * @param  [type] $class_name [description]
     * @return [type]             [description]
     */
    public function autoloadHandler($class_name){
        if(class_exists($class_name,false) || interface_exists($class_name,false)){
            return true;
        }
        $class_file = sprintf("%s.php",str_replace("\\", '/', $class_name));
        return $this->import($class_file);
    }


    /**
     * 错误回调
     */
    public function errorHandler($no,$msg,$file,$line){
        if(error_reporting()==0 || !$this->config('app.debug',false) || $no == E_NOTICE ){
            $this->addLog("[$no] $msg in $file on $line",'error');
            return;
        }else{
            throw new ErrorException($msg,$no,1,$file,$line);
        }
    }

    /**
     * 异常回调
     */
    public function exceptionHandler($exception){
        $log_msg = sprintf(" %s on %s in %s",$exception->getMessage(),$exception->getFile(),$exception->getLine());
        $this->addLog($log_msg,'exception');
        $app_exception_handler = $this->config('app.exception_handler');

        if($app_exception_handler && is_callable($app_exception_handler)){
            return call_user_func_array($app_exception_handler,func_get_args());
        }else{
            while(ob_get_level()!=0){
                ob_end_clean();
            }
            if($this->config('app.debug',false)){
                require __DIR__.'/vendor/notice.php';// $this->fpath('ecore/tmps/notice.php');
            }else{
                $error_code = $exception->getCode();
                $status = $error_code == 404 ? "404 Not Found!" : "500 Internal Server Error";
                header("Status:$status");
                $app_code_error_file = $this->fpath(sprintf("core/error_%s.php",$error_code));
                $app_error_file = $this->fpath("core/error.php");
                if(file_exists($app_code_error_file)){
                    require $app_code_error_file;
                }elseif(file_exists($app_error_file)){
                    require $app_error_file;
                }else{
                    echo "<!DOCTYPE html><html lang='zh_CN'><head><title>$status</title><style>
                        body {width: 35em;margin: 0 auto;font-family: Tahoma, Verdana, Arial, sans-serif;}
                        </style></head><body>
                        <h1>$status</h1></body></html>";
                }
            }
        }
    }

    /**
     * php中止时执行回调
     */
    public function shutdownHandler(){
        $error = error_get_last();
        if(is_array($error) && in_array($error['type'],array(E_ERROR,E_PARSE,E_CORE_ERROR,E_CORE_WARNING,E_COMPILE_ERROR,E_COMPILE_WARNING))){
            $this->exceptionHandler(new ErrorException($error['message'],$error['type'],0,$error['file'],$error['line']));
        }
        $this->saveLog();
    }


    /**
     * 保存日志（php中止会自动处理）
     * @return [type] [description]
     */
    private function saveLog(){
        if($this->config('app.logs',true) && count($this->app_logs)>0){
            $log_file = $this->fpath(sprintf("runtime/logs/%s.log",date('Y_m_d')));
            $log_content[] = sprintf("%s",str_repeat("===", 30));
            $log_content[] = sprintf("[%s] %s => %s",date('Y-m-d H:i:s',ECORE_APP_START_TIME),(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : ''),(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : ''));
            foreach($this->app_logs as $info){
                if(is_array($info) && count($info)==3){
                    list($time,$type,$msg) = $info;
                    list($prefix,$suffix) = explode('.',$time,2);
                    $msg = !is_string($msg) ? var_export($msg,true) : $msg;
                    $log_content[] = sprintf("[%s:%s][%s]%s",date('Y-m-d H:i:s',$prefix),$suffix,$type,$msg);
                }
            }
            @file_put_contents($log_file,implode("\n",$log_content)."\r\n\n",FILE_APPEND);
        }
    }
}