<template>
    <div>
        <div class="container">
            <div class="title">
                <mu-appbar title="Title">
                    <mu-button icon slot="left" @click="goback">
                        <mu-icon value="chevron_left"></mu-icon>
                    </mu-button>
                    <div class="center">
                        {{ getFriendStatus }}
<!--                        聊天({{Object.keys(getUsers).length}})-->
                    </div>
                    <mu-button icon slot="right" @click="">
                        <mu-icon value="people"></mu-icon>
                    </mu-button>
                </mu-appbar>
            </div>
            <div class="chat-inner">
                <div class="chat-container">
                    <div v-if="getFriendInfo.length === 0" class="chat-no-people">暂无消息,赶紧来占个沙发～</div>
                    <div v-if="getFriendInfo.length !== 0 && isloading" class="chat-loading">
                        <div class="lds-css ng-scope">
                            <div class="lds-rolling">
                                <div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <!-- <div v-if="getInfos.length > 0" class="chat-top">到顶啦~</div> -->
                    <Message
                        v-for="obj in getFriendInfo"
                        :key="obj._id"
                        :is-self="obj.userid === userid"
                        :name="obj.username"
                        :head="obj.src"
                        :msg="obj.msg"
                        :img="obj.img"
                        :mytime="obj.time"
                        :container="container"
                    ></Message>
                    <div class="clear"></div>
                </div>
            </div>
            <div class="bottom">
                <div class="functions">
                    <div class="fun-li" @click="imgupload">
                        <i class="icon iconfont icon-camera"></i>
                    </div>
                    <div class="fun-li emoji">
                        <i class="icon iconfont icon-emoji"></i>
                        <div class="emoji-content" v-show="getEmoji">
                            <div class="emoji-tabs">
                                <div class="emoji-container" ref="emoji">
                                    <div class="emoji-block"
                                         :style="{width: Math.ceil(emoji.people.length / 5) * 48 + 'px'}">
                                        <span v-for="(item, index) in emoji.people" :key="index">{{item}}</span>
                                    </div>
                                    <div class="emoji-block"
                                         :style="{width: Math.ceil(emoji.nature.length / 5) * 48 + 'px'}">
                                        <span v-for="(item, index) in emoji.nature" :key="index">{{item}}</span>
                                    </div>
                                    <div class="emoji-block"
                                         :style="{width: Math.ceil(emoji.items.length / 5) * 48 + 'px'}">
                                        <span v-for="(item, index) in emoji.items" :key="index">{{item}}</span>
                                    </div>
                                    <div class="emoji-block"
                                         :style="{width: Math.ceil(emoji.place.length / 5) * 48 + 'px'}">
                                        <span v-for="(item, index) in emoji.place" :key="index">{{item}}</span>
                                    </div>
                                    <div class="emoji-block"
                                         :style="{width: Math.ceil(emoji.single.length / 5) * 48 + 'px'}">
                                        <span v-for="(item, index) in emoji.single" :key="index">{{item}}</span>
                                    </div>
                                </div>
                                <div class="tab">
                                    <!-- <a href="#hot"><span>热门</span></a>
                                    <a href="#people"><span>人物</span></a> -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="chat">
                    <div class="input" @keyup.enter="submess">
                        <input type="text" v-model="chatValue">
                    </div>
                    <mu-button class="demo-raised-button" primary @click="submess">发送</mu-button>
                </div>
                <input id="inputFile" name='inputFile' type='file' multiple='mutiple' accept="image/*;capture=camera"
                       style="display: none" @change="fileup">
            </div>
        </div>
    </div>
</template>

