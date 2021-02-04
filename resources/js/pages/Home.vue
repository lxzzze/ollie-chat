<template>
    <div>
        <div class="header">
            <div class="head">
                <img :src="src" alt="">
            </div>
            <div class="name">
                {{userid}}
            </div>
            <div class="background">
                <img :src="src" alt="">
            </div>
        </div>
        <div class="content">
            <mu-dialog v-if="openSimple" v-loading="dialogLoading" data-mu-loading-size="24" title="添加好友" width="360" :open.sync="openSimple" :esc-press-close="false" :overlay-close="false">
                <mu-form ref="form" :model="validateForm" class="mu-demo-form">
                    <mu-text-field v-model="validateForm.user_email" label="用户邮箱" style="width: 80%" label-float placeholder="请输入用户邮箱以搜索用户" icon="account_circle"></mu-text-field>
                    <mu-form-item>
                        <mu-button flat color="primary" @click="searchUser"><mu-icon left value="search"></mu-icon>搜索</mu-button>
                        <mu-button flat color="primary" @click="closeSimpleDialog"><mu-icon left value="close"></mu-icon>关闭</mu-button>
                    </mu-form-item>
                </mu-form>
                <div v-if="searchData">
                    <mu-divider></mu-divider>
                    <mu-list>
                        <mu-list-item avatar button :ripple="false">
                            <mu-list-item-action>
                                <mu-avatar>
                                    <img :src="searchData.avatar">
                                </mu-avatar>
                            </mu-list-item-action>
                            <mu-list-item-title>{{ searchData.email }}</mu-list-item-title>
                        </mu-list-item>
                    </mu-list>
                    <mu-row>
                        <mu-col span="4"></mu-col>
                        <mu-col span="4">
                            <mu-button small color="primary" @click="addFriend(searchData.id)">添加好友</mu-button>
                        </mu-col>
                        <mu-col span="4"></mu-col>
                    </mu-row>
                </div>
            </mu-dialog>


            <mu-list>
                <mu-list-item button @click="changeAvatar">
                    <mu-list-item-action>
                        <mu-icon slot="left" value="send"/>
                    </mu-list-item-action>
                    <mu-list-item-title>修改头像</mu-list-item-title>
                </mu-list-item>
                <mu-list-item button @click="openSimpleDialog">
                    <mu-list-item-action>
                        <mu-icon slot="left" value="person"/>
                    </mu-list-item-action>
                    <mu-list-item-title>添加好友</mu-list-item-title>
                </mu-list-item>
                <mu-list-item button @click="handleGithub">
                    <mu-list-item-action>
                        <mu-icon slot="left" value="grade"/>
                    </mu-list-item-action>
                    <mu-list-item-title>Github地址</mu-list-item-title>
                </mu-list-item>
                <!--        <mu-list-item button @click="rmLocalData">-->
                <!--          <mu-list-item-action>-->
                <!--            <mu-icon slot="left" value="drafts"/>-->
                <!--          </mu-list-item-action>-->
                <!--          <mu-list-item-title>清除缓存</mu-list-item-title>-->
                <!--        </mu-list-item>-->
            </mu-list>
            <!--<mu-divider/>-->
        </div>
        <div class="logout">
            <mu-button @click="logout" class="demo-raised-button" full-width>退出</mu-button>
        </div>
        <div style="height:80px"></div>
    </div>
</template>

