<?php

namespace misuoka\WorkLoop;

class WorkDistribute
{
    /**
     * 系统名称
     *
     * @var string
     */
    private $sysname = 'WorkLoop';

    /**
     * 版本号
     *
     * @var string
     */
    private $version = 'v0.1.0';

    /**
     * 配置信息
     *
     * @var array
     */
    private $config = [
        'name'               => '后台业务循环守护服务程序',
        'version'            => 'v1.0', // 程序版本
        'display_type'       => 0, // UI形式。0：默认输出方式；1：重复刷新格式化的UI
        'child_std_redirect' => false, // 重定向子进程输出
        'workers'            => [
            // 'workname' => [
            //     'enabled'      => true,  // 启用任务
            //     'logic'        => \app\logic\Xxxx::class, // 具体业务类，执行函数必须是 run
            //     'sleeptime'    => 0.3,   // 秒，支持小数。循环执行任务的休眠时间
            //     'working_time' => 10,    // 工作时长，单位分钟。进程循环执行的时间。时间到了之后，会再次启动进程
            // ],
        ],
    ];

    /**
     * 业务配置
     *
     * @var [type]
     */
    private $workers;

    /**
     * 工作进程
     *
     * @var array
     */
    private $tasks = [];

    /**
     * 执行输出日志
     *
     * @var array
     */
    private $runlogs = [];

    /**
     * 主进程ID
     *
     * @var [type]
     */
    private $masterPid = null;

    /**
     * 消息队列
     *
     * @var [type]
     */
    private $msgQueue = null;

    /**
     * 启动时间
     *
     * @var [type]
     */
    private $timeStart = null;

    public function __construct($config = [])
    {
        $this->config    = \array_merge($this->config, $config);
        $this->masterPid = \getmypid();
        // $this->msgQueue  = new MsgQueue();
    }

    public function sysLoadInfo()
    {
        $loadAvg = \sys_getloadavg();
        foreach ($loadAvg as $key => $value) {
            $loadAvg[$key] = round($value, 2);
        }

        return \implode(", ", $loadAvg);
    }

    public function softUI()
    {
        $childrenPids = $this->getChildrenPids();

        $texts = [];
        $texts[] = "-----------------------<white> PHP多进程循环任务服务程序 </white>-------------------";
        $texts[] = "程序名称：<purple>" . $this->config['name'] . "</purple>";
        $texts[] = "程序版本：<purple>" . $this->config['version'] . "</purple>";
        $texts[] = "服务脚本：" . $this->sysname . " " . $this->version;
        $texts[] = "开始时间：" . $this->timeStart;
        $texts[] = "现在时间：" . date('Y-m-d H:i:s');
        $texts[] = "平均负载：" . $this->sysLoadInfo();
        $texts[] = "执行业务：<red>" . implode(',', array_keys($childrenPids)) . '</red>';
        $texts[] = "主进程：  <red>" . \posix_getpid() . '</red>';
        $texts[] = "子进程：  <red>" . count($this->tasks) . ' 个，PID：' . implode(',', $childrenPids) . '</red>';
        $texts[] = "----------------------------<green> By: Misuoka </green>----------------------------";
        $texts[] = "<yellow>Press Ctrl+C to quit.</yellow>";
        $texts[] = '';

        $texts  = array_merge($texts, $this->runlogs);
        $string = $this->clearLine($this->replaceColor(\implode(PHP_EOL, $texts)));

        return $string;
    }

    public function softTitle()
    {
        $texts   = [];
        $texts[] = "-----------------------<white> PHP多进程循环任务服务程序 </white>-------------------";
        $texts[] = "程序名称：<purple>" . $this->config['name'] . "</purple>";
        $texts[] = "程序版本：<purple>" . $this->config['version'] . "</purple>";
        $texts[] = "服务脚本：" . $this->sysname . " " . $this->version;
        $texts[] = "开始时间：" . $this->timeStart;
        $texts[] = "主进程：  <red>" . \posix_getpid() . '</red>';
        $texts[] = "----------------------------<green> By: Misuoka </green>----------------------------";
        $texts[] = "<yellow>Press Ctrl+C to quit.</yellow>";
        $texts[] = '';

        $texts  = array_merge($texts, $this->runlogs);
        $string = $this->clearLine($this->replaceColor(\implode(PHP_EOL, $texts)));

        return $string;
    }

    public function showScreen()
    {
        if ($this->config['display_type'] == 1) {
            echo $this->softUI();
        } else {
            echo $this->softTitle();
        }
    }

