<?php

namespace misuoka\WorkLoop;

class Pipe
{
    private $pipeFile;
    private $writePipe;
    private $readPipe;

    public function __construct($pipeFile, $mode = 0664)
    {
        $this->pipeFile = $pipeFile;

        if (!file_exists($this->pipeFile)) {
            if (!posix_mkfifo($this->pipeFile, $mode)) {
                throw new \Exception("管道 [ {$pipeFile} ] 创建失败");
            }
        } 
    }

    /**
     * 打开写管道
     *
     * @return void
     */
    public function openWrite()
    {
        $this->writePipe = fopen($this->pipeFile, 'w');
        if ($this->writePipe == null) {
            return false;
        }

        return true;
    }

    /**
     * 往管道中写入数据
     *
     * @param [type] $data
     * @return void
     */
    public function write($data)
    {
        if ($this->writePipe == null) {
            return false;
        }

        return \fwrite($this->writePipe, $data);
    }

    /**
     * 关闭写管道
     *
     * @return void
     */
    public function closeWrite()
    {
        return \fclose($this->writePipe);
    }

    public function writeAll($data)
    {
        $data .= "\n";
        $writePipe = fopen($this->pipeFile, 'w');
        \fwrite($writePipe, $data, strlen($data));
        \fclose($writePipe);
    }

    /**
     * 打开读管道
     *
     * @return void
     */
    public function openRead()
    {
        $this->readPipe = \fopen($this->readPipe, 'r');
        if ($this->readPipe == null) {
            return false;
        }

        return true;
    }

    /**
     * 从管道中读取数据
     *
     * @param integer $byte
     * @return void
     */
    public function read($byte = 1024)
    {
        if ($this->writePipe == null) {
            return '';
        }

        return \fread($this->readPipe, $byte);
    }

    /**
     * 关闭读管道
     *
     * @return void
     */
    public function closeRead()
    {
        return \fclose($this->readPipe);
    }

    public function readAll()
    {
        $readPipe = \fopen($this->pipeFile, 'r');
        $data     = '';
        \stream_set_blocking($readPipe, false); // 非阻塞
        while ($readPipe != null && !\feof($readPipe)) {
            $data .= \fread($readPipe, 1024);
        }
        \fclose($readPipe);

        return $data;
    }

    public function stopPipe()
    {
        try {

            $this->writePipe && \fclose($this->writePipe);
            $this->readPipe && \fclose($this->readPipe);
        } catch (\Throwable $th) {

        }

        @unlink($this->pipeFile);
    }
}
