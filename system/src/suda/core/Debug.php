<?php
namespace suda\core;

Hook::listen("system:init", "Debug::beforeSystemRun");
Hook::listen("system:display::after", "Debug::afterSystemRun");
Hook::listen("system:shutdown", "Debug::phpShutdown");
Hook::listen("system:debug:printf", "Debug::printf");

// TODO: 记录异常类型
class Debug
{
    const MAX_LOG_SIZE=2097152;
    const TRACE = 'trace'; // 运行跟踪
    const DEBUG = 'debug'; // 调试记录
    const INFO = 'info'; // 普通消息
    const NOTICE = 'notice'; // 注意的消息
    const WARNING = 'warning'; // 警告消息
    const ERROR = 'error'; // 错误消息

    protected static $run_info;
    protected static $log=[];
    protected static $time=[];

    public static function time(string $name)
    {
        self::$time[$name]=microtime(true);
    }
    public static function timeEnd(string $name)
    {
        if (isset(self::$time[$name])) {
            $pass=microtime(true)-self::$time[$name];
            $backtrace=debug_backtrace();
            $call=(isset($backtrace[2]['class'])?$backtrace[2]['class'].'#':'').$backtrace[2]['function'];
            self::_loginfo('info', $call, _T('process %s %fs', $name, $pass), $backtrace[1]['file'], $backtrace[1]['line']);
        }
    }

    protected static function _loginfo(string $level, string $name, string $message, string $file, int $line)
    {
        $loginfo['file']=$file;
        $loginfo['line']=$line;
        $loginfo['message']=$message;
        $loginfo['name']=$name;
        $loginfo['level']=$level;
        $loginfo['time']=microtime(true)-D_START;
        $loginfo['mem']=memory_get_usage() - D_MEM;
        self::$log[]=$loginfo;
    }

    public static function displayException(Exception $e)
    {
        self::logException($e);
        if (Config::get('console', false)) {
            return self::printConsole($e);
        } else {
            return self::printHTML($e);
        }
    }

    protected static function printConsole(Exception $e)
    {
        $line=$e->getLine();
        $file=$e->getFile();
        $error=$e->getMessage();
        $backtrace=$e->getBacktrace();
        foreach ($backtrace as $trace) {
            $print_d = null;
            if (isset($trace['file'])) {
                $print_d=basename($trace['file']).'#'.$trace['line'];
            }
            if (isset($trace['class'])) {
                $function = $trace['class'].$trace['type'].$trace['function'];
            } else {
                $function = $trace['function'];
            }
            $args_d='';
            if (!empty($trace['args'])) {
                foreach ($trace['args'] as $arg) {
                    if (is_object($arg)) {
                        $args_d .= 'class '.get_class($arg).',';
                    } else {
                        $args_d.= (is_array($arg)?json_encode($arg):$arg) .',';
                    }
                }
                $args_d = rtrim($args_d, ',');
            }
            $print_d.=' '.$function.'('.$args_d.')';
            $traces_console[]=$print_d;
        }
        print "\033[31m# Error>\033[33m $error\033[0m\r\n";
        print "\t\033[34mCause By $file:$line\033[0m\r\n";
        foreach ($traces_console as $trace_info) {
            print "\033[36m$trace_info\033[0m\r\n";
        }
    }

