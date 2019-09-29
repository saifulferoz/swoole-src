--TEST--
swoole_server: slowlog
--SKIPIF--
<?php
require __DIR__ . '/../include/skipif.inc';
skip_if_darwin();
skip_if_in_valgrind();
?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';

$pm = new SwooleTest\ProcessManager;

$pm->parentFunc = function ($pid) use ($pm)
{
    $client = new swoole_client(SWOOLE_SOCK_TCP, SWOOLE_SOCK_SYNC); //同步阻塞
    if (!$client->connect('127.0.0.1', $pm->getFreePort(), 3))
    {
        exit("connect failed\n");
    }
    echo $client->recv();
    Assert::assert($client->send("Request\n"));
    echo $client->recv();
    $pm->kill();
};

$pm->childFunc = function () use ($pm)
{
    $serv = new swoole_server('127.0.0.1', $pm->getFreePort());
    $serv->set([
        'worker_num' => 1,
        'task_worker_num' => 1,
        'request_slowlog_file' => __DIR__ . '/slow.log',
        'trace_event_worker' => true,
        'request_slowlog_timeout' => 1,
        'trace_flags' => SWOOLE_TRACE_ALL,
        'log_level' => SWOOLE_LOG_WARNING,
//        'log_file' => '/dev/null',
    ]);
    $serv->on("workerStart", function ($serv, $wid) use ($pm) {
        $pm->wakeup();
        global  $argv;
        if ($serv->taskworker) {
            swoole_set_process_name('php '.$argv[0].': task worker #'.$wid);
        } else {
            swoole_set_process_name('php '.$argv[0].': event worker #'.$wid);
        }
    });
    $serv->on('task', function (swoole_server $serv, $task_id, $worker_id, $data) {
        sleep(2);
        $serv->send($data[2], "Task Finish\n");
    });
    $serv->on('finish', function (swoole_server $serv, $fd, $rid, $data) {

    });
    $serv->on('connect', function (swoole_server $serv, $fd) {
        $serv->task([str_repeat("A", 1024 * 1024 * 2), 'task', $fd]);
    });
    $serv->on('receive', function ($serv, $fd, $reactor_id, $data) {
        sleep(2);
        $serv->send($fd, "Hello World\n");
    });
    $serv->start();
};

$pm->childFirst();
$pm->run();
?>
--EXPECT--
Task Finish
Hello World
