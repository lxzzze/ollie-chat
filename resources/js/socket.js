// // 通过 Socket.io 客户端发起 WebSocket 请求
// import io from 'socket.io-client'
// const socket = io.connect(process.env.APP_URL + ':' + process.env.LARAVELS_LISTEN_PORT + '/ws/')
// export default socket

// 通过 socket.io 客户端进行 WebSocket 通信
import io from 'socket.io-client';
// const socket = io('http://todo-s.test', {
//     path: '/ws',
//     transports: ['websocket']
// });
const socket = io('http://todo-s.test',{transports: ['websocket']});
// const socket = io('http://todo-s.test');


export default socket;
