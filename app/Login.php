<?php
/**
 * Created by PhpStorm.
 * User: 85210755@qq.com
 * NickName: 柏宇娜
 * Date: 2018/7/4 17:06
 */

namespace app;

use think\Db;

include_once 'common.php';

class Login
{
    public function test($ws, $fd)
    {
        $list = Db::name('users')->where('user_id', 1)->find();
        $ws->push($fd, json_encode($list['nickname']));
        shell_exec("pgrep -f 'swoole.php'| head -1 | xargs kill -USR1");
    }
}