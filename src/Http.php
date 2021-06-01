<?php
namespace Sohris\Http;

use React\EventLoop\LoopInterface;
use Sohris\Core\Interfaces\ModuleInterface;
use Sohris\Core\Server;

class Http implements ModuleInterface
{
    private $module_name = "Sohris_Http";

    private $socket;

    private $server;

    private $configs = array();

    public function __construct(LoopInterface $loop)
    {  
        $this->configs = Utils::getConfigFiles('http');

        $server = Server::getServer();

        $server->on("running", function (){
            echo "load";
        });

        if(!isset($this->configs['workers']) || !is_numeric($this->configs['workers']))
        {
            $this->configs['workers'] = 1;
        }

        $workers = array();
        for($i = 0 ; $i < $this->configs['workers'] ; $i++)
        {
            $workers[] = new Worker($i);
        }


        $this->server = new \React\Http\Server($loop,new Balancer($workers));
        $this->socket = new \React\Socket\Server('0.0.0.0:80', $loop);
        $this->server->listen($this->socket);

    }

    public function getName():string
    {
        return $this->module_name;

    }

}