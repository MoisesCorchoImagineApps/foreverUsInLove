<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;
use App\Models\UsersPrivateMessages;
use App\Models\UsersMessages;
use Auth;

class UserPrivateChat extends Model
{
    protected $table = 'user_private_chat';
    protected $primaryKey = 'user_private_chat_id';
    protected $appends = array('match_id','last_message','message_count','unread_message_count','unread_message_sender_count', 'sender_id');

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "request_from",
        'request_to',
        'request_status',
        'invite_msg',
    ];

    public function getMatchIdAttribute()
    {
        if ($this->userLikesFrom) {
            $result = $this->userLikesFrom->where(['like_from'=>$this->request_from,'like_to'=>$this->request_to,'match_status'=>'match'])->first();
            if (!empty($result)) {
                return $result->match_id;
            }
        }
        
        return '';
    }

    public function getSenderIdAttribute(){
        $getModel = UsersPrivateMessages::where('match_id',$this->match_id)->orderBy('id','DESC')->first();
        if (!empty($getModel)) {
            return $getModel->sender_id;
        }else{
            return null;
        }
    }
    
    public function getUnreadMessageCountAttribute(){
        return UsersPrivateMessages::where('match_id',$this->match_id)->where('receiver_id',Auth::id())->where('read_status','Unread')->count();
    }
    
    public function getUnreadMessageSenderCountAttribute(){
        return UsersPrivateMessages::where('match_id',$this->match_id)->where('sender_id',Auth::id())->where('read_status','Unread')->count();
    }

    public function getLastMessageAttribute(){

        $getModel = UsersPrivateMessages::where('match_id',$this->match_id)->orderBy('id','DESC')->first();
        //$getModel = UsersMessages::where('match_id',$this->match_id)->orderBy('id','DESC')->first();
        
        if (!empty($getModel)) {
            return $getModel->message;
        }else{
            return null;
        }
    }

    public function getMessageCountAttribute(){
        return UsersPrivateMessages::where('match_id',$this->match_id)->count();
    }

    public function getRequestFromAttribute($value)
    {
        return (int) $value;
    }

    public function getRequestToAttribute($value)
    {
        return (int) $value;
    }

    public function getRequestToUser()
    {
        return $this->hasOne('App\Models\User', 'id','request_to');
    }

    public function getRequestFromUser()
    {
        return $this->hasOne('App\Models\User', 'id','request_from');
    }

    public function userLikesFrom()
    {
        return $this->hasOne('App\Models\UsersLikes', 'like_from','request_from');
    }

    public function userLikesTo()
    {
        return $this->hasOne('App\Models\UsersLikes', 'like_to','request_to');
    }
    
    /*public function userImages()
    {
        return $this->hasMany('App\Models\UserImages', 'user_id','user_id');
    }*/

    /*public function anonymousProfile()
    {
        return $this->hasOne('App\Models\AnonymousProfile', 'user_id','request_to');
    }*/


    protected function addUpdateUserPrivateChat($params = [])
    {
        if(empty($params)) {
            return false;   
        }

        $id = Arr::get($params, 'id', 0);
        
        if($id > 0) {
            $model = self::find($id);
            if(!$model) {
                $model = new self();    
            }
        } else {
            $model = new self();
        }

        $params        = Arr::except($params, ['id']);
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
