<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

class SocketIOController extends Controller
{
    protected $transports = ['polling', 'websocket'];

    public function upgrade(Request $request)
    {
        if (! in_array($request->input('transport'), $this->transports)) {
            return response()->json(
                [
                    'code' => 0,
                    'message' => 'Transport unknown',
                ],
                400
            );
        }

        if ($request->has('sid')) {
//            return '\u0\u298:40';
            return '1:6';
        }

        $payload = json_encode([
            'sid' => base64_encode(uniqid()),
            'upgrades' => ['websocket'],
            'pingInterval' => config('laravels.swoole.heartbeat_idle_time') * 1000,
            'pingTimeout' => config('laravels.swoole.heartbeat_check_interval') * 1000,
        ]);
        return response('98:0' . $payload . '2:40');
    }

    public function ok()
    {
        return response('ok');
    }
}
