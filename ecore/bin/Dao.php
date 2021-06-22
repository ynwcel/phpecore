<?php
namespace ecore\bin;
use Exception;

class Dao extends AbsBase{

    protected $config = array();

    private $handler = null;
    
    private $columns = array();
    private $options = array();
    private $commands = array();
    private $errors = array();

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

    public function handler(){
        if(!$this->handler instanceof \PDO){
            $config = $this->config;
            $dao_dsn = isset($config['dsn']) ? $config['dsn'] : '';
            $user = isset($config['username']) ? $config['username'] : '';
            $password = isset($config['password']) ? $config['password'] : '';
            $attrs = array(
                \PDO::ATTR_TIMEOUT=>3,
                \PDO::ATTR_ERRMODE=>\PDO::ERRMODE_EXCEPTION,
                \PDO::MYSQL_ATTR_USE_BUFFERED_QUERY=>true,
                \PDO::ATTR_DEFAULT_FETCH_MODE=>\PDO::FETCH_OBJ,
            );
            $this->handler = new \Pdo($dao_dsn,$user,$password,$attrs);
            $this->handler->exec(sprintf("set names %s",isset($this->config['charset']) ? $this->config['charset'] : 'utf8'));
        }
        return $this->handler;
    }

    public function __call($method,$args=array()){
        $this_cls = get_class($this);
        $this_handler = $this->handler();

        if($method == 'table'){
            $table = array_shift($args);
            if(strpos($table,'{{') !== 0){
                $table_prefix = isset($this->config['table_prefix']) ? $this->config['table_prefix'] : '';
                if($table_prefix && strpos($table,$table_prefix)===0){
                    $table = substr($table,strlen($table_prefix));
                }
            }
            $this->options['table'] = sprintf("{{%s}}",trim($table,"{}"));
            return $this;
        }elseif(isset($this->options['table']) && in_array($method,array('fields','group','order','limit','where','having','count','max','min','sum','avg'))){
            if(in_array($method,array('fields','group','order','limit'))){
                if(count($args)<1){
                    throw new \Exception("Invalid Method Params:<$this_cls::$method()>",500);
                }

                $args = count($args) == 1 && is_string($args[0]) ? explode(',', $args[0]) : $args;

                if(strtolower($method)=='fields' || strtolower($method)=='group'){
                    $fields = array();
                    foreach($args as $field){
                        $f = trim($field);
                        $fields[] = preg_match("/^\w+$/", $f) ?  "`$f`" : $f;
                    }
                    $this->options[strtolower($method)] = implode(',',$fields);
                }elseif(strtolower($method)=='limit'){
                    $limit[] = intval($args[0]);
                    $limit[] = isset($args[1]) ? intval($args[1]) : "";
                    $this->options['limit'] = trim(implode(",",$limit),',');
                }elseif(strtolower($method)=='order'){
                    $orders = array();
                    foreach($args as $arg){
                        $info = explode(" ",trim($arg));
                        if(count($info)==1){
                            $info[1] = 'asc';
                        }
                        $orders[]=sprintf("%s %s",(preg_match("/^\w+$/", $info[0]) ?  "`$info[0]`" : $info[0]), $info[1]);
                    }
                    $this->options['order'] = implode(',',$orders);
                }
                return $this;
            }elseif(strtolower($method)=='where' || strtolower($method)=='having'){
                list($where_str,$params) = call_user_func_array(array($this,'buildWhere'),$args);
                list($option_key1,$option_key2) = array(strtolower($method),strtolower(sprintf("%s_params",$method)));
                $where = substr(trim($where_str),5);
                if(isset($this->options[$option_key1]) && $this->options[$option_key1]){
                    $this->options[$option_key1] = sprintf("%s and %s",$this->options[$option_key1],$where);
                }else{
                    $this->options[$option_key1] = $where;
                }
                if(isset($this->options[$option_key2]) && is_array($this->options[$option_key2])){
                    $this->options[$option_key2] = array_merge($this->options[$option_key2],array_values($params));
                }else{
                    $this->options[$option_key2] = $params;
                }
                return $this;

                /*if(!is_array($args[0])){
                    $args = array($args);
                }
                foreach($args as $argv){
                    list($where_str,$params) = call_user_func_array(array($this,'buildWhere'),$argv);//$this->parseWhere($args);
                    if($where_str){
                        $where = substr(trim($where_str),5);
                        list($option_key1,$option_key2) = array(strtolower($method),strtolower(sprintf("%s_params",$method)));
                        if(isset($this->options[$option_key1]) && $this->options[$option_key1]){
                            $this->options[$option_key1] = sprintf("%s and %s",$this->options[$option_key1],$where);
                        }else{
                            $this->options[$option_key1] = $where;
                        }
                        if(isset($this->options[$option_key2]) && is_array($this->options[$option_key2])){
                            $this->options[$option_key2] = array_merge($this->options[$option_key2],array_values($params));
                        }else{
                            $this->options[$option_key2] = $params;
                        }
                    }
                }*/
                return $this;
            }elseif(in_array(strtolower($method),array('count','max','min','sum','avg'))){
                $arg = array_shift($args);
                $arg = strtolower($method) == 'count' && !$arg ? '*' : $arg;
                if(!$arg){
                    throw new \Exception("Invalid Method Params:<$this_cls::$method()>",500);
                }
                $field = sprintf("%s(%s)",strtolower($method),$arg);
                return $this->fields($field)->fetchOne();
            }
        }elseif(isset($this->options['table']) && strpos($method,'findBy')===0) {
            $field = strtolower(substr($method, 6));
            $arg = array_shift($args);
            return $this->where("$field = ?", $arg)->fetchRow();
        }elseif(isset($this->options['table']) && $method == 'columns') {
            $table = $this->options['table'];
            unset($this->options['table']);
            if (!isset($this->columns[$table])) {
                $command = sprintf("show columns from %s", $table);
                $this->columns[$table] = $this->prepare($command)->fetchMap('Field');
            }
            return $this->columns[$table];
        }elseif($method == "buildWhere"){            
            list($where,$params) = array('',array());
            if(count($args)==1){
                if(is_string($args[0])){  
                    //buildWhere('id=3')                                          
                    list($where,$params) = $this->parseWhere(array($args[0]));  
                }elseif(is_array($args[0]) && count($args[0])>=1){  
                    if(is_array($args[0][0])){  
                        //buildWhere(['id=3'])
                        //buildWhere([['id=3']])
                        //buildWhere([['id=3'],['online>=?','2020-01-01']])              
                        $_wheres = $_params = array();
                        foreach($args[0] as $argv){
                            $p_argv = is_array($argv) ? $argv : array($argv);
                            list($_w,$_p) = $this->parseWhere($p_argv);
                            if($_w){
                                $_wheres[] = $_w;
                                $_params = array_merge($_params,array_values($_p));
                            }
                        }
                        list($where,$params) = array(implode(" and ", $_wheres),$_params);                  
                    }else{
                        //buildWhere(['id=? and pid in (?,?)',1,2,3])
                        list($where,$params) = $this->parseWhere($args[0]);; 
                    }
                }
            }elseif(count($args)>0){
                if(is_array($args[0])){
                    //buildWhere(['id=3'],['online>=?','2020-01-01'],['pid','in',[1,2,3]])
                    $_wheres = $_params = array();
                    foreach($args as $argv){
                        list($_w,$_p) = $this->parseWhere($argv);
                        if($_w){
                            $_wheres[] = $_w;
                            $_params = array_merge($_params,array_values($_p));
                        }
                    }
                    list($where,$params) = array(implode(" and ", $_wheres),$_params);
                }else{
                    //buildWhere('id=3 and online>=? and pid in (?,?,?)','2020-01-01',1,2,3)
                    list($where,$params) = $this->parseWhere($args);
                }
            }
            $where_str = $where ? sprintf(" where $where") : $where;
            return array($where_str,$params);
        }elseif(is_callable(array($this_handler,$method))) {
            return call_user_func_array(array($this_handler,$method),$args);
        }else{
            return parent::__call($method,$args);
        }
    }