    public function refreshScreen()
    {
        if ($this->config['display_type'] == 1) {
            echo $this->softUI();
        }
    }

    private function getChildrenPids()
    {
        $ret = [];
        foreach ($this->tasks as $task) {
            $ret[$task->getWorker()->getName()] = $task->getPid();
        }

        return $ret;
    }

    // 清屏
    public function clearScreen()
    {
        $arr = [27, 91, 72, 27, 91, 50, 74];
        foreach ($arr as $a) {
            echo chr($a);
        }
        //array_map(create_function('$a', 'print chr($a);'), array(27, 91, 72, 27, 91, 50, 74));
    }

    public function replaceColor($str)
    {
        $line   = "\033[1A\n\033[K";
        $white  = "\033[47;30m";
        $green  = "\033[32;40m";
        $yellow = "\033[33;40m";
        $red    = "\033[31;40m";
        $purple = "\033[35;40m";
        $end    = "\033[0m";
        $str    = str_replace(['<n>', '<white>', '<green>', '<yellow>', '<red>', '<purple>'], [$line, $white, $green, $yellow, $red, $purple], $str);
        $str    = str_replace(['</n>', '</white>', '</green>', '</yellow>', '</red>', '</purple>'], $end, $str);
        return $str;
    }

    // shell 替换显示
    private function clearLine($message, $force_clear_lines = null)
    {
        static $last_lines = 0;
        if (!is_null($force_clear_lines)) {
            $last_lines = $force_clear_lines;
        }

        // 获取终端宽度
        $toss       = $status       = null;
        $term_width = exec('tput cols', $toss, $status);
        if ($status || empty($term_width)) {
            $term_width = 64; // Arbitrary fall-back term width.
        }

        $line_count = 0;
        foreach (explode("\n", $message) as $line) {
            $line_count += count(str_split($line, $term_width));
        }
        // Erasure MAGIC: Clear as many lines as the last output had.
        for ($i = 0; $i < $last_lines; $i++) {
            echo "\r\033[K\033[1A\r\033[K\r";
        }
        $last_lines = $line_count;
        return $message . "\n";
    }

    private function log($str, $type = 'info')
    {
        $str = \sprintf("[%s][%s] %s", date("Y-m-d H:i:s"), $type, $str);

        if ($this->config['display_type'] == 1) {
            if (count($this->runlogs) > 5) {
                array_shift($this->runlogs);
            }
            $this->runlogs[] = $str;
        } else {
            echo $str . "\n";
        }
    }

    /*
     *
     * @return void
     */
    public function run()
    {
        $this->timeStart = date('Y-m-d H:i:s');
        $this->clearScreen();
        $this->showScreen();
        $this->loadWorkers();

        // pcntl_signal(SIGUSR1, function ($signal) {
        //     $this->log('重载任务');
        //     $this->loadWorkers(); // 重新加载任务
        // });

        $this->wait();
    }

    /**
     * 收回进程并重启
     */
    private function wait()
    {
        while (true) {
            pcntl_signal_dispatch();
            $status = 0;
            $pid    = pcntl_wait($status, WNOHANG);
            pcntl_signal_dispatch();

            // $data = $this->msgQueue->read();
            // if ($data) {
            //     echo "从消息队列读取：" . $data . "\n";
            // }

            $index = $this->getIndexOfTaskByPid($pid);
            if (false !== $index) {
                // 重启任务
                \sleep(1); // 1s
                $this->restartWorker($index, \pcntl_wexitstatus($status));
            }

            $this->refreshScreen();
            usleep(200000); // 0.2s
        }
    }

    private function loadWorkers()
    {
        $this->parseConfig();

        foreach ($this->workers as $worker) {
            if ($worker->isEnabled()) {
                $this->startTask($worker);
            } else {
                $this->removeTask($worker);
            }
        }
    }

    private function parseConfig()
    {
        $this->workers = [];
        foreach ($this->config['workers'] as $name => $item) {

            // 检查配置有效性
            if (!$this->checkConfig($name, $item)) {
                continue;
            }

            $worker          = new Worker($name, $item);
            $this->workers[] = $worker;
        }
    }

    private function checkConfig($name, $item)
    {
        if (!isset($item['logic'])) {
            $this->log(sprintf("执行失败，任务 [ %s ] 未配置业务类", $name), 'error');
            return false;
        }

        try {
            $logic = new $item['logic'];
        } catch (\Throwable $th) {
            $this->log(sprintf("执行失败，任务 [ %s ] 的业务类 [ %s ] 不存在", $name, $item['logic']), 'error');
            return false;
        }

        if (!($logic instanceof WorkInterface)) {
            $this->log(sprintf("执行失败，任务 [ %s ] 的业务类 [ %s ] 未实现接口 [ WorkInterface ]", $name, $item['logic']), 'error');
            return false;
        }

        return true;
    }

