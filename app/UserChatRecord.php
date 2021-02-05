<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserChatRecord extends Model
{
    protected $table = 'user_chat_record';
    public $timestamps = true;
    protected $guarded = [];
}
