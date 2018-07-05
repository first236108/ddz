<?php
/**
 * Created by PhpStorm.
 * User: 85210755@qq.com
 * NickName: 柏宇娜
 * Date: 2018/6/4 15:17
 */
include_once './vendor/autoload.php';

use app\Login;

$ws = new swoole_websocket_server("0.0.0.0", 9503);
$ws->set([
    'worker_num'      => 4,
    'max_request'     => 500,
    'max_conn'        => 1000,
    'task_worker_num' => 100,
    'daemonize'       => 0
]);

$ws->on('open', function ($ws, $request) {
    var_dump($request->fd, $request->get, $request->server);
    (new Login())->test($ws, $request->fd);
    //$ws->push($request->fd, json_encode($result));
});

$ws->on('message', function ($ws, $frame) {
    if (isset($frame->data->reload))
        $ws->reload();
    $ws->push($frame->fd, json_encode($frame));
    //$task_id = $ws->task($send);

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

$ws->on('close', function ($ws, $fd) {
    $task_id = $ws->task($data);
});

$ws->start();