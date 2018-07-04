<?php
/**
 * Created by PhpStorm.
 * User: 85210755@qq.com
 * NickName: 柏宇娜
 * Date: 2018/7/4 17:06
 */
namespace app;
use Illuminate\Database\Capsule\Manager as Capsule;
include_once 'common.php';
class Login
{
    public function test()
    {
        $db = new Capsule;
        $db->addConnection(db_config());
        $db->setAsGlobal();
        $db->bootEloquent();

        $list = $db->table('t_user')->where('id', 4444)->first();
        var_dump($list);
    }
}