    public function prepare($command,$params=array()){
        if(func_num_args()!=2 || !is_array($params)){
            $_args = func_get_args();
            $command = array_shift($_args);
            $params = $_args;
        }
        $this->options['prepare']['sql'] = $command;
        $this->options['prepare']['params'] = $params;
        return $this;
    }

    public function begin(){
        return $this->handler()->beginTransaction();
    }
    public function startTransaction(){
        return $this->handler()->beginTransaction();
    }
    public function commit(){
        return $this->handler()->commit();
    }
    public function rollback(){
        return $this->handler()->rollBack();
    }

    public function insert($data,$insert_id=false){
        $table = isset($this->options['table']) ? $this->options['table'] : '';
        $columns = array_change_key_case(array_keys($this->table($table)->columns()));
        $all_key = $all_val = $params =  array();
        foreach($data as $key=>$val){
            if(!in_array(strtolower($key),$columns)){
                continue;
            }
            $all_key[] = "`$key`";
            if(is_array($val)){                         // 'nums'=>array('nums+?+?',3,2)
                $_data1 = array_shift($val);
                $all_val[] = $_data1 ? $_data1 : '?';
                while(count($val)>0){
                    $params[] = array_shift($val);
                }
            }else{
                $all_val[] = "?";
                $params[] = $val;
            }
        }
        $command = sprintf("insert into  $table (%s) values (%s)",implode(",",$all_key),implode(",",$all_val));
        unset($this->options['table']);
        if($this->prepare($command, $params)->exec()){
            $last_insert_id = $this->handler()->lastInsertId();
            if($insert_id && $last_insert_id){
                return $last_insert_id;
            }else{
                return true;
            }
        }else{
            return false;
        }
    }

