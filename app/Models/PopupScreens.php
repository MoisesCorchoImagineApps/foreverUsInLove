<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class PopupScreens extends Model
{
    protected $table = 'popup_screens';
   

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
     protected $fillable = [
        'screen_id',
        'popup_id',
    ];

  

   
}