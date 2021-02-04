<?php


namespace App;


use Illuminate\Database\Eloquent\Model;

class Apply extends Model
{
    protected $table = 'apply';
    public $timestamps = true;
    protected $guarded = [];

    //关联用户
    public function user()
    {
        return $this->hasOne(User::class,'id','from_id');
    }
}
