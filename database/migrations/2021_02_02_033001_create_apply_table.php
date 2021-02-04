<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateApplyTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('apply', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id')->nullable()->comment('被申请添加的用户')->index();
            $table->integer('from_id')->nullable()->comment('申请添加的用户');
            $table->string('message')->nullable()->comment('添加好友原因');
            $table->integer('status')->default(0)->comment('1为已通过，0为未处理');
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
        Schema::dropIfExists('apply');
    }
}
