<?php
/**
 * WebSocket 服务通信处理器类
 * Author：学院君
 */

namespace App\Services;

use App\Services\WebSocket\Pusher;
use App\Services\WebSocket\SocketIO\Packet;
use App\Services\WebSocket\SocketIO\SocketIOParser;
use App\Services\WebSocket\WebSocket;
use Hhxsv5\LaravelS\Swoole\WebSocketHandlerInterface;
use Illuminate\Support\Facades\Log;
use Swoole\Http\Request;
use Swoole\WebSocket\Frame;
use Swoole\WebSocket\Server;

class WebSocketHandler implements WebSocketHandlerInterface
{
    /**
     * @var WebSocket
     */
    protected $websocket;
    /**
     * @var Parser
     */
    protected $parser;

    public function __construct()
    {
        $this->websocket = app(WebSocket::class);
        $this->parser = app(SocketIOParser::class);

    }

    // 连接建立时触发
    public function onOpen(Server $server, Request $request)
    {
        if (!request()->input('sid')) {
            // 初始化连接信息适配 socket.io-client，这段代码不能省略，否则无法建立连接
            $payload = json_encode([
                'sid' => base64_encode(uniqid()),
                'upgrades' => [],
                'pingInterval' => config('laravels.swoole.heartbeat_idle_time') * 1000,
                'pingTimeout' => config('laravels.swoole.heartbeat_check_interval') * 1000,
            ]);
            $initPayload = Packet::OPEN . $payload;
            $connectPayload = Packet::MESSAGE . Packet::CONNECT;
            $server->push($request->fd, $initPayload);
            $server->push($request->fd, $connectPayload);
        }

        Log::info('WebSocket 连接建立:' . $request->fd);
        $payload = [
            'sender'    => $request->fd,
            'fds'       => [$request->fd],
            'broadcast' => false,
            'assigned'  => false,
            'event'     => 'message',
            'message'   => '欢迎访问聊天室',
        ];
        $pusher = Pusher::make($payload, $server);
        $pusher->push($this->parser->encode($pusher->getEvent(), $pusher->getMessage()));
    }

    // 收到消息时触发
    public function onMessage(Server $server, Frame $frame)
    {
        // $frame->fd 是客户端 id，$frame->data 是客户端发送的数据
        Log::info("从 {$frame->fd} 接收到的数据: {$frame->data}");
        if ($this->parser->execute($server, $frame)) {
            // 跳过心跳连接处理
            return;
        }
        $payload = $this->parser->decode($frame);
        ['event' => $event, 'data' => $data] = $payload;
        $payload = [
            'sender' => $frame->fd,
            'fds'    => [$frame->fd],
            'broadcast' => false,
            'assigned'  => false,
            'event'     => $event,
            'message'   => $data,
        ];
        $pusher = Pusher::make($payload, $server);
        $pusher->push($this->parser->encode($pusher->getEvent(), $pusher->getMessage()));
    }

    // 连接关闭时触发
    public function onClose(Server $server, $fd, $reactorId)
    {
        Log::info('WebSocket 连接关闭:' . $fd);
    }
}
