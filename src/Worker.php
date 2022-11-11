<?php

namespace misuoka\WorkLoop;

class Worker
{
    private $name;

    private $logic;

    private $enabled;

    private $onlyOnce;

    private $workingTime;

    private $sleeptime;

    public function __construct($name, $config = [])
    {
        $this->name        = $name;
        $this->logic       = $config['logic'] ?? null;
        $this->enabled     = $config['enabled'] ?? false;
        $this->workingTime = $config['working_time'] ?? 10; // 10 分钟
        $this->sleeptime   = !isset($config['sleeptime'])
            || $config['sleeptime'] <= 0
            || !\is_numeric($config['sleeptime']) 
            ? 300000 : $config['sleeptime'] * 1000000; // 0.3 秒
    }

    public function getName()
    {
        return $this->name;
    }

    public function getLogic()
    {
        return $this->logic;
    }

    public function isEnabled()
    {
        return $this->enabled;
    }

    public function getWorkingTime()
    {
        return $this->workingTime;
    }

    public function getSleeptime()
    {
        return $this->sleeptime;
    }
}
