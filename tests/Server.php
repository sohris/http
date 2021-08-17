<?php
include "vendor/autoload.php";

use React\Http\Message\Response;
use React\Socket\Connector;
use Sohris\Core\Loop;
use Sohris\Thread\Process;
use Sohris\Thread\Thread;

$certificateData = array(
    "countryName" => "US",
    "stateOrProvinceName" => "Texas",
    "localityName" => "Houston",
    "organizationName" => "DevDungeon.com",
    "organizationalUnitName" => "Development",
    "commonName" => "DevDungeon",
    "emailAddress" => "nanodano@devdungeon.com"
);

// Generate certificate
$privateKey = openssl_pkey_new();
$certificate = openssl_csr_new($certificateData, $privateKey);
$certificate = openssl_csr_sign($certificate, null, $privateKey, 365);

// Generate PEM file
$pem_passphrase = 'abracadabra'; // empty for no passphrase
$pem = array();
openssl_x509_export($certificate, $pem[0]);
openssl_pkey_export($privateKey, $pem[1], $pem_passphrase);
$pem = implode($pem);

// Save PEM file
$pemfile = './server.pem';
file_put_contents($pemfile, $pem);


$loop = Loop::getLoop();

$default_port = 50010;
$workers_socket = [];
$workers = 6;
$used_ports = [];
$threads = [];

for ($i = 0; $i < $workers; $i++) {
    unset($thread1);
    echo "creating thread $i" . PHP_EOL;
    $port = $default_port + $i;
    $used_ports[] = $port;
    $thread1 = new Thread;
    $thread1->child(function (Process $process) use ($port) {
        $loop = Loop::newLoop();
        $http_socket = new \React\Socket\Server("0.0.0.0:" . $port, $loop);
        $server = new \React\Http\Server($loop, function () use ($port) {
            return new Response(200, [], "aaaa $port");
        });
        $server->listen($http_socket);

        $loop->run();
    });
    $thread1->setName("http_worker_" . $i);
    $threads[] = $thread1;
}
$connector = new Connector($loop);
echo "Total threads => " . \count($threads) . PHP_EOL;

$socket = new \React\Socket\Server("0.0.0.0:80", $loop);

$socket->on('connection', function (React\Socket\ConnectionInterface $connection) use (&$connector, &$used_ports) {
    $connection->on('data', function ($data) use ($connection, &$connector, &$used_ports) {
        $next_port = \current($used_ports);
        if (\is_null($next_port) || empty($next_port)) {
            \reset($used_ports);
            $next_port = \current($used_ports);
        }
        \next($used_ports);

        $connector->connect("0.0.0.0:" . $next_port)->then(function (React\Socket\ConnectionInterface $connection2) use ($data, $connection) {
            $connection2->on('data', function ($data2) use ($connection, $connection2) {
                $connection->end($data2);
                $connection2->close();
            });

            $connection2->write($data);
        }, function ($e) {
            echo $e->getMessage();
        });
    });
});

foreach ($threads as $thread) {
    $thread->run();
}


$loop->run();
