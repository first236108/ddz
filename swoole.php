<?php
/**
 * Created by PhpStorm.
 * User: 85210755@qq.com
 * NickName: 柏宇娜
 * Date: 2018/6/4 15:17
 */
include_once '../thinkphp/base.php';
include_once '../thinkphp/helper.php';

use think\Db;

$db = Db::connect([
    'type'     => 'mysql',
    'hostname' => '192.168.1.170',
    'database' => 'supplychain',
    'username' => 'root',
    'password' => 'root',
    'hostport' => '3306',
    'charset'  => 'utf8',
    'prefix'   => 'tp_',
]);

$ws = new swoole_websocket_server("0.0.0.0", 9503);
$ws->set([
    'worker_num'      => 4,
    'max_request'     => 500,
    'max_conn'        => 1000,
    'task_worker_num' => 100,
    'daemonize'       => 0
]);

$ws->on('open', function ($ws, $request) use ($db) {
    $req  = paserUri($request->server['request_uri']);
    $id   = $req['uid'];
    $info = login($db, $request, $id);

    $result = [
        'type' => 1,//0服务器消息，1登录返回消息，2更新在线状态消息
        'from' => '服务器',
        'data' => [
            'from'   => '服务器',
            'time'   => date('H:i'),
            'me'     => $info['me'],
            'frends' => $info['frends'],
            'msg'    => "欢迎 '{$info['me']['nickname']}' 登录商超"
        ]
    ];
    $ws->push($request->fd, json_encode($result));

    $notice['fds'] = getOnlineFrendsFd($db, $info['me']['user_id']);
    if (count($notice['fds'])) {
        $notice['body'] = [
            'type' => 3,
            'from' => '服务器',
            'data' => [
                'from'   => '服务器',
                'time'   => date('H:i'),
                'online' => $info['me']['user_id'],
                'msg'    => $info['me']['nickname'] . '已上线...'
            ]
        ];
        $task_id        = $ws->task($notice);
    }
});

$ws->on('message', function ($ws, $frame) use ($db) {
    if ($frame->data) {
        $data = json_decode($frame->data, true);
        $fds  = findFd($db, $data['from'], $data['to']);
        if ($fds === false) {
            $result = [
                'type' => 0,
                'from' => '服务器',
                'data' => [
                    'from'     => '服务器',
                    'time'     => date('H:i'),
                    'nickname' => '服务器',
                    'head_pic' => 'http://www.iconpng.com/png/computer-and-media-1/network26.png',
                    'msg'      => "对方不在线，消息未发送成功..."
                ]
            ];
            $ws->push($frame->fd, json_encode($result));
        } else {
            $send['body'] = [
                'type' => 0,
                'from' => $frame->fd,
                'data' => [
                    'from'     => $data['from'],
                    'time'     => date('H:i'),
                    'nickname' => $data['nickname'],
                    'head_pic' => $data['head_pic'],
                    'msg'      => $data['msg']
                ]
            ];
            $send['fds']  = $fds;
            $task_id      = $ws->task($send);
        }
    }
});

$ws->on('task', function ($ws, $task_id, $from_id, $data) {
    foreach ($data['fds'] as $key => $fd) {
        $ws->push($fd, json_encode($data['body']));
    }

    $ws->finish("$data -> OK");
});

$ws->on('finish', function ($ws, $task_id, $data) {
    echo '发送完成' . PHP_EOL;
});

$ws->on('close', function ($ws, $fd) use ($db) {
    echo "client-{$fd} is closed\n";
    $user    = $db->name('swoole a')->join('__USERS__ b', 'a.user_id=b.user_id')->where(['a.fd' => $fd, 'a.online' => 1])->field('a.*,b.nickname')->find();
    $user_id = $user['user_id'];
    $db->name('swoole')->where("user_id", $user_id)->update(['online' => 0]);
    $data['fds'] = getOnlineFrendsFd($db, $user_id);
    if (count($data['fds'])) {
        $data['body'] = [
            'type' => 2,
            'from' => '服务器',
            'data' => [
                'from'    => '服务器',
                'time'    => date('H:i'),
                'offline' => $user_id,
                'msg'     => $user['nickname'] . '已下线...'
            ]
        ];
        $task_id      = $ws->task($data);
    }
});

function paserUri($str)
{
    $arr = explode('/', trim($str, '/'));

    foreach ($arr as $k => $v) {
        if ($k % 2)
            $values[] = $v;
        else
            $keys[] = $v;
    }
    $result = array_combine($keys, $values);
    return $result;
}

function login($db, $request, $id)
{
    $data = [
        'fd'        => $request->fd,
        'user_id'   => $id,
        'last_time' => time(),
        'last_ip'   => $request->server['remote_addr'],
        'online'    => 1
    ];
    $user = $db->name('users a')
        ->join('__SWOOLE__ b', 'a.user_id=b.user_id', 'LEFT')
        ->where('a.user_id', $id)
        ->field('a.user_id,a.nickname,a.head_pic,b.fd,b.last_time,b.last_ip,b.channel_id')
        ->find();
    if ($user['fd']) {
        $user['fd'] = $request->fd;
        $db->name('swoole')->where('user_id', $id)->update($data);
    } else {
        $data['create_time'] = time();
        $db->name('swoole')->insert($data);
    }

    $frends = $db->name('frend a')
        ->join('__SWOOLE__ b', 'a.frend_uid=b.user_id')
        ->join('__USERS__ c', 'b.user_id=c.user_id')
        ->where(['a.user_id' => $id, 'a.r_status' => 1])
        ->column('b.online,b.fd,c.nickname,c.head_pic', 'a.frend_uid');

    return ['me' => $user, 'frends' => $frends];
}

function findFd($db, $from, $to)
{
    $fd   = $db->name('swoole')->where(['user_id' => $to, 'online' => 1])->field('fd')->find();
    $from = $db->name('swoole')->where("user_id", $from)->value('fd');
    if ($fd) {
        array_push($fd, $from);
        return $fd;
    }

    $fds = $db->name('swoole')->where("channel_id = (select channel_id from tp_swoole where fd=$from and channel_id=$to limit 1) and online=1")->column('fd');
    if ($fds)
        return $fds;
    else
        return false;
}

function getOnlineFrendsFd($db, $user_id)
{
    return $db->name('frend a')->join('__SWOOLE__ b', 'a.frend_uid=b.user_id')->where("a.user_id=$user_id and b.online=1")->column('fd');
}

$ws->start();