    public function insertAll($datas){
        $_datas = array_values($datas);
        if(!isset($_datas[0]) || !is_array($_datas[0])){
            throw new \Exception("Invalid Method Params:<$this_cls::insertAll()>",500);
        }

        $table = isset($this->options['table']) ? $this->options['table'] : '';
        $columns = array_change_key_case(array_keys($this->table($table)->columns()));
        $_datas_first = array_change_key_case($_datas[0]);
        //$exists_fields = array_unique(array_intersect($columns,array_keys($_datas_first)));

        $exists_fields = array();
        foreach(array_keys($_datas_first) as $field){
            if(in_array($field, $columns)){
                $exists_fields[] = $field;
            }
        }

        $values = $params = array();
        foreach($_datas as $data){
            $value_item = array();
            foreach($data as $key=>$val){
                if(in_array(strtolower($key),$exists_fields)){
                    $value_item[] = "?";
                    $params[] = $val;
                }
            }
            $values[] = sprintf("(%s)",implode(',', $value_item));
        }
        $command = sprintf("insert into  $table (%s) values %s",implode(",",$exists_fields),implode(",",$values));
        unset($this->options['table']);
        return $this->prepare($command, $params)->exec();
    }

    public function update($data){
        $this_cls = get_class($this);
        $table = isset($this->options['table']) ? $this->options['table'] : '';
        $where = isset($this->options['where']) ? $this->options['where'] : '';
        $param = isset($this->options['where_params']) ? $this->options['where_params'] : array();
        if( !$where ){
            throw new \Exception("Not Found Update Where:<$this_cls::update()>",500);
        }
        $columns = array_change_key_case(array_keys($this->table($table)->columns()));
        $where = $where ? " where $where " : "";
        $command = $values = array();
        foreach($data as $key=>$val){
            if(!in_array(strtolower($key),$columns)){
                continue;
            }
            if(is_array($val)){                         // 'nums'=>array('nums+?+?',3,2)
                $_data1 = array_shift($val);
                $command[] = sprintf("`$key` = %s",$_data1 ? $_data1 : '?');
                while(count($val)>0){
                    $values[] = array_shift($val);
                }
            }else{
                $command[] = "`$key`= ? ";
                $values[] = $val;
            }
        }
        foreach($param as $val){
            $values[] = $val;
        }
        $command = sprintf("update $table  set %s %s ",implode(',', $command),$where);
        unset($this->options['table']);
        unset($this->options['where']);
        unset($this->options['where_params']);
        return $this->prepare($command,$values)->exec();
    }

