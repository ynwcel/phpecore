<?php
namespace ecore\bin;
use Exception;

class Model extends AbsBase{
    protected $config = array();
    
    /**
     * 构造函数
     * @access public
     */
    public function __construct($args=array()){
        parent::__construct();
        if(is_array($args)){
            $this->config = array_merge($this->config,$args);
        }
    }
}