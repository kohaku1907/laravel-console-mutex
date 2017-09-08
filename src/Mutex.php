<?php

namespace Illuminated\Console;

use Cache;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Redis as RedisFacade;
use NinjaMutex\Lock\FlockLock;
use NinjaMutex\Lock\MemcachedLock;
use NinjaMutex\Lock\MySqlLock;
use NinjaMutex\Lock\PredisRedisLock;
use NinjaMutex\Mutex as Ninja;

class Mutex
{
    private $command;
    private $strategy;
    private $ninja;

    public function __construct(Command $command)
    {
        $this->command = $command;
        $this->strategy = $this->getStrategy();
        $this->ninja = new Ninja($command->getMutexName(), $this->strategy);
    }

    public function getStrategy()
    {
        if (!empty($this->strategy)) {
            return $this->strategy;
        }

        switch ($this->command->getMutexStrategy()) {
            case 'mysql':
                return new MySqlLock(
                    config('database.connections.mysql.username'),
                    config('database.connections.mysql.password'),
                    config('database.connections.mysql.host'),
                    config('database.connections.mysql.port', 3306)
                );

            case 'redis':
                return $this->getRedisLock();

            case 'memcached':
                return new MemcachedLock(Cache::getStore()->getMemcached());

            case 'file':
            default:
                return new FlockLock(storage_path('app'));
        }
    }

    private function getRedisLock()
    {
        return new PredisRedisLock($this->getPredisClient());
    }

    public function getPredisClient()
    {
        return RedisFacade::connection();
    }

    public function __call($method, $parameters)
    {
        return call_user_func_array([$this->ninja, $method], $parameters);
    }
}
