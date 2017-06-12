<?php
/**
 * Suda FrameWork
 *
 * An open source application development framework for PHP 7.0.0 or newer
 * 
 * Copyright (c)  2017 DXkite
 *
 * @category   PHP FrameWork
 * @package    Suda
 * @copyright  Copyright (c) DXkite
 * @license    MIT
 * @link       https://github.com/DXkite/suda
 * @version    since 1.2.4
 */
function mime(string $type){
    return suda\core\Response::mime($type);
}
// 语言翻译
function __(string $message){
    return call_user_func_array('suda\core\Locale::_',func_get_args());
}
// 语言翻译
function _T(string $message){
    return call_user_func_array('suda\core\Locale::_',func_get_args());
}

// 获取debug记录
function _D(){
    return new suda\core\Debug;
}

// 获取配置
function conf(string $name,$default=null){
    return suda\core\Config::get($name,$default);
}

// 使用命名空间
function use_namespace(string $namespace){
    return suda\core\Autoloader::setNamespace($namespace);
}

function u($name=null,array $values=[]){
    if (is_string($name)){
        return suda\core\Router::getInstance()->buildUrl($name,$values);
    }
    elseif (is_array($name)){
        return suda\core\Router::getInstance()->buildUrl(suda\core\Response::$name,$name);
    }else{
        return suda\core\Router::getInstance()->buildUrl(suda\core\Response::$name);
    }
}

function import(string $path){
    return suda\core\Autoloader::import($path);
}