<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class UsersLikes extends Model
{
    protected $table = 'user_likes';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'like_from',
        'like_to',
        'match_id',
        'match_status',
        'like_status',
        'plan_status',
        'notification',
        'read_status',
        'matched_at',
        'read_status',
        'match_as',
    ];

    public function user()
    {
        return $this->hasOne('App\Models\User','id','like_from')->with('userImages')->where('status', 'Active');
    }

    public function likingUser()
    {
        return $this->hasOne('App\Models\User', 'id', 'like_to')->with('userImages')->where('status', 'Active');
    }

    protected function addUpdateUsersLikes($params = [])
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
