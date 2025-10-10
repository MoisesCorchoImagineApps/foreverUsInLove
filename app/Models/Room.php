<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use App\Models\GroupChat;

class Room extends Model
{
    protected $table = 'room';
    protected $primaryKey = 'room_id';
    protected $appends = ['unread_message_count'];

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     protected $fillable = [
        'room_name',        
        'room_icon',
        'room_icon1',
        'total_users',
        'channel_name',
        'date_from',
        'date_to',
        'status',
        'call_request_status',
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function getUnreadMessageCountAttribute()
    {

        return GroupChat::where(['room_id'=>$this->room_id,'read_status'=>'Unread'])->count();
        
    }

    public function getRoomIconAttribute($value)
    {
        return !empty($value) ? url('/')."/room_icon/".$value : '';
    }

    public function getRoomIcon1Attribute($value)
    {
        return !empty($value) ? url('/')."/room_icon/".$value : '';
    }
    

    public function roomJoinMember()
    {
        return $this->hasMany('App\Models\RoomJoinMember', 'room_id','room_id')->with(['user','user.userImages']);
    }

    public function getTotalUsersAttribute($value){
        return $this->roomJoinMember->count();
    }




    protected function addUpdateRoom($params = [])
    {
        if(empty($params)) {
            return false;   
        }
        $id = Arr::get($params, 'room_id', 0);
        
        if($id > 0) {
            $model = self::find($id);
            if(!$model) {
                $model = new self();
            }
        } else {
            $model = new self();
        }

        $params        = Arr::except($params, ['room_id']);
        $fillableFiled = $model->getFillable();
        $fillableFiled = array_intersect_key($params, array_flip($fillableFiled));
        $model->fill($fillableFiled);
        $result = $model->save();
        if($result) {
            return $model;
        }

        return false;
    }
}
