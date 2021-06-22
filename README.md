**勿投入生产环境**

**勿投入生产环境**

**勿投入生产环境**

---

## phpecore

#### 介绍
phpecore 是简易mvc个人练习框架

#### 框架目录结构
```php
├── composer.json
├── composer.lock
├── ecore
│   ├── App.php
│   ├── bin
│   │   ├── AbsBase.php
│   │   ├── Action.php
│   │   ├── Dao.php
│   │   ├── Model.php
│   │   └── Redis.php
│   ├── util
│   │   ├── Request.php
│   │   ├── Response.php
│   │   └── Session.php
│   └── vendor
│       ├── config.php
│       └── notice.php
└── README.md
```

#### 项目目录结构 
```php
├── app
│   └── home
├── core
│   ├── bin
│   ├── config.php
│   └── util
├── public
│   └── index.php
├── runtime
│   ├── logs
│   └── datas
└── template
    ├── home
    └── __layouts
```

#### 开发规范
1. 使用命名空间 **ecore\\**
2. 文件命统一使用 **.php** 后缀
3. 配置文件/函数库文件/常量定义文件 名 **全部字母** **小写**
4. 类 **首字母** **大写**
3. 

#### 执行流程
1. index.php 定义 WEB\_ROOT 路径、【可选：定义 APP\_ROOT路径】）
2. index.php 载入 ecore\App类单一实例 (ecore\App::load())
3. ecore\App 载入默认配置(ecore\vendor\config.php)
4. ecore\App 合并项目配置(core\config.php)
5. ecore\App 配置环境(设置运行模式，session是否开启等）
6. ecore\App 载入项目常量定义文件（core\define.php)
7. ecore\App 载入项目函数库文件（core\common.php)
8. ecore\App 载入项目引导文件(core/bootstrap.php)
9. index.php 项目运行（ecore\App->run())
10. ecore\App 解析路由 (ecore\App->router())
11. ecore\App 路由调度 (ecore\App->dispatch())
12. ecore\App 实例化 Action 类 
13. ecore\App 调用 Action 实例方法
14. ecore\App 返回

#### 项目回调处理
```
   \core\bin\AppHook 
```
1. app_router_prefix
2. app_router_suffix
3. app_dispatch_prefix
4. app_dispatch_suffix
5. app_action_prefix
6. app_action_suffix

### Apis
#### ecore\App
```php
public static final function load()

public function __get($var)
public function __set($var,$val=null)
public function hook($name,$args=array())
public function getWebRoot()
public function getAppRoot()
public function getAppName()
public function alias($alias)
public function fpath($_file)
public function import($_file)
public function config($key=null,$default=null,$empty_val = true)
public function run()
public function url($url,$params=array())
public function dispatch()
public function router()
public function addLog($msg,$type="info")
public function autoloadHandler($class_name)
public function errorHandler($no,$msg,$file,$line)
public function exceptionHandler($exception)
public function shutdownHandler()
```
#### ecore\bin\AbsBase

```php
public function __get($var)
public function __set($var,$val=null)
public function __call($method,$args=array())

public function loadApp()
public function loadDao($tag=null,$args=array())
public function loadModel($tag=null,$args=array())
public function loadRedis($tag=null,$args=array())

public function dump($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE)
public function xmp($var, $echo = true, $label = null, $flags = ENT_SUBSTITUTE)
```

#### echore\bin\Action (extends AbsBase)

```php
public function input($key,$default=null,$filter=null)
    
public function assign($var,$val=null)
    
public function tempate($template,$datas=array())
public function fetch($template,$datas=array(),$apply_layout=true)
public function display($template,$datas=array(),$apply_layout=true,$code=200,$content_type="text/html",$charset='utf-8')
    
public function success($msg,$url=null,$code=200,$content_type="text/html",$charset='utf-8')
public function error($msg,$url=null,$code=200,$content_type="text/html",$charset='utf-8')
public function ajaxReturn($flag,$data=null,$msg='',$code=200,$content_type="application/json",$charset='utf-8')
public function json($datas=array(),$code=200,$content_type="application/json",$charset='utf-8')
    
public function redirect($url,$code=302,$content_type="text/html",$charset='utf-8')
public function formatTemplate($template)
```

#### ecore\bin\Dao(extends AbsBase)

```php
public function __construct($args=array())
public function __call($method,$args=array())

public function handler()

public function table/fields/group/order/limit/where/having()
public function count/max/min/sum/avg/findBy/columns()   

public function begin()
public function startTransaction()
public function commit()
public function rollback()

public function insert($data,$insert_id=false)
public function insertAll($datas)
public function update($data)
public function delete()

public function prepare($command,$params=array())
public function execute()
public function exec()

public function fetchOne()
public function fetchRow()
public function fetchMap($key_field,$val_field=null)
public function fetchAll()
public function fetchPage($page_index=1,$page_size=30)

public function buildCommand()
public function getLastError()
public function getLastCommand()
```

#### ecore\bin\Model

```php
public function __construct($args=array())
```

#### ecore\bin\Redis

```php
public function __construct($args=array())
public function __call($method,$args=array())

public function handler()

public function has($key)    
public function get($key, $default = false)
public function set($key, $value, $expire = null
public function inc($key, $step = 1)
public function dec($key, $step = 1)
public function rm($key)
```

#### ecore\util\Request

```php
public final static function load()

public function scheme()
public function host($strict = false)
public function domain()
public function absUrl($domain=true)
public function clientIp() 
public function method($method = false)
    
public  function isGet()
public  function isPost()
public  function isPut()
public  function isDelete()
public  function isHead()
public  function isPatch()
public  function isOptions()
public  function isCli()
public  function isCgi()
public  function isSsl()
public function isAjax()
public function isMobile()
    
public function env($name=null,$filter='stripslashes')
public function get($name=null,$filter='stripslashes')
public function post($name=null,$filter='stripslashes')
public function cookie($name=null,$filter='stripslashes')
public function session($name=null,$filter='stripslashes')
public function header($name=null,$filter="stripslashes")
public function server($name=null,$filter='stripslashes')
public function param($name=null,$filter='stripslashes')
public function input($name = '' ,$data = array() , $filter = 'stripslashes')
    
public function filterValue(&$value, $key, $filters=array())
```

#### ecore\util\Response

```php
public final static function load()

public function withHeader($headers=array())

public function addHeader($name, $value = null)
public function setCode($code=200)
public function setContent($content='')
public function setContentType($content_type='text/html')
public function setCharset($charset='utf-8')
public function getHeader()
public function getContent()
public function getContentType()
public function getCharset()
public function getCode()
public function send($content=null,$code=null,$content_type=null,$charset=null)
```

#### ecore\util\Session

```php
public static function load()

public static function start()
public static function set($name,$val=null)
public static function get($name)
public static function pull($name)
public static function delete($name)
public static function flush()
public static function destroy()
```

