<template>
    <div class="hello" v-loading="loading">
        <div>
            <mu-list toggle-nested>
                <mu-list-item button :ripple="false" nested :open="open === 'send'" @toggle-nested="open = arguments[0] ? 'send' : ''">
                    <mu-list-item-action>
                        <mu-icon value="how_to_reg"></mu-icon>
                    </mu-list-item-action>
                    <mu-list-item-title>好友申请</mu-list-item-title>
                    <mu-list-item-action v-if="applyCount != '0'">
                        <mu-badge color="secondary" :content="applyCount"></mu-badge>
                    </mu-list-item-action>

                    <mu-list-item v-for="(item,index) in applyList" :key="index" button :ripple="false" slot="nested" avatar>
                        <mu-list-item-action avatar>
                            <mu-avatar>
                                <img :src="item.user.avatar">
                            </mu-avatar>
                        </mu-list-item-action>
                        <mu-list-item-content>
                            <mu-list-item-title>{{ item.user.name }}</mu-list-item-title>
                            <mu-list-item-title>{{ item.message }}</mu-list-item-title>
                        </mu-list-item-content>
                        <mu-list-item-action>
                            <mu-button small color="primary" @click="applyPass(item.id)">申请通过</mu-button>
                        </mu-list-item-action>
                    </mu-list-item>
                </mu-list-item>
            </mu-list>
            <mu-divider></mu-divider>

            <mu-list>
                <mu-sub-header>通讯录</mu-sub-header>
                <mu-list-item button avatar v-for="(item,index) in friends" :key="index" @click="chatFriend(item.user.id)">
                    <mu-list-item-action avatar>
                        <mu-avatar>
                            <img :src="item.user.avatar">
                        </mu-avatar>
                    </mu-list-item-action>
                    <mu-list-item-content>
                        <mu-list-item-title>{{ item.user.name }}</mu-list-item-title>
                    </mu-list-item-content>
                    <mu-list-item-action>
                        <mu-icon value="chat_bubble"></mu-icon>
                    </mu-list-item-action>
                </mu-list-item>
                <p v-if="friends.length == 0" style="text-align:center">暂无好友,真凄凉</p>

            </mu-list>
        </div>
    </div>
</template>

<script>
    import {mapState} from "vuex";
    import Axios from '../api/axios';
    import Confirm from "../components/Confirm";

    export default {
        name: "friendsList",
        data(){
            return{
                open: '',
                friends:[],
                applyCount:'0',
                applyList:[],
                loading:false,
            }
        },
        mounted() {
            this.$store.commit("setTab", true);
            if (!this.auth_token){
                this.$router.push("/login");
                return;
            }
            this.friendsList();
        },
        methods:{
            friendsList(){
                this.loading = true;
                Axios.get(`/user/list?api_token=${this.auth_token}`)
                    .then(res => {
                        this.loading = false;
                        if (res.data.errno == 0){
                            this.applyList = res.data.data.applyList;
                            this.friends = res.data.data.friend;
                            this.applyCount = (res.data.data.applyCount).toString();
                        }
                    })
            },
            applyPass(apply_id){
                this.loading = true;
                Axios.get(`/user/applyPass?api_token=${this.auth_token}&apply_id=${apply_id}`)
                    .then(res => {
                        this.loading = false;
                        if (res.data.errno == 0){
                            this.$toast.success(res.data.message);
                            this.friendsList();
                        }else {
                            return this.$toast.error(res.data.message);
                        }
                    })
            },
            async chatFriend(friend_id){
                const uerId = this.userid;
                if (!uerId) {
                    const res = await Confirm({
                        title: "提示",
                        content: "聊天请先登录，但是你可以查看聊天记录哦~"
                    });
                    if (res === "submit") {
                        this.$router.push({path: "login"});
                    }
                    return;
                }
                this.$store.commit("setTab", false);
                this.$router.push({path: "/friendChat", query: {friendId: friend_id}});

            }
        },
        computed: {
            ...mapState({
                userid: state => state.userInfo.userid,
                src: state => state.userInfo.src,
                auth_token: state => state.userInfo.token,
            })
        }
    }
</script>

<style scoped>

</style>