<script type="text/ecmascript-6" scoped>
    import {mapGetters, mapState} from 'vuex';
    import {inHTMLData} from 'xss-filters-es6';
    import emoji from '../utils/emoji';
    import {setItem, getItem} from '../utils/localStorage';
    import {queryString} from '../utils/queryString';
    import Message from '../components/Message';
    import loading from '../components/loading';
    import Alert from '../components/Alert';
    import debounce from 'lodash/debounce';
    import url from '../api/server';
    import {setTimeout} from 'timers';
    import ios from '../utils/ios';
    import socket from '../socket';
    import Axios from '../api/axios';


    export default {
        data() {
            const notice = getItem('notice') || {};
            const {noticeBar, noticeVersion} = notice;
            return {
                isloading: false,
                friendId: '',
                container: {},
                chatValue: '',
                emoji: emoji,
                current: 1,
                openSimple: false,
                noticeBar: !!noticeBar,
                noticeList: [],
                noticeVersion: noticeVersion || '20181222',
                token:null,
                message:[],

            };
        },
        async created() {
            this.token = getItem('token');
            const friendId = queryString(window.location.href, 'friendId');
            this.friendId = friendId;
            if (!friendId) {
                this.$router.push({path: '/'});
            }
            if (!this.userid) {
                // 防止未登录
                this.$router.push({path: '/login'});
            }

        },
        async mounted() {
            // 微信 回弹 bug
            ios();
            this.container = document.querySelector('.chat-inner');
            // socket内部，this指针指向问题
            const that = this;
            await this.$store.commit('setRoomDetailInfos');
            await this.$store.commit('setTotal', 0);
            const obj = {
                name: this.userid,
                src: this.src,
                friendId: this.friendId,
                api_token: this.token
            };
            socket.emit('friendChat', obj);
            socket.on('friendChat', function (obj) {
                that.$store.commit('setFriendStatus', obj);
            });
            // socket.on('roomout', function (obj) {
            //     that.$store.commit('setUsers', obj);
            // });
            loading.show();
            setTimeout(async () => {
                const data = {
                    total: +this.getFriendTotal,
                    current: this.current,
                    friendId: this.friendId,
                    api_token: this.token
                };
                this.isloading = true;
                await this.$store.dispatch('getFriendHistory', data);
                this.isloading = false;
                loading.hide();
                this.$nextTick(() => {
                    this.container.scrollTop = 10000;
                });
            }, 500);

            this.container.addEventListener('scroll', debounce(async (e) => {
                if (e.target.scrollTop >= 0 && e.target.scrollTop < 50) {
                    this.$store.commit('setFriendCurrent', this.getFriendCurrent + 1);
                    const data = {
                        total: +this.getFriendTotal,
                        current: this.getFriendCurrent,
                        friendId: this.friendId,
                        api_token: this.token
                    };
                    this.isloading = true;
                    await this.$store.dispatch('getFriendHistory', data);
                    this.isloading = false;
                }
            }, 50));

            this.$refs.emoji.addEventListener('click', function (e) {
                var target = e.target || e.srcElement;
                if (!!target && target.tagName.toLowerCase() === 'span') {
                    that.chatValue = that.chatValue + target.innerHTML;
                }
                e.stopPropagation();
            });
        },
        methods: {
            goback() {
                // const obj = {
                //     name: this.userid,
                //     roomid: this.roomid,
                //     api_token: this.token,
                // };
                // socket.emit('roomout', obj);
                this.$router.goBack();
                this.$store.commit('setTab', true);
                this.$store.commit('setFriendCurrent', 0);
            },
            setLog() {
                // 版本更新日志
            },
            fileup() {
                const that = this;
                const file1 = document.getElementById('inputFile').files[0];
                if (file1) {
                    const formdata = new window.FormData();
                    formdata.append('file', file1);
                    formdata.append('api_token', that.token);
                    formdata.append('roomid', that.friendId);
                    //上传图片
                    Axios.post('/file/uploadimg', formdata, {headers: {'Content-Type': 'application/x-www-form-urlencoded'}})
                        .then((res) => {
                            if (res.data.data.errno == 200){
                                let img = res.data.data.data.img;
                                const obj = {
                                    username: that.userid,
                                    src: that.src,
                                    img: img,
                                    msg: '',
                                    friendId: that.friendId,
                                    time: new Date(),
                                    api_token: that.token
                                };
                                socket.emit('friendMessage', obj);
                                this.$nextTick(() => {
                                    this.container.scrollTop = 10000;
                                });
                            }
                        });
                } else {
                    console.log('必须有文件');
                }
            },
            imgupload() {
                const file = document.getElementById('inputFile');
                file.click();
            },
            submess() {
                // 判断发送信息是否为空
                if (this.chatValue !== '') {
                    if (this.chatValue.length > 200) {
                        Alert({
                            content: '请输入100字以内'
                        });
                        return;
                    }
                    const msg = inHTMLData(this.chatValue); // 防止xss

                    const obj = {
                        username: this.userid,
                        src: this.src,
                        img: '',
                        msg,
                        friendId: this.friendId,
                        time: new Date(),
                        api_token: this.token
                    };
                    // 传递消息信息
                    socket.emit('friendMessage', obj);
                    this.chatValue = '';
                } else {
                    Alert({
                        content: '内容不能为空'
                    });
                }
            }
        },
        computed: {
            ...mapGetters([
                'getEmoji',
                'getFriendInfo',
                'getFriendCurrent',
                'getFriendTotal',
                'getFriendStatus',
            ]),
            ...mapState([
                'isbind'
            ]),
            ...mapState({
                userid: state => state.userInfo.userid,
                src: state => state.userInfo.src,
                auth_token: state => state.userInfo.token,
            })
        },
        components: {
            Message
        }
    };
</script>

<style lang="stylus" rel="stylesheet/stylus" src="./Chat.styl" scoped></style>
