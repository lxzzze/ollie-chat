<?php

namespace App;

use Illuminate\Database\Eloquent\Model;

class UserMessage extends Model
{
    protected $table = 'user_message';
    public $timestamps = true;
    protected $guarded = [];

    //关联用户
    public function user()
    {
        return $this->hasOne(User::class,'id','from_user_id');
    }
}
