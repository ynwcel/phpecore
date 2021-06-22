<?php
namespace ecore\util;

class Response{
    protected static $instance = null;

    protected $content = null;
    protected $charset = 'utf-8';
    protected $content_type = null;
    protected $code = 200;
    protected $headers = array();

    public function __construct(){}

    public final static function load(){
        if(!self::$instance instanceof self){
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function withHeader($headers=array()){
        if(is_array($headers)){
            foreach($headers as $key=>$val){
                $this->addHeader($key,$val);
            }
        }
        return $this;
    }


    public function addHeader($name, $value = null){
        if (is_array($name)) {
            $this->headers = array_merge($this->headers, $name);
        } else {
            $this->headers[$name] = $value;
        }
        return $this;
    }

    public function setCode($code=200){
        $this->code = $code;
        return $this;
    }


    public function setContent($content=''){
        $this->content = $content;
        return $this;
    }

    public function setContentType($content_type='text/html'){
        $this->content_type = $content_type;
        return $this;
    }

    public function setCharset($charset='utf-8'){
        $this->charset = $charset;
        return $this;
    }



    public function getHeader(){
        return $this->headers;
    }

    public function getContent(){
        return $this->content;
    }


    public function getContentType(){
        return $this->content_type;
    }


    public function getCharset(){
        return $this->charset;
    }


    public function getCode(){
        return $this->code;
    }


    public function send($content=null,$code=null,$content_type=null,$charset=null){
        $content = $content ? $content : $this->content;
        $code = $code ? $code : $this->code;
        $content_type = $content_type ? $content_type : $this->content_type;
        $charset = $charset ? $charset : $this->charset;


        if (!headers_sent()) {
            // 发送状态码
            if(function_exists('http_response_code')){
                http_response_code($code);
            }else{
                header('Status:'.$code);
            }
            // 发送头部信息
            foreach ($this->headers as $name => $val) {
                if (is_null($val)) {
                    header($name);
                } else {
                    header($name . ':' . $val);
                }
            }
        }
        header(sprintf("Content-type:%s;Charset=%s",$content_type,$charset));
        echo $content;

        if (function_exists('fastcgi_finish_request')) {
            // 提高页面响应
            fastcgi_finish_request();
        }
        return $this;
    }
}