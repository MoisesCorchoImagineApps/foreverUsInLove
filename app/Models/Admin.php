<?php

//namespace App;
namespace App\Models;


/*use Illuminate\Notifications\Notifiable;
use Illuminate\Foundation\Auth\User as Authenticatable;*/

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class Admin extends Authenticatable
{
    //use Notifiable;
        use HasFactory, Notifiable, HasApiTokens;


    protected $table = 'admin';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'email', 'password','first_name','last_name'
    ];

    /**
     * The attributes that should be hidden for arrays.
     *
     * @var array
     */
    protected $hidden = [
        'password', 'remember_token',
    ];


    /*public function getAuthPassword()
    {
      return $this->passcode;
    }*/
}
