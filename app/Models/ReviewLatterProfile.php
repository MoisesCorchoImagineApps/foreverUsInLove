<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class ReviewLatterProfile extends Model
{
    protected $table = 'review_latter_profile';
    protected $primaryKey = 'review_latter_profile_id';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        "review_by",
        'review_to',
    ];

    public function getReviewByAttribute($value)
    {
        return (int) $value;
    }

    public function getReviewToAttribute($value)
    {
        return (int) $value;
    }

    public function getReviewToUser()
    {
        return $this->hasOne('App\Models\User', 'id','review_to');
    }

    public function userImages()
    {
        return $this->hasMany('App\Models\UserImages', 'user_id','review_to');
    }

    public function getRequestFromUser()
    {
        return $this->hasOne('App\Models\User', 'id','review_to');
    }



    protected function addUpdateReviewLatter($params = [])
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
