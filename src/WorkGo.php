<?php

namespace misuoka\WorkLoop;

class WorkGo
{
    private $daemonize = false;
    private $logFile;
    private $pidFile;
    private $pipeFile;
    private $startFile;
    private $uid = 80;
    private $gid = 80;
    private $pipe;
    private static $startTime;
    private $wdServer = null;

    public function __construct($config)
    {
        $this->config = $config;
        $this->init();
    }

    public function init()
    {
        $backtrace     = debug_backtrace();
        $startFile     = $backtrace[count($backtrace) - 1]['file'];
        $unique_prefix = str_replace('.php', '', str_replace('/', '_', $startFile));

        $this->pidFile   = sys_get_temp_dir() . "/{$unique_prefix}.pid";
        $this->pipeFile  = sys_get_temp_dir() . "/{$unique_prefix}.pipe";
        $this->logFile   = sys_get_temp_dir() . '/WorkLoop.log';
        $this->startFile = $startFile;
    }

    public function run()
    {
        global $argv;

        if (\php_sapi_name() != 'cli') {
            die("only run in command line mode\n");
        }

        if (count($argv) < 2) {
            $this->help($argv[0]);
            exit;
        }

        if (isset($argv[2]) && $argv[2] === '-d') {
            $this->daemonize = true;
        }

        if ($argv[1] === 'stop') {
            $this->stop();
        } else if ($argv[1] === 'start') {
            $this->start();
        } else if ($argv[1] === 'status') {
            $this->status();
        } else if ($argv[1] === 'restart') {
            $this->stop();
            \sleep(1);
            $this->start();
        } else {
            $this->help($argv[0]);
        }
    }

    private function daemon()
    {
        $pid = pcntl_fork();
        if ($pid == -1) {
            die("The [ WorkLoop ] proc could not fork.\n");
        } else if ($pid) {
            // we are the parent
            // pcntl_wait($status); // Protect against Zombie children
            exit;
        } else {
            // we are the child
            //
            // posix_setuid($this->uid);
            // posix_setgid($this->gid);
            // posix_setuid(posix_getuid());
            // posix_setgid(posix_getgid());
            \posix_setsid();

            // 修改当前进程的工作目录 TODO: 待测试
            // \chdir('/');

            // 二次 fork
            $pid = \pcntl_fork();
            if ($pid == -1) {
                die("The [ WorkLoop ] client proc could not fork.\n");
            } elseif ($pid) {
                exit;
            }

            echo "The [ WorkLoop ] proc is start.\n";

            return (getmypid());
        }
    }

    private function start()
    {
        if (file_exists($this->pidFile)) {
            if ($this->isMasterAlive()) {
                echo "The [ WorkLoop ] proc is running. Please stop this proc first.\n";
                exit;
            } else {
                @unlink($this->pidFile);
                @unlink($this->pipeFile);
            }
        }

        \umask(0); // 把文件掩码清零

        cli_set_process_title('WorkLoop:Master');

        if ($this->daemonize === true) {
            $this->daemon();

            global $STDIN, $STDOUT, $STDERR;

            \fclose(STDIN);
            \fclose(STDOUT);
            \fclose(STDERR);
            $STDIN  = fopen('/dev/null', "r");
            $STDOUT = fopen('/dev/null', "a");
            $STDERR = fopen('/dev/null', "a");
        }

        // 创建 PID 文件
        $this->createPidFile();
        $this->createPipe();

        static::$startTime = time();
        $this->installSignal(); // 安装退出信号

        $this->wdServer = new WorkDistribute($this->config);
        $this->wdServer->run();
    }

    private function stop()
    {
        if (file_exists($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            @unlink($this->pidFile);
            $this->removePipe();

            if ($pid) {
                posix_kill($pid, 9);
            }
        }
    }

    private function status()
    {
        //
        if ($this->isMasterAlive($pid)) {
            posix_kill($pid, SIGUSR2);
            sleep(1);
            // 从管道中读取数据
            $this->createPipe();
            $data = $this->pipe->readAll();
            echo $data . "\n";
        } else {
            echo "The [ WorkLoop ] proc not running.\n";
        }
    }

    private function help($proc)
    {
        $this->safeEcho(sprintf("Usage: php %s {start|restart|stop|help} [-d]\n", $proc));
    }

    private function createPidFile()
    {
        file_put_contents($this->pidFile, getmypid());
    }

    private function createPipe()
    {
        $this->pipe = new Pipe($this->pipeFile);
    }

    private function removePipe()
    {
        // 释放管道
        $this->pipe && $this->pipe->stopPipe();
        @unlink($this->pipeFile);
    }

    private function isMasterAlive(&$pid = -1)
    {
        if (file_exists($this->pidFile)) {
            $pid = file_get_contents($this->pidFile);
            return $pid && @\posix_kill($pid, 0);
        }

        return false;
    }

    /**
     * Log.
     *
     * @param string $msg
     * @return void
     */
    private function log($msg, $writeToFile = true)
    {
        $msg = date('[Y-m-d H:i:s]') . " {$msg}\n";
        if (!$this->daemonize) {
            $this->safeEcho($msg);
        }

        if ($writeToFile) {
            file_put_contents((string) $this->logFile, $msg, FILE_APPEND | LOCK_EX);
        }
    }

    /**
     * Log.
     *
     * @param string $msg
     * @return void
     */
    private function trace($msg)
    {
        $msg = date('[Y-m-d H:i:s]') . " {$msg}\n";
        if (!$this->daemonize) {
            $this->safeEcho($msg);
        }
    }

    /**
     * Safe Echo.
     *
     * @param $msg
     */
    private function safeEcho($msg)
    {
        if (!function_exists('posix_isatty') || posix_isatty(STDOUT)) {
            echo $msg;
        }
    }

    // 信号处理函数
    public function signalHandler($signo)
    {
        switch ($signo) {
            case SIGKILL: // 杀死进程
            case SIGTERM: // 软件信号终止
                // 处理kill
                $this->stop();
                echo PHP_EOL . 'killed ' . PHP_EOL;
                exit;
                break;
            case SIGHUP:
                //处理SIGHUP信号
                echo "SIGHUP\n";
                break;
            case SIGINT:
                // 处理 ctrl+c
                unlink($this->pidFile);
                $this->removePipe();
                echo PHP_EOL . 'ctrl + c ' . PHP_EOL;
                exit;
                break;
            case SIGUSR2:
                $this->buildRunnigStatus();
                break;
            default:
                // 处理所有其他信号
        }
    }

    public function installSignal()
    {
        // stop
        // pcntl_signal(SIGKILL, array($this, 'signalHandler'), false);
        pcntl_signal(SIGINT, array($this, 'signalHandler'), false);
        // graceful stop
        // pcntl_signal(SIGTERM, array($this, 'signalHandler'), false);
        pcntl_signal(SIGHUP, array($this, 'signalHandler'), false);
        // reload
        // pcntl_signal(SIGUSR1, array($this, 'signalHandler'), false);
        // graceful reload
        // pcntl_signal(SIGQUIT, array($this, 'signalHandler'), false);
        // status
        pcntl_signal(SIGUSR2, array($this, 'signalHandler'), false);
        // connection status
        // pcntl_signal(SIGIO, array($this, 'signalHandler'), false);
        // ignore
        pcntl_signal(SIGPIPE, SIG_IGN, false);
    }

    private function buildRunnigStatus()
    {
        $this->pipe->writeALl($this->wdServer->softUI());
    }
}
