import Axios from './axios';

const Service = {
    // 登录接口
    loginUser: data => Axios.post('/login', data),
    // 注册接口
    RegisterUser: data => Axios.post('/register', data),
    // 获取当前房间所有历史聊天记录
    RoomHistoryAll: data => Axios.get('/history/message', {
        params: data
    }),
    //获取与当前好友历史聊天记录
    RoomFriendHistory: data => Axios.get('/history/friendMessage',{
        params: data
    }),
    // 机器人
    getRobotMessage: data => Axios.get('/robot', {
        params: data
    }),
    // 上传图片
    postUploadFile: data => Axios.post('/file/uploadimg', data, {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    }),
    // 上传头像
    postUploadAvatar: data => Axios.post('/file/avatar', data, {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded'
        }
    }),

    //搜索用户
    searchUser: data => Axios.get(`/user/search?email=${data.email}&api_token=${data.api_token}`),
    //添加好友
    addFriend : data => Axios.get(`/user/addFriend?friend_id=${data.friend_id}&api_token=${data.api_token}&message=${data.message}`),

};

export default Service;
