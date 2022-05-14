<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserMessage extends Model
{
    use HasFactory;
    protected $table = "user_message";
    protected $fillable = [
        'sd_id',
        'rcv_id',
        'msg_id',
        'type',
        'seen',
    ];
    public function message()
    {
        return $this->belongsTo('App\Models\Message' , 'msg_id');
    }
}