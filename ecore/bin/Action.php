<?php
namespace ecore\bin;

use Exception;
use ecore\util\Request;
use ecore\util\Response;

class Action extends AbsBase{
    protected $view_layout = null;
    protected $view_datas = array();

    public function __construct(){
        
    }

    public function input($key,$default=null,$filter=null){
        $data = $_REQUEST;
        if(strpos($key,'.')>0){
            $_datas = array(
                'env'=>$_ENV,
                'get'=>$_GET,
                'post'=>$_POST,
                'session'=>$_SESSION,
                'cookie'=>$_COOKIE,
                'server'=>$_SERVER,
            );
            list($sub_type,$sub_key) = explode(".",$key,2);
            $sub_type = strtolower($sub_type);
            if(isset($_datas[$sub_type])){
                $data = $_datas[$sub_type];
                $key = $sub_key;
            }
        }
        return Request::load()->input($key,$data,$filter);
    }

    public function assign($var,$val=null){
        if(is_array($var)){
            return $this->view_datas = array_merge($this->view_datas,$var);
        }elseif($var){
            return $this->view_datas[$var] = $val;
        }
    }

    public function template($template,$datas=array()){
        $view_datas = is_array($datas) ? array_merge($this->view_datas,$datas) : $this->view_datas;
        $template = $this->formatTemplate($template);
        $this->loadApp()->addLog("Template::".$template);
        $view_file = $this->loadApp()->fpath($template);
        if(file_exists($view_file)){
            ob_start();
            extract($view_datas);
            require $view_file;
            $view_contents = ob_get_contents();
            ob_end_clean();
            return $view_contents;
        }else{
            throw new Exception("Not Found Template File:<$template>", 500);
        }
    }

    public function fetch($template,$datas=array(),$apply_layout=true){
        $view_group = basename(dirname(str_replace("\\", '/', get_class($this))));
        $template = strpos($template,'/') === false ? sprintf("%s/%s",$view_group,$template) : $template;

        $view_contents = $this->template($template,$datas);
        if($apply_layout && $this->view_layout){
            $datas['layout_subview_content'] = $view_contents;
            $view_layout = strpos($this->view_layout, '/')===false ? sprintf("__layouts/%s",$this->view_layout) : $this->view_layout;
            $view_contents = $this->template($view_layout,$datas);
        }
        return $view_contents;
    }


    public function display($template,$datas=array(),$apply_layout=true,$code=200,$content_type="text/html",$charset='utf-8'){
        $content = $this->fetch($template,$datas,$apply_layout);
        return Response::load()->send($content,$code,$content_type,$charset);
    }


    public function success($msg,$url=null,$code=200,$content_type="text/html",$charset='utf-8'){
        $datas = array(
            'type'=>'success',
            'msg'=>$msg,
            'url'=>$url ? $url : $_SERVER['HTTP_REFERER'],
        );
        if(Request::load()->isAjax()){
            return $this->ajaxReturn(true,$datas,$msg);
        }else{
            $dispatch_jump_tpl = "success_error.php";
            if(file_exists($this->loadApp()->fpath($this->formatTemplate($dispatch_jump_tpl)))){
                $content = $this->template($dispatch_jump_tpl,$datas);
            }else{
                $tpl_js = $datas['url'] ? sprintf('window.location.href="%s";',$datas['url']) : 'window.history.go(-1);';
                $tpl_msg='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Success!!!</title><script type="text/javascript">window.onload=function(){alert("Success!!\n%s");%s;}</script></html>';
                $content = sprintf($tpl_msg,$datas['msg'],$tpl_js);
            }
            return Response::load()->send($content,$code,$content_type,$charset);
        }
    }


    public function error($msg,$url=null,$code=200,$content_type="text/html",$charset='utf-8'){
        $datas = array(
            'type'=>'error',
            'msg'=>$msg,
            'url'=>$url ? $url : $_SERVER['HTTP_REFERER'],
        );
        if(Request::load()->isAjax()){
            return $this->ajaxReturn(false,$datas,$msg);
        }else{
            $dispatch_jump_tpl = "success_error.php";
            if(file_exists($this->loadApp()->fpath($this->formatTemplate($dispatch_jump_tpl)))){
                $content = $this->template($dispatch_jump_tpl,$datas);
            }else{
                $tpl_js = $datas['url'] ? sprintf('window.location.href="%s";',$datas['url']) : 'window.history.go(-1);';
                $tpl_msg='<!DOCTYPE html><html><head><meta charset="utf-8"><title>Error!!!</title><script type="text/javascript">window.onload=function(){alert("Error!!\n%s");%s;}</script></html>';
                $content = sprintf($tpl_msg,$datas['msg'],$tpl_js);
            }
            return Response::load()->send($content,$code,$content_type,$charset);
        }
    }


    public function ajaxReturn($flag,$data=null,$msg='',$code=200,$content_type="application/json",$charset='utf-8'){
        $data = array('flag'=>$flag,'data'=>$data,'msg'=>$msg);
        return $this->json($data,$code,$content_type,$charset);
    }


    public function json($datas=array(),$code=200,$content_type="application/json",$charset='utf-8'){
        $datas = $datas ? $datas : $this->view_datas;
        $content = json_encode($datas,JSON_UNESCAPED_UNICODE);
        return Response::load()->send($content,$code,$content_type,$charset);
    }

    public function redirect($url,$code=302,$content_type="text/html",$charset='utf-8'){
        return Response::load()->addHeader('Location',$url)->send('',$code,$content_type,$charset);
    }

    public function formatTemplate($template){
        $template = strpos($template,'template/')===0 ? $template : sprintf("template/%s",ltrim($template,'/'));
        $template = strtolower(substr($template,-4)) == '.php' ? $template : $template.'.php';
        return $template;
    }
}
