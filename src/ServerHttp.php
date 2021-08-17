<?php

namespace Sohris\Http;

use React\Socket\ServerInterface;

use Evenement\EventEmitter;
use React\EventLoop\LoopInterface;
class ServerHttp extends EventEmitter implements ServerInterface
{
        private $master;
        private $loop;
        private $listening = false;
    
        public function __construct($stream, LoopInterface $loop)
        {
            $this->loop = $loop;
    
            $this->master = $stream;
            if (false === $this->master) {
                throw new \RuntimeException('Failed to listen on "' . $uri . '": ' . $errstr, $errno);
            }
            \stream_set_blocking($this->master, false);
    
            $this->resume();
        }
    
        public function getAddress()
        {
            if (!\is_resource($this->master)) {
                return null;
            }
    
            $address = \stream_socket_get_name($this->master, false);
    
            // check if this is an IPv6 address which includes multiple colons but no square brackets
            $pos = \strrpos($address, ':');
            if ($pos !== false && \strpos($address, ':') < $pos && \substr($address, 0, 1) !== '[') {
                $address = '[' . \substr($address, 0, $pos) . ']:' . \substr($address, $pos + 1); // @codeCoverageIgnore
            }
    
            return 'tcp://' . $address;
        }
    
        public function pause()
        {
            if (!$this->listening) {
                return;
            }
    
            $this->loop->removeReadStream($this->master);
            $this->listening = false;
        }
    
        public function resume()
        {
            if ($this->listening || !\is_resource($this->master)) {
                return;
            }
    
            $that = $this;
            $this->loop->addReadStream($this->master, function ($master) use ($that) {
                $newSocket = @\stream_socket_accept($master, 0);
                if (false === $newSocket) {
                    $that->emit('error', array(new \RuntimeException('Error accepting new connection')));
    
                    return;
                }
                $that->handleConnection($newSocket);
            });
            $this->listening = true;
        }
    
        public function close()
        {
            if (!\is_resource($this->master)) {
                return;
            }
    
            $this->pause();
            \fclose($this->master);
            $this->removeAllListeners();
        }
    
        /** @internal */
        public function handleConnection($socket)
        {
            $this->emit('connection', array(
                new Connection($socket, $this->loop)
            ));
        }
           
}