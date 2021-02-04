<?php
namespace App\Http\Controllers;

use App\Http\Resources\MessageResource;
use App\Message;
use App\User;
use App\UserMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

//use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class MessageController extends Controller
{
    /**
     * 获取历史聊天记录
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function history(Request $request)
    {
        $roomId = intval($request->get('roomid'));
        $current = intval($request->get('current'));
        $total = intval($request->get('total'));
        if ($roomId <= 0 || $current <= 0) {
            Log::error('无效的房间和页面信息');
            return response()->json([
                'data' => [
                    'errno' => 1
                ]
            ]);
        }
        // 获取消息总数
        $messageTotal = Message::where('room_id', $roomId)->count();
        $limit = 20;  // 每页显示20条消息
        $skip = ($current - 1) * 20;  // 从第多少条消息开始
        // 分页查询消息
        $messages = Message::query()->with('user')->where('room_id', $roomId)
            ->skip($skip)->take($limit)->orderBy('created_at','desc')->get()
            ->map(function ($item){
                return [
                    'id' => $item->id,
                    'userid' => $item->user->email,
                    'username' => $item->user->name,
                    'src' => $item->user->avatar,
                    'msg' => $item->msg,
                    'img' => $item->img,
                    'roomid' => $item->room_id,
                    'time' => $item->created_at
                ];
            })->toArray();
        $messages = array_reverse($messages);
        // 返回响应信息
        return response()->json([
            'data' => [
                'errno' => 0,
                'data' => $messages,
                'total' => $messageTotal,
                'current' => $current
            ]
        ]);
    }

    //用户聊天记录
    public function friendMessage(Request $request)
    {
        $friendId = intval($request->get('friendId'));
        $current = intval($request->get('current'));
        if ($friendId <= 0 || $current <= 0) {
            Log::error('无效的房间和页面信息');
            return response()->json([
                'data' => ['errno' => 1]
            ]);
        }
        $user_id = $request->get('user_id');
        $weUser = User::query()->find($user_id);
        $heUser = User::query()->find($friendId);
        // 获取消息总数
        $messageTotal = UserMessage::query()->where([['from_user_id',$user_id],['to_user_id',$friendId]])
                        ->orWhere([['from_user_id',$friendId],['to_user_id',$user_id]])->count();
        $limit = 20;  // 每页显示20条消息
        $skip = ($current - 1) * 20;  // 从第多少条消息开始
        $messages = UserMessage::query()->with('user')->where([['from_user_id',$user_id],['to_user_id',$friendId]])
            ->orWhere([['from_user_id',$friendId],['to_user_id',$user_id]])->skip($skip)
            ->take($limit)->orderBy('created_at','desc')->get()
            ->map(function ($item) use ($weUser,$heUser,$friendId){
                $email = $item->from_user_id == $weUser->id ? $weUser->email : $heUser->email;
                $name = $item->from_user_id == $weUser->id ? $weUser->name : $heUser->name;
                $avatar = $item->from_user_id == $weUser->id ? $weUser->avatar : $heUser->avatar;
                return [
                    'id' => $item->id,
                    'userid' => $email,
                    'username' => $name,
                    'src' => $avatar,
                    'msg' => $item->msg,
                    'img' => $item->img,
                    'friend_id' => $friendId,
                    'time' => $item->created_at
                ];
            })->toArray();
        $messages = array_reverse($messages);
        // 返回响应信息
        return response()->json([
            'data' => [
                'errno' => 0,
                'data' => $messages,
                'total' => $messageTotal,
                'current' => $current
            ]
        ]);
    }

}