    private function startTask(Worker $worker)
    {
        $index = $this->getIndexOfTask($worker->getName());

        if (false === $index) {
            // 创建新任务
            $task = new Task($worker);
            $this->runTask($task);
            $this->tasks[] = $task;
        } else {
            // 先停止后重新创建
            $this->tasks[$index]->setWorker($worker); // 设置新的 Worker
            $this->tasks[$index]->stop(); // 立即停止运行中的进程
        }
    }

    private function removeTask(Worker $worker)
    {
        $index = $this->getIndexOfTask($worker->getName());

        if (false !== $index) {
            $this->tasks[$index]->stop();
            $this->tasks[$index]->setRemoving(true);
        }
    }

    private function getIndexOfTask(string $workerName)
    {
        foreach ($this->tasks as $index => $task) {
            if ($workerName == $task->getWorker()->getName()) {
                return $index;
            }
        }

        return false;
    }

    private function getIndexOfTaskByPid($pid)
    {
        if (!$pid || $pid <= 0) {
            return false;
        }

        foreach ($this->tasks as $index => $task) {
            if ($pid == $task->getPid()) {

                return $index;
            }
        }

        return false;
    }

    private function getWorker($workerName)
    {
        foreach ($this->workers as $worker) {
            if ($workerName == $worker->getName()) {
                return $worker;
            }
        }

        return false;
    }

    private function runTask(Task $task)
    {
        $pid = pcntl_fork();
        if ($pid === -1) {

            $this->log("创建任务子进程失败", 'error');
            exit('fork fail');
        } else if ($pid > 0) {

            $task->setPid($pid);
            return $pid;
        } else {

            \cli_set_process_title($this->sysname . ':' . $task->getWorker()->getName());

            if ($this->config['display_type'] == 1 || $this->config['child_std_redirect']) {
                \fclose(STDIN);
                \fclose(STDOUT);
                \fclose(STDERR);
                $STDIN  = fopen('/dev/null', "r");
                $STDOUT = fopen('/dev/null', "a");
                $STDERR = fopen('/dev/null', "a");
            }

            try {
                // 执行子进程业务
                $task->run(function () {

                    if (posix_getppid() != $this->masterPid) {
                        // printf("[%s] 父进程不存在，退出子进程\n", date("Y-m-d H:i:s"));
                        exit;
                    }

                    // $this->msgQueue->write('hello world: ' . rand(100000, 999999));
                });
            } catch (\Throwable $th) {
                printf("任务 [ %s ] 运行异常：%s\n", $task->getWorker()->getName(), $th->getMessage());
                exit(11); // 异常
            }

            // 正常退出子进程
            exit;
        }
    }

    private function restartWorker($index, $exitCode = 0)
    {
        $workerName = $this->tasks[$index]->getWorker()->getName();
        $worker     = $this->getWorker($workerName); // 获取业务配置中的 worker 信息
        if (!$worker) {
            $this->log(sprintf("新配置里不存在任务 [ %s ]，即将移除", $workerName));
            $this->tasks[$index]->setRemoving(true);
        }

        // TODO: 
        if ($exitCode == 11) {
            //
            // $worker->
        }

        if ($this->tasks[$index]->isRemoving()) {

            unset($this->tasks[$index]);
            $this->log(sprintf("移除任务 [ %s ]", $workerName));
        } else {

            $this->tasks[$index]->setWorker($worker);
            $this->runTask($this->tasks[$index]);
            $this->log(sprintf("重新启动 [ %s ]", $workerName));
        }
    }

    private function free()
    {
        // 只有主进程才停止
        if ($this->masterPid == \getmypid()) {
            // $this->msgQueue->stop();
        }
    }

    private function sizeformat($byte)
    {
        if ($byte < 1024) {
            return $byte . 'byte';
        } else if (($size = round($byte / 1024, 2)) < 1024) {
            return $size . 'KB';
        } else if (($size = round($byte / (1024 * 1024), 2)) < 1024) {
            return $size . 'MB';
        } else {
            return round($byte / (1024 * 1024 * 1024), 2) . 'GB';
        }
    }

    public function __destruct()
    {
        $this->free();
    }
}
