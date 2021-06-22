<?php
namespace ecore\bin;

use ecore\App;
use ecore\util\Request;

abstract class AbsBase{
    private static $loaded = array();

    protected function __construct(){
        
    }

    public function __get($var){
        return $this->loadApp()->$var;
    }

    public function __set($var,$val=null){
        return $this->loadApp()->$var = $val;
    }

    public function __call($method,$args=array()){
        $app = $this->loadApp();
        if(method_exists($app, $method) && is_callable(array($app,$method))){
            return call_user_func_array(array($app,$method), $args);
        }else{
            $this_cls = get_class($this);
            throw new \Exception("Not Found Method:<$this_cls::$method()>",500);
        }
    }

    /**
     * @return \ecore\App
     */
    public function loadApp(){
        return App::load();
    }

    /**
     * @param null $tag
     * @param array $args
     * @return \ecore\bin\Dao
     */
    public function loadDao($tag=null,$args=array()){
        $call_class = "\\ecore\\bin\\Dao";
        $call_config = is_array($args) ? $args : array();

        if($tag){
            $app_dao_class = sprintf("\\core\\dao\\%s",$this->loadApp()->alias($tag));
            if(class_exists($app_dao_class) && is_subclass_of($app_dao_class, $call_class)){
                $call_class = $app_dao_class;
            }
            $call_config = array_merge($this->loadApp()->config("daos.$tag",array(),false),$call_config);
        }

        $loaded_key = sprintf("daos.%s.%s",$call_class,md5(serialize($call_config)));
        if(!isset(self::$loaded[$loaded_key]) || !self::$loaded[$loaded_key] instanceof $call_class){
            self::$loaded[$loaded_key] = new $call_class($call_config);
        }
        return self::$loaded[$loaded_key];
    }


    /**
     * @param null $tag
     * @param array $args
     * @return \ecore\bin\Model
     */
    public function loadModel($tag=null,$args=array()){
        $base_class = "\\ecore\\bin\\Model";
        $call_class = sprintf("\\core\\model\\%s",$this->loadApp()->alias($tag));

        $loaded_key = sprintf("model.%s.%s",$call_class,md5(serialize($args)));
        if(!isset(self::$loaded[$loaded_key]) || !self::$loaded[$loaded_key] instanceof $base_class){
            if(!$tag ||  !class_exists($call_class) || !is_subclass_of($call_class, $base_class)){
                throw new Exception("Not Found Model Class:<$call_class>", 500);
            }
            self::$loaded[$loaded_key] = new $call_class($args);
        }
        return self::$loaded[$loaded_key];
    }

    /**
     * @param null $tag
     * @param array $args
     * @return \ecore\bin\Redis
     */
    public function loadRedis($tag=null,$args=array()){
        $call_class = "\\ecore\\bin\\Redis";
        $call_config = is_array($args) ? $args : array();

        if($tag){
            $app_redis_class = sprintf("\\core\\redis\\%s",$this->loadApp()->alias($tag));
            if(class_exists($app_redis_class) && is_subclass_of($app_redis_class, $call_class)){
                $call_class = $app_redis_class;
            }
            $call_config = array_merge($this->loadApp()->config("redis.$tag",array(),false),$call_config);
        }

        $loaded_key = sprintf("redis.%s.%s",$call_class,md5(serialize($call_config)));
        if(!isset(self::$loaded[$loaded_key]) || !self::$loaded[$loaded_key] instanceof $call_class){
            self::$loaded[$loaded_key] = new $call_class($call_config);
        }
        return self::$loaded[$loaded_key];
    }

    public function dump($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE){
        $label = (null === $label) ? '' : rtrim($label) . ':';
        ob_start();
        var_dump($var);
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', ob_get_clean());

        if (Request::load()->isCli()) {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, $flags);
            }

            $output = '<pre>' . $label . $output . '</pre>';
        }

        if ($echo) {
            echo($output);
            return;
        }

        return $output;
    }

    public function xmp($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE){
        $label = (null === $label) ? '' : rtrim($label) . ':';
        $output = preg_replace('/\]\=\>\n(\s+)/m', '] => ', print_r($var,true));

        if (Request::load()->isCli()) {
            $output = PHP_EOL . $label . $output . PHP_EOL;
        } else {
            if (!extension_loaded('xdebug')) {
                $output = htmlspecialchars($output, $flags);
            }

            $output = '<pre>' . $label . $output . '</pre>';
        }

        if ($echo) {
            echo($output);
            return;
        }

        return $output;
    }
}
