<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//Route::get('/history/message', 'MessageController@history');

Route::middleware(['auth:api','authUser'])->group(function () {
    //查看公共页面历史聊天数据
    Route::get('/history/message', 'MessageController@history');
    //好友聊天记录
    Route::get('/history/friendMessage','MessageController@friendMessage');
    //上传图片发送信息
    Route::post('/file/uploadimg', 'FileController@uploadImage');
    //上传头像
    Route::post('/file/avatar', 'FileController@avatar');
    //搜索用户
    Route::get('/user/search','UserController@search');
    //添加好友
    Route::get('/user/addFriend','UserController@addFriend');
    //好友列表
    Route::get('/user/list','UserController@index');
    //好友申请通过
    Route::get('/user/applyPass','UserController@applyPass');
});
//用户注册
Route::post('/register', 'AuthController@register');
//用户登录
Route::post('/login', 'AuthController@login');
