# work-loop
循环执行任务的后台服务程序

## 使用方法

使用 composer 进行安装 `composer require misuoka/work-loop`。

按如下配置执行脚本，在 **workers** 数组里配置具体的业务代码。

```php
# 假设文件：index.php 

require(__DIR__ . '/../vendor/autoload.php');

use misuoka\WorkLoop\WorkGo;

$config = [
    'name'         => 'XXX服务后台业务程序', // 自定义项目名称
    'version'      => 'v1.0',              // 自定义项目版本
    'display_type' => 0, // UI形式。0：默认输出方式；1：重复刷新格式化的UI
    'workers' => [
        // 'workname' => [
        //     'enabled'      => true,  // 启用任务
        //     'logic'        => \app\logic\Xxxx::class, // 具体业务类，执行函数必须是 run
        //     'sleeptime'    => 0.3,   // 秒，支持小数。循环执行任务的休眠时间
        //     'working_time' => 10,    // 工作时长，单位分钟。进程循环执行的时间。时间到了之后，会再次启动进程
        // ],
    ],
];

$wd = new WorkGo($config);
$wd->run();
```

在脚本文件中配置完成后，在 shell 终端中执行如下命令。

```shell
# Usage: php script_name.php {start|restart|stop|help} [-d]
# -d 表示以守护进程的方式运行脚本
php script_name.php start
```
