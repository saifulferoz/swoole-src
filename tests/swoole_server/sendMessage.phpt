--TEST--
swoole_server: send message
--SKIPIF--
<?php
require __DIR__ . '/../include/skipif.inc';
skip_if_in_valgrind();
?>
--FILE--
<?php
require __DIR__ . '/../include/bootstrap.php';

/**

 * Time: 下午4:34
 */

$simple_tcp_server = __DIR__ . "/../include/api/swoole_server/opcode_server.php";
$port = get_one_free_port();

start_server($simple_tcp_server, TCP_SERVER_HOST, $port);

suicide(2000);
usleep(500 * 1000);

makeCoTcpClient(TCP_SERVER_HOST, $port, function(Client $cli) use($port) {
    $r = $cli->send(opcode_encode("sendMessage", ["SUCCESS", 1]));
    Assert::assert($r !== false);
}, function(Client $cli, $recv) {
    list($op, $msg) = opcode_decode($recv);
    echo $msg;
    swoole_event_exit();
});

?>
--EXPECT--
SUCCESS
