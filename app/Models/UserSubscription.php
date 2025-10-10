<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserSubscription extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'subscription_id',
        'start_date',
        'expire_date',
        'title',
        'description',
        'search_filters',
        'like_per_day',
        'video_call',
        'who_like_me',
        'who_view_me',
        'undo_profile',
        'read_receipt',
        'travel_mode',
        'profile_badge',
        'price',
        'currency_code',
        'month',
        'plan_duration',
        'plan_type',
        'google_plan_id',
        'apple_plan_id',
        'order_id'
    ];

    public function user()
    {
        return $this->belongsTo(User::class,'user_id','id');
    }

    public function subscriptionOrder(){
        return $this->belongsTo(Subscription::class,'subscription_id');
    }
}
