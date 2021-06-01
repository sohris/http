<?php 

class ForkException extends \RuntimeException {}
class ModeException extends \RuntimeException {}

class Thread
{
    protected const READ_BUFFER = 1024 * 4;
    protected $process;
    protected $callback;
    protected $child;
    protected $parent;

    public function __construct(callable $process)
    {
        if (php_sapi_name() !== 'cli') {
            throw new ModeException('threads are available in CLI mode only.');
        }

        pcntl_async_signals(true);
        $this->process = \Closure::bind($process, $this, static::class);
    }

    protected function respond(string $value): void
    {
        $write = [$this->parent];
        echo "writing... ";

        if (stream_select($read, $write, $except, 1)) {
            $socket = reset($write);
            flock($socket, LOCK_EX);
            fwrite($socket, $value);
            flock($socket, LOCK_UN);
            echo "done";
            posix_kill(posix_getppid(), SIGCHLD);
        }
        echo "\n";
    }

    protected function receive(): void
    {
        if (!$this->callback) {
            return;
        }
        echo "receiving... ";

        $read = [$this->child];

        if (stream_select($read, $write, $except, null)) {
            $socket = reset($read);
            flock($socket, LOCK_EX);
            $response = '';

            do {
                $buffer = fread($socket, static::READ_BUFFER);
                $response .= $buffer;
            } while (strlen($buffer) == static::READ_BUFFER);

            flock($socket, LOCK_UN);
            echo "done";
            call_user_func($this->callback, $response);
        } else {
            echo "cannot read :(\n";
        }
        echo "\n";
    }

    public function synchronize(callable $callback): self
    {
        $this->callback = $callback;

        return $this;
    }

    public function start(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        $pid = pcntl_fork();

        if ($pid == -1) {
            throw new ForkException('Cannot fork process');
        } elseif ($pid) {
            // parent
            fclose($pair[0]);
            $this->child = $pair[1];

            pcntl_signal(SIGCHLD, function () {
                $this->receive();
            });
        } else {
            fclose($pair[1]);
            $this->parent = $pair[0];
            call_user_func($this->process);
            exit(0);
        }
    }
}

register_shutdown_function(function () {
    echo posix_getpid() . " process finished\n";
});

echo "main process with pid = " . posix_getpid() . "\n";
$varFromBeginning = "begin!\n";

(new Thread(function () {
    echo "a text inside child\n";
    usleep(1000);
    $this->respond(str_pad("a very_long", 2000, '=') . "\n");
    usleep(1000);
    $this->respond("a myValue two\n");
    usleep(1000);
    $this->respond("a myValue three\n");
    usleep(10000);
    $this->respond("a myValue four\n");
}))->synchronize(function ($response) use (&$varFromBeginning) {
    echo $response;
    $varFromBeginning .= $response;
})->start();

echo "after the tread\n";

for ($i = 0; $i < 10; $i++) {
    echo "$i \n";
    usleep(100000);
}

pcntl_wait($status);
echo "$varFromBeginning\n";