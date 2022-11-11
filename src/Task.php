<?php

namespace misuoka\WorkLoop;

class Task
{
    private $worker;

    private $pid;

    private $removing;

    private $timestart;

    private $controlFile;

    public function __construct(Worker $worker)
    {
        $this->setWorker($worker);
    }

    public function setWorker(Worker $worker)
    {
        $this->worker      = $worker;
        $this->controlFile = sys_get_temp_dir() . '/' . $this->worker->getName() . '_loop.end';
    }

    public function setPid($pid)
    {
        $this->pid = $pid;
    }

    public function setRemoving($removing)
    {
        $this->removing = $removing;
    }

    public function getWorker()
    {
        return $this->worker;
    }

    public function getPid()
    {
        return $this->pid;
    }

    public function isRemoving()
    {
        return $this->removing;
    }

    public function run($callback = null)
    {
        $class           = $this->worker->getLogic();
        $logic           = new $class;
        $this->timestart = time();

        do {
            try {
                $logic->run();
                is_callable($callback) ? $callback() : null;

                \usleep($this->worker->getSleeptime());
            } catch (\Throwable $th) {
                echo "异常：" . $th->getMessage() . "\n";
            }

            

        } while ($this->loop());
    }

    public function stop()
    {
        \file_put_contents($this->controlFile, '1');
    }

    private function loop()
    {
        // 单次执行；检测停止；超时设置，避免进程挂死
        if (time() - $this->timestart > 60 * $this->worker->getWorkingTime()
            || $this->checkEnd()) {

            return false;
        }

        return true;
    }

    private function checkEnd()
    {
        \clearstatcache();
        return \file_exists($this->controlFile) && @\unlink($this->controlFile);
    }

    function print($string) {
        if (!function_exists('posix_isatty') || posix_isatty(STDOUT)) {
            echo date('[ Y-m-d H:i:s ] ') . $string . "\n";
        }
    }
}
