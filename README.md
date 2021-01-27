# ollie-chat

# 基于laravel+swoole+vue开发的聊天室

本项目根据学院君的实战教程[Swoole 从入门到实战教程](https://laravelacademy.org/books/swoole-tutorial)的实战项目基础上进行改编开发,当然项目的大框架没有变
,由于学院君没有再维护该项目,所以在练习的初期也遇到了一些问题,我就在这些问题的基础上进行相应的解决处理,以满足我对项目的改造需求。

在项目学习的过程中,由于遇到的一些问题,并阅读学习了swoole和hhxsv5/laravel-s扩展包等相关文档,这里先对项目的运行流程做一些介绍。

在开发使用聊天室的功能,我们需要使用websocket协议。WebSocket是一种在单个TCP连接上进行全双工通信的协议。
它的最大特点就是，服务器可以主动向客户端推送信息，客户端也可以主动向服务器发送信息，是真正的双向平等对话，属于服务器推送技术的一种。

而swoole通过内置的WebSocket服务器支持，通过几行PHP代码就可以写出一个异步IO的多进程的WebSocket服务器。

使用hhxsv5/laravel-s扩展包就可以将swoole和laravel结合起来。  

`LaravelS 是一个胶水项目，用于快速集成 Swoole 到 Laravel 或 Lumen ，然后赋予它们更好的性能、更多可能性。`

通过使用laravels运行`php bin/laravels start -d`使服务可以常驻内存中,这样就比正常的laravel请求流程中,节省了框架初始化,启动服务的开销。提高了laravel的性能。
当然具体的扩展包使用细节可以查看文档[hhxsv5/laravel-s](https://github.com/hhxsv5/laravel-s/blob/master/README-CN.md#%E4%BD%BF%E7%94%A8swooletable)
     
通过添加./app/Services/WebSocket/WebSocketHandler.php为websocket通信处理器。其实就是一个类,其中内部有三个方法
onOpen,onMessage,onClose分别对应连接建立,收到消息,连接断开所会触发的事件。并在config/laravels.php中添加相关配置
```
'websocket' => [
    'enable' => true,
    'handler' => \App\Services\WebSocket\WebSocketHandler::class,
]
```

swoole还支持其他事件ServerStart发生在Master进程启动时触发,WorkerStart发生在Worker/Task进程启动完成后触发,
WorkerStop发生在Worker/Task进程正常退出后触发。这里添加了workerStart事件,为进程启动时触发的方法,主要做一些组件绑定到容器的初始化工作,
包括socket.io通信协议解析,message事件解析类等。代码如下

```
public function handle(Server $server, $workerId)
    {
        $isWebsocket = config('laravels.websocket.enable') == true;
        if (!$isWebsocket) {
            return;
        }
        // WorkerStart 事件发生时 Laravel 已经初始化完成，在这里做一些组件绑定到容器的初始化工作最合适
        //注册协议解析类
        app()->singleton(Parser::class, function () {
            $parserClass = config('laravels.websocket.parser');
            return new $parserClass;
        });
        //为协议解析类添加别名
        app()->alias(Parser::class, 'swoole.parser');
        //注册聊天室服务类,这里由于我在开发过程中遇到bug,所以自己写了实现,所以没有用到这里的功能
        app()->singleton(RoomContract::class, function () {
            $driver = config('laravels.websocket.drivers.default', 'table');
            $driverClass = config('laravels.websocket.drivers.' . $driver);
            $driverConfig = config('laravels.websocket.drivers.settings.' . $driver);
            $roomInstance = new $driverClass($driverConfig);
            if ($roomInstance instanceof RoomContract) {
                $roomInstance->prepare();
            }
            return $roomInstance;
        });
        //为聊天室服务类添加别名
        app()->alias(RoomContract::class, 'swoole.room');
        //注册websocket核心处理类
        app()->singleton(WebSocket::class, function (Container $app) {
            return new WebSocket($app->make(RoomContract::class));
        });
        //为websocket核心处理类添加别名
        app()->alias(WebSocket::class, 'swoole.websocket');
        
        // 引入 Websocket 路由文件
        $routePath = base_path('routes/websocket.php');
        require $routePath;
    }
```

这里还要在.env文件中添加相关的配置,分别用于指定 Swoole HTTP/WebSocket 服务器运行的IP地址和端口

```
LARAVELS_LISTEN_IP=127.0.0.1
LARAVELS_LISTEN_PORT=5200
```

添加Nginx虚拟主机,配置如下,这里的配置就是将配置的指定路径/socket.io协议升级为websocket,并将请求转发给127.0.0.1:5200进行处理

```
upstream laravels {
    # Connect IP:Port
    server 127.0.0.1:5200 weight=5 max_fails=3 fail_timeout=30s;
    keepalive 16;
}
server {
    listen 80;

    server_name todo-s.test;
    root ./www/wwwroot/ollie-chat/public;

    index index.php index.html index.htm;

    # Nginx handles the static resources(recommend enabling gzip), LaravelS handles the dynamic resource.
    location / {
        try_files $uri @laravels;
    }


    # 处理 WebSocket 通信
    location ^~ /socket.io/ {
        proxy_http_version 1.1;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Real-PORT $remote_port;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header Server-Protocol $server_protocol;
        proxy_set_header Server-Name $server_name;
        proxy_set_header Server-Addr $server_addr;
        proxy_set_header Server-Port $server_port;
        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_pass http://laravels;
    }

    location @laravels {
        proxy_http_version 1.1;
        proxy_set_header Connection "";
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Real-PORT $remote_port;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header Host $http_host;
        proxy_set_header Scheme $scheme;
        proxy_set_header Server-Protocol $server_protocol;
        proxy_set_header Server-Name $server_name;
        proxy_set_header Server-Addr $server_addr;
        proxy_set_header Server-Port $server_port;
        proxy_pass http://laravels;
    }
}
```