    public function delete(){
        $this_cls = get_class($this);
        $table = $this->options['table'];
        $where = isset($this->options['where']) ? $this->options['where'] : '';
        $param = isset($this->options['where_params']) ? $this->options['where_params'] : array();


        if(!$where){
            throw new \Exception("Not Found Update Where:<$this_cls::delete()>",500);
        }
        $where = $where ? " where $where " : "";
        $command = "delete from $table $where ";
        unset($this->options['table']);
        unset($this->options['where']);
        unset($this->options['where_params']);
        return $this->prepare($command,$param)->exec();
    }


    public function execute(){
        return $this->exec();
    }
    public function exec(){
        $result = $this->query();
        if($result){
            return true;
        }
        return false;
    }

    public function fetchOne(){
        $data_obj = $this->fetchRow();
        $data_arr = $data_obj ? (array)$data_obj : array();
        if(is_array($data_arr) && count($data_arr)>=1){
            return array_shift($data_arr);
        }
        return null;
    }

    public function fetchRow(){
        return $this->query('fetch_row');
    }

    public function fetchMap($key_field,$val_field=null){
        $lists = $this->fetchAll();
        $maps = array();
        if(is_array($lists) && $key_field){
            foreach($lists as $row){
                if(isset($row->$key_field)){
                    if(!isset($maps[$row->$key_field])){
                        $maps[$row->$key_field] = $val_field ? (isset($row->$val_field) ? $row->$val_field : null) : $row;
                    }else{
                        throw new \Exception(sprintf("Key Field Value Not Unique<%s>",__METHOD__),500);
                        
                    }
                }
            }
        }
        return $maps;
    }

    public function fetchAll(){
        return $this->query('fetch_all');
    }

    public function fetchPage($page_index=1,$page_size=30){
        $page_index = intval($page_index);
        $page_size = intval($page_size);
        $page_index = $page_index<=1 ? 1 : $page_index;
        $page_size = $page_size ? $page_size : 30;


        list($command,$params) = $this->buildCommand();
        $count_command = sprintf("select count(*) as rows_count from (%s) as fetch_page_table_alias",$command);

        // list($command,$params) = $this->buildCommand();
        // list($command_prefix,$command_suffix) = preg_split("/\s+from\s+/i", $command,2);
        // $count_command = sprintf("select count(*) as rows_count from %s",$command_suffix);

        $info = $this->prepare($count_command,$params)->fetchRow();
        $row_count = doubleval($info->rows_count);

        $result = new \stdClass();
        if(!$row_count || $row_count<=$page_size){
            $result->page_size = $page_size;
            $result->page_index = 1;
            $result->page_count = 1;
            $result->page_datas = $this->prepare($command,$params)->fetchAll();
            $result->rows_count = $row_count;
        }else{
            $page_count = ceil($row_count / $page_size);
            $page_index = $page_index>=$page_count ? $page_count : $page_index;

            $limit_suffix = " limit ".(($page_index-1)*$page_size).",$page_size";
            $page_datas = $this->prepare($command.$limit_suffix,$params)->fetchAll();

            $result->page_index = $page_index;
            $result->page_count = $page_count;
            $result->page_datas = $page_datas;
            $result->page_size = $page_size;
            $result->rows_count = $row_count;
        }
        return $result;
    }

    public function buildCommand(){
        list($sql,$params) = array("",array());
        if(isset($this->options['prepare']) && isset($this->options['prepare']['sql']) && $this->options['prepare']['sql']){
            $sql = $this->options['prepare']['sql'];
            $params = isset($this->options['prepare']['params']) && is_array($this->options['prepare']['params']) ? $this->options['prepare']['params'] : array();
        }elseif(isset($this->options['table']) && isset($this->options['table'])){
            $command = array("select");
            if(isset($this->options['fields']) && $this->options['fields'] ){
                $command[] = $this->options['fields'];
            }else{
                $command[] = " * ";
            }
            $command[] = " from ".(isset($this->options['table']) ? $this->options['table'] : '' );
            if(isset($this->options['where']) && $this->options['where']){
                $command[] = sprintf("where %s",$this->options['where']);
            }
            if(isset($this->options['group']) && $this->options['group']){
                $command[] = " group by ".$this->options['group'];
                if(isset($this->options['having'])){
                    $command[] = "having ".$this->options['having'];
                }
            }
            if(isset($this->options['order']) && $this->options['order']){
                $command[] = " order by  ".$this->options['order'];
            }if(isset($this->options['limit']) && $this->options['limit']){
                $command[] = " limit ".$this->options['limit'];
            }
            $where_params = isset($this->options['where_params']) && is_array($this->options['where_params']) ? $this->options['where_params'] : array();
            $having_params = isset($this->options['having_params']) && is_array($this->options['having_params']) ? $this->options['having_params'] : array();
            $sql = implode(" ",$command);
            $params = array_merge(array_values($where_params),array_values($having_params));
        }

        $this->options = array();
        return array($sql,$params);
    }