<script>
    import {mapState} from "vuex";
    import {clear, removeItem} from "../utils/localStorage";
    import Confirm from "../components/Confirm";
    import Alert from "../components/Alert";
    import url from '../api/server';
    import Axios from '../api/axios';


    export default {
        data() {
            return {
                dialogLoading: false,
                openSimple: false,
                validateForm:{
                    user_email:'',
                },
                searchData:null,
            };
        },
        async mounted() {
            this.$store.commit("setTab", true);
            if (!this.userid) {
                const data = await Confirm({
                    title: "提示",
                    content: "需要登录后才能查看哦~",
                    ok: "去登录",
                    cancel: "返回首页"
                });
                if (data === "submit") {
                    this.$router.push("/login");
                    return;
                }
                this.$router.push("/");
            }
        },
        methods: {
            changeAvatar() {
                this.$router.push("/avatar");
                this.$store.commit("setTab", false);
            },
            //查询用户
            async searchUser() {
                if (!this.validateForm.user_email){
                    return this.$toast.error('请输入邮箱');
                }
                const obj = {
                    email: this.validateForm.user_email,
                    api_token: this.auth_token
                };
                const res = await url.searchUser(obj);
                if (res.data.errno == 0){
                    if (!res.data.data){
                        this.searchData = null;
                        return this.$toast.error('未查询到任何用户');
                    }else {
                        this.searchData = res.data.data;
                    }
                }else{
                    return this.$toast.error(res.data.data);
                }
            },
            //添加好友
            addFriend(friend_id){
                 this.$prompt('输入好友添加备注').then(({ value }) => {
                    this.dialogLoading = true;
                    Axios.get(`/user/addFriend?friend_id=${friend_id}&api_token=${this.auth_token}&message=${value}`)
                        .then(res => {
                            this.dialogLoading = false;
                            if (res.data.errno == 0){
                                return this.$toast.success(res.data.message);
                            }else {
                                return this.$toast.error(res.data.message);
                            }
                        })
                });
            },
            // async rmLocalData() {
            //   const data = await Confirm({
            //     title: "提示",
            //     content: "清除缓存会导致更新历史再再次提醒，确定清除？"
            //   });
            //   if (data === "submit") {
            //     removeItem("update-20180916");
            //   }
            // },
            //退出
            async logout() {
                const data = await Confirm({
                    title: "提示",
                    content: "你忍心离开吗？"
                });
                if (data === "submit") {
                    clear();
                    this.$store.commit("setUserInfo", {
                        type: "userid",
                        value: ""
                    });
                    this.$store.commit("setUserInfo", {
                        type: "token",
                        value: ""
                    });
                    this.$store.commit("setUserInfo", {
                        type: "src",
                        value: ""
                    });
                    this.$store.commit("setUnread", {
                        room1: 0,
                        room2: 0
                    });
                    this.$router.push("/login");
                    this.$store.commit("setTab", false);
                }
            },
            handleGithub() {
                Alert({
                    content: "https://github.com/lxzzze/ollie-chat"
                });
            },
            openSimpleDialog () {
                this.openSimple = true;
            },
            closeSimpleDialog () {
                this.openSimple = false;
            }
            // handleTips() {
            //   Alert({
            //     title: "请我喝杯咖啡",
            //     html:
            //       '<div>' +
            //         '<img style="width: 200px;" src="https://xueyuanjun.com/wp-content/uploads/2019/05/e7156cfe0196dd7d7ea4f8f5f10b8d1a.jpeg" />' +
            //         '</div>'
            //   });
            // }
        },
        computed: {
            ...mapState({
                userid: state => state.userInfo.userid,
                src: state => state.userInfo.src,
                auth_token: state => state.userInfo.token,
            })
        }
    };
</script>

<style lang="stylus" rel="stylesheet/stylus" scoped>
    .header {
        position: relative;
        width: 100%;
        height: 200px;
        display: inline-block;

    .head {
        width: 80px;
        margin: 20px auto 0;

    img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
    }

    }

    .name {
        height: 50px;
        line-height: 50px;
        color: #ffffff;
        font-size: 18px;
        text-align: center;
    }

    .background {
        position: absolute;
        top: 0;
        left: 0;
        width: 100%;
        height: 200px;
        z-index: -1;
        filter: blur(10px);

    img {
        width: 100%;
        height: 100%;
    }

    }
    }

    .logout {
        width: 200px;
        margin: 0 auto;

    .mu-raised-button {
        background: #ff4081;
        color: #fff;
    }

    }
</style>
