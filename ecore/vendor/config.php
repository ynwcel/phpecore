<?php
return array(
    'app'=>array(
        "debug"=>false,
        "logs"=>true,
        "timezone"=>"PRC",
        "exception_handler"=>array(),

        "session_auto_start"=>true,
        "session_save_name"=>"",
        "session_save_handler"=>"",
        "session_save_path"=>"",
        

        "url_modules"=>array(),
        "url_base"=>"/",
        "url_script"=>"index.php",
        "url_split"=>"/",
        "url_suffix"=>".html",
        "url_rewrite"=>true,
        "url_rules"=>array(
            # 格式为 
            # '/article/:id'=>'home/index/read/:id',
            # '/news/:act/:act_id'=>'home/index/show/:act/:act_id',
        ),
    ),
    'daos'=>array(
        "main"=>array(
            "dsn"=>"",
            "username"=>"",
            "password"=>"",
            "table_prefix"=>"",
            "charset"=>"utf8",
        ),
    ),
    'redis'=>array(
        "main"=>array(
            "host"=>"",
            "port"=>"",
            "password"=>"",
            "timeout"=>1,
            "database"=>0,
        ),
    ),
    'params'=>array(),
);