    public function getLastError(){
        return end($this->errors);
    }
    public function getLastCommand(){
        return end($this->commands);
    }


    private function query($fetch_type=null){
        $table_prefix = isset($this->config['table_prefix']) ? $this->config['table_prefix'] : '';
        list($query,$params) = $this->buildCommand();

        $table_regexp = '/{{(\w+)}}/';
        $query_command = preg_replace($table_regexp, $table_prefix."\\1", $query);

        $this->commands[] = array($query_command,$params);
        $this->loadApp()->addLog(var_export(array($query_command,$params),true),'sql');

        try{
            $statem = $this->handler()->prepare($query_command);
            $statem->execute($params);
            if($fetch_type){
                $data = new \stdClass();
                switch ($fetch_type){
                    case 'fetch_row':
                        $data = $statem->fetch(\PDO::FETCH_OBJ);
                        break;
                    case 'fetch_all':
                    default:
                        $data = $statem->fetchAll(\PDO::FETCH_OBJ);
                        break;
                }
                return $data;
            }else {
                return $statem;
            }
        }catch(\Exception $err){
            $this->errors[] = array($err->getCode(),$err->getMessage());
            $this->loadApp()->addLog(var_export(end($this->errors),true),'sql');
            if($this->loadApp()->config('app.debug',false)){
                throw $err;
            } else {
                $this->handler()->inTransaction() && $this->rollback();
                return false;
            }
        }
    }

    /**
     * 生成查询条件
     * @param  array  $_wheres [description]
     * @return array(
     *             'where'=>'',
     *             'params'=>array(),
     *         )
     */
    protected function parseWhere($_wheres=array()){
        $wheres = $params = array();
        if(is_array($_wheres)){
            $_arg_val = $_wheres;
            /**
             * 满足如下要求
             * array('id=1')
             * array('id=?',3)
             * array('id=? and type=?',array(3,1));
             */
            if(count($_arg_val)<=2){                    //array('id <> ? and is_show=?',array(3,1))
                $wheres[] = sprintf("( %s )",array_shift($_arg_val));
                $_val_val = count($_arg_val) == 1 && is_array($_arg_val[0]) ? $_arg_val[0] : $_arg_val;
                $params = array_merge($params,array_values($_val_val));
            }else{
                $_val_key = array_shift($_arg_val);
                $_val_op = array_shift($_arg_val);
                $_op = strtolower(trim(preg_replace("/\s+/"," ",$_val_op)));
                $_val_val = count($_arg_val) == 1 && is_array($_arg_val[0]) ? $_arg_val[0] : $_arg_val;
                /**
                 * 满足如下要求
                 * array('id','in',1,2,3,4,5,6)
                 * array('id','in',array(1,2,3,4,6,7))
                 */
                if( $_op == 'in' || $_op == 'not in' ){
                    $wheres[] = sprintf("( %s %s (%s) )",$_val_key,$_op,trim(str_repeat("?,",count($_val_val)),','));
                    $params = array_merge($params,array_values($_val_val));
                /**
                 * 满足如下要求
                 * array('id','between',1,2)
                 * array('id','not between',array(3,4))
                 */
                }elseif(($_op == 'between' || $_op == 'not between')) {
                    $wheres[] = sprintf("( %s %s ? and ? )",$_val_key,$_op);
                    $params[] = array_shift($_val_val);
                    $params[] = array_shift($_val_val);

                /**
                 * 满足如下要求
                 * array('id=? and type<>?',3,1);
                 */
                }else{
                    $wheres[] = sprintf("( %s )",$_val_key);
                    $params[] = $_val_op;
                    $params = array_merge($params,array_values($_val_val));
                }
            }
        }
        return array(implode(" and ", $wheres),$params);
    }
}