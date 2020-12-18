<?php

namespace App\Listeners;

use App\Events\MessageReceived;
use Hhxsv5\LaravelS\Swoole\Task\Listener;
use Illuminate\Support\Facades\Log;

class MessageListener extends Listener
{


    /**
     * Handle the event.
     *
     * @return void
     */
    public function handle()
    {
        $message = $this->event->getData();
        Log::info(__CLASS__ . ': 开始处理', $message->toArray());
        if ($message && $message->user_id && $message->room_id && ($message->msg || $message->img)) {
            $message->save();
            Log::info(__CLASS__ . ': 处理完毕');
        } else {
            Log::error(__CLASS__ . ': 消息字段缺失，无法保存');
        }
    }
}