    protected static function printHTML(Exception $e)
    {
        // 非致命错误
        if ($e->getSeverity()!=E_ERROR) {
            echo "<div class=\"suda-error\"><b>{$e->getName()}</b>: {$e->getMessage()} at {$e->getFile()}#{$e->getLine()}</div>";
            return;
        }
        $line=$e->getLine();
        $file=$e->getFile();

        $pos_num = $line - 1;
        $code_file = file($file);
        $start = $line - 5 < 0 ? 0 : $line - 5;
        $lines = array_slice($code_file, $start, 10, true);
        $backtrace=$e->getBacktrace();
        
        foreach ($backtrace as $trace) {
            $print = null;
            if (isset($trace['file'])) {
                $print = '<a title="'.Storage::cut($trace['file']).'">'.basename($trace['file']).'</a>#'.$trace['line'];
            }
            if (isset($trace['class'])) {
                $function = $trace['class'].$trace['type'].$trace['function'];
            } else {
                $function = $trace['function'];
            }
            $args = '';
            if (!empty($trace['args'])) {
                foreach ($trace['args'] as $arg) {
                    if (is_object($arg)) {
                        $args .= 'class '.get_class($arg).',';
                    } else {
                        $args .=   var_export($arg, true).',';
                    }
                }
                $args = rtrim($args, ',');
            }
            $print .= ' '.$function.'('.$args.')';
            $traces[] = $print;
        }

        $render=new class extends Response {
            public $template=null;
            public function onRequest(Request $request)
            {
                $this->state(500);
                if (\suda\template\Manager::compile('suda:error')) {
                    $this->template=$this->page('suda:error');
                } else {
                    $this->template=$this->pagefile(SYS_RES.'/tpl/error.tpl');
                }
            }
        };
        $render->onRequest(Request::getInstance());
        $debug=self::getInfo();
        $render->template->assign([
                'erron'=>$e->getName(),
                'error'=>$e->getMessage(),
                'file'=>$file,
                'line'=>$line,
                'debuginfo'=>$debuginfo="time:{$debug['time']}  memory:{$debug['memory']}",
                'lines'=>$lines,
                'pos_num'=>$pos_num,
                'traces'=>$traces,
            ]);
        \suda\template\Manager::loadCompile();
        
        $render->template->render();
        exit($e->getMessage());
    }

    public static function logException(Exception $e)
    {
        $loginfo['file']=$e->getFile();
        $loginfo['line']=$e->getLine();
        $loginfo['message']=$e->getMessage();
        $loginfo['name']=$e->getName();
        $loginfo['time']=microtime(true)-D_START;
        $loginfo['mem']=memory_get_usage() - D_MEM;
        $loginfo['hash']=md5(microtime(true).$loginfo['file'].$loginfo['line']);
        $loginfo['level']=Debug::ERROR;
        self::$log[]=$loginfo;
    }

    public static function printf()
    {
        return Request::ip() . "\t" . date('Y-m-d H:i:s') . "\t" .Request::method()."\t\t".Request::virtualUrl() . "\r\n";
    }
    protected static function save($file = 'debug.log')
    {
        if (!is_dir(dirname($file))) {
            Storage::mkdirs(dirname($file));
        }

        if (is_file($file) && filesize($file) > self::MAX_LOG_SIZE) {
            rename($file, dirname($file) . '/' . time() . '-' . basename($file));
        }

        $str=Hook::execTail("system:debug:printf");
        foreach (self::$log as $log) {
            $str.="\t[".number_format($log['time'], 10).':'.$log['mem'].']'."\t".$log['level'].'>In '.$log['file'].'#'.$log['line']."\t\t".$log['name']."\t".$log['message']."\r\n";
        }

        return file_put_contents(LOG_DIR.'/'.$file, $str, FILE_APPEND);
    }



    public static function beforeSystemRun()
    {
        Hook::listen('system:debug::start');
        self::$run_info['start_time']=D_START;
        self::$run_info['start_memory']=D_MEM;
    }

    protected static function getInfo()
    {
        self::$run_info['end_time']=microtime(true);
        self::$run_info['time']=microtime(true) - D_START;
        self::$run_info['memory']=memory_get_usage() - D_MEM;
        self::$run_info['end_memory']=memory_get_usage();
        self::$run_info['included_files']=get_included_files();
        return self::$run_info;
    }


    public static function afterSystemRun()
    {
        $info=self::getInfo();
        Hook::listen('system:debug::end', $info);
    }

    public static function phpShutdown()
    {
        self::save();
    }

    public static function __callStatic($method, $args)
    {
        self::aliasMethod($method, $args);
    }
    
    private static function aliasMethod($method, $args)
    {
        static $mpk=['d','t','i','n','w','e','u'];
        static $map=['d'=>'debug','t'=>'trace','i'=>'info','n'=>'notice','w'=>'warning','e'=>'error','u'=>'user'];

        if (preg_match('/([dtinweu]|debug|trace|info|notice|warning|error|user)/i', $method)) {
            if (in_array($method, $mpk)) {
                $level=$map[strtolower($method)];
            } else {
                $level=strtolower($method);
            }
        }
        $backtrace=debug_backtrace();
        $name=(isset($backtrace[2]['class'])?$backtrace[2]['class'].'#':'').$backtrace[2]['function'];
        self::_loginfo($level, isset($args[1])?$args[0]:$name, $args[1]??$args[0], $backtrace[1]['file'], $backtrace[1]['line']);
    }

    public function __call($method, $args)
    {
        self::aliasMethod($method, $args);
    }
}
