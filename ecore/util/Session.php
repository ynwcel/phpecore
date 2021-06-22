<?php
namespace ecore\util;

class Session{
    public static function load(){
        return new self();
    }

    public static function start(){
        if( PHP_SESSION_ACTIVE != session_status() ){
            session_start();
        }
        return true;
    }

    public static function set($name,$val=null){
        self::start();
        return $_SESSION[$name] = $val;
    }

    public static function get($name){
        self::start();
        return isset($_SESSION[$name]) ? $_SESSION[$name] : null;
    }

    public static function pull($name){
        $data = self::get($name);
        self::delete($name);
        return $data;
    }

    public static function delete($name){
        self::start();
        $_SESSION[$name] = null;
        unset($_SESSION[$name]);
    }

    public static function flush(){
        self::start();
        if (!empty($_SESSION)) {
            $_SESSION = array();
        }
        return true;
    }

    public static function destroy(){
        self::flush();
        session_unset();
        session_destroy();
    }

}