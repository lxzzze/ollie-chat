<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateUserMessageTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('user_message', function (Blueprint $table) {
            $table->id();
            $table->string('msg')->nullable()->comment('聊天内容');
            $table->string('img')->nullable()->comment('聊天图片');
            $table->integer('from_user_id')->comment('发送者user_id');
            $table->integer('to_user_id')->comment('接收者user_id');
            $table->integer('time')->comment('时间戳');
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
        Schema::dropIfExists('user_message');
    }
}
