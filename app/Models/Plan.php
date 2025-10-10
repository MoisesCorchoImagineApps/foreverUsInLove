<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Plan extends Model
{
    const ALL_FILTERS = ['min_distance','max_distance','min_height','max_height','min_age','max_age','pageSize','page','is_apply_filter','user_looking_for','latitude','longitude'];
    const FreePlanType      = 'free';
    const ProPlanType      = 'pro';
    protected $table        = 'plan';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'description',
        'search_filters',
        'like_per_day',
        'super_like_par_day',
        'group_video_call_and_chat',
        'video_call_duration',
        'my_likes',
        'who_views_me',
        'private_chat_request',
        'price',
        'currency_code',
        'month',
        'plan_duration',
        'plan_type',
        'apple_plan_id',
        'google_plan_id'
    ];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    
    protected $appends = ['new_price'];

    public function getLikePerDayAttribute($value){
        return abs($value);
    }
    
    public function getNewPriceAttribute()
    {
        $formatted_price = number_format((float) $this->price, 2, '.', '');
        return $formatted_price;
    }

    protected function addUpdatePlan($params = [])
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
