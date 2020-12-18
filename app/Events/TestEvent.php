<?php

namespace App\Events;

use App\Listeners\TestEventListener;
use Hhxsv5\LaravelS\Swoole\Task\Event;

class TestEvent extends Event
{
    private $data;

    protected $listeners = [
        TestEventListener::class
    ];

    public function __construct($data)
    {
        $this->data = $data;
    }

    public function getData()
    {
        return $this->data;
    }
}
