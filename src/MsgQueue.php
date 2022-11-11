<?php

namespace misuoka\WorkLoop;

class MsgQueue
{
    private $mqid = null;

    public function __construct()
    {
        $key = \ftok(__FILE__, 'x');
        $this->mqid = \msg_get_queue($key, 0666);
    }

    public function read()
    {
        // 非阻塞读取
        \msg_receive($this->mqid, 1, $mssage_type, 1024, $data, true, MSG_IPC_NOWAIT, $errorcode);
        
        if ($errorcode != MSG_ENOMSG) {
            return $data;
        }

        return null;
    }

    public function write($data)
    {
        \msg_send($this->mqid, 1, $data);
    }

    public function stop()
    {
        \msg_remove_queue($this->mqid);
    }
}