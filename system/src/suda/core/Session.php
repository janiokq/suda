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
namespace suda\core;

/**
 * 会话操纵类
 * 控制PHP全局会话，
 */
class Session
{
    protected static $session;

    public static function newInstance(string $type = 'PHP')
    {
        if (class_exists($class=__NAMESPACE__.'\\session\\'.ucfirst($type).'Session')) {
            static::$session[$type]=$class::newInstance();
            return static::$session[$type];
        } else {
            throw new Exception(__('unsupport type of session:%s', $type));
        }
    }
 
    public static function __callStatic(string $method, $args)
    {
        return call_user_func_array([self::newInstance(conf('session.type','PHP')),$method], $args);
    }
}