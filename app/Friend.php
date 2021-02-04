<?php


namespace App;


use Illuminate\Database\Eloquent\Model;

class Friend extends Model
{
    protected $table = 'friends';
    public $timestamps = true;
    protected $guarded = [];

    //关联用户
    public function user()
    {
        return $this->hasOne(User::class,'id','friend_id');
    }
}
