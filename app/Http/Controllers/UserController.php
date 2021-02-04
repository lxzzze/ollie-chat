<?php

namespace App\Http\Controllers;

use App\Apply;
use App\Friend;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    //好友列表
    public function index(Request $request)
    {
        $user_id = $request->get('user_id');
        //好友列表
        $friend = Friend::query()->with('user')->where([['user_id',$user_id],['he_status',1],['we_status',1]])->get();
        //申请好友信息
        $applyList = Apply::query()->with('user')->where([['user_id',$user_id],['status',0]])->get();
        $applyCount = count($applyList);

        $data = ['friend'=>$friend,'applyList'=>$applyList,'applyCount'=>$applyCount];
        return ['errno'=>0,'data'=>$data];
    }

    //查询用户
    public function search(Request $request)
    {
        // 验证注册字段
        $validator = Validator::make($request->all(), [
            'email' => 'bail|required|email|max:100',
        ]);
        if ($validator->fails()) {
            return [
                'errno' => 1,
                'data' => $validator->errors()->first()
            ];
        }
        $email = $request->input('email');

        $user = User::query()->select('id','name','email','avatar')->where('email','=',$email)->first();

        return [
            'errno' => 0,
            'data' => $user
        ];
    }

    //添加好友
    public function addFriend(Request $request)
    {
        // 验证注册字段
        $validator = Validator::make($request->all(), [
            'friend_id' => 'bail|required|integer',
        ]);
        if ($validator->fails()) {
            return [
                'errno' => 1,
                'data' => $validator->errors()->first()
            ];
        }
        $user_id = $request->get('user_id');
        $friend_id = $request->input('friend_id');
        $message = $request->input('message');
        if ($user_id == $friend_id){
            return ['errno' => 1 ,'message' => '无法添加自己为好友'];
        }
        $check = Friend::query()->where([['user_id',$user_id],['friend_id',$friend_id],['he_status',1]])->exists();
        if ($check){
            return ['errno' => 0 ,'message' => '用户已经是您的好友了'];
        }
        $apply_check = Apply::query()->where([['from_id',$user_id],['user_id',$friend_id]])->exists();
        if ($apply_check){
            return ['errno' => 0 ,'message' => '您已提交添加好友申请,请等待用户审核通过'];
        }
        //新增数据
        Apply::query()->create([
            'user_id' => $friend_id,
            'from_id' => $user_id,
            'message' => $message
        ]);
        Friend::query()->create([
            'user_id' => $user_id,
            'friend_id' => $friend_id,
            'we_status' => 1,
            'he_status' => 0
        ]);
        return ['errno' => 0 ,'message' => '您已提交添加好友申请,请等待用户审核通过'];
    }

    //申请好友通过
    public function applyPass(Request $request)
    {
        // 验证注册字段
        $validator = Validator::make($request->all(), [
            'apply_id' => 'bail|required|integer',
        ]);
        if ($validator->fails()) {
            return [
                'errno' => 1,
                'message' => $validator->errors()->first()
            ];
        }
        $user_id = $request->get('user_id');
        $apply_id = $request->input('apply_id');
        $apply = Apply::query()->find($apply_id);
        if (!$apply || ($apply &&$apply->user_id != $user_id)){
            return [
                'errno' => 1,
                'message' => '数据出错,请刷新重试'
            ];
        }
        $apply->status = 1;
        $apply->save();
        Friend::query()->create([
            'user_id' => $user_id,
            'friend_id' => $apply->from_id,
            'we_status' => 1,
            'he_status' => 1
        ]);
        Friend::query()->where([['user_id',$apply->from_id],['friend_id',$user_id]])->update(['he_status'=>1]);
        return [
            'errno' => 0,
            'message' => '成功添加好友'
        ];
    }

}
