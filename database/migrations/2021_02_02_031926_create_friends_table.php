<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateFriendsTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('friends', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable()->comment('用户id')->index();
            $table->integer('friend_id')->nullable()->comment('好友用户id');
            $table->integer('we_status')->nullable()->comment('1为已添加，2为已拉黑,3为已删除');
            $table->integer('he_status')->nullable()->comment('1为已添加，2为已拉黑,3为已删除，只有当we_status和he_status为1时才允许发送消息');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('friends');
    }
}
