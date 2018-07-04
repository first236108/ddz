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
    public function test()
    {
        $list = Db::table('t_user')->where('id', 4444)->find();
        var_dump($list);
    }
}