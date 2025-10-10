<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ViewedPopup extends Model
{
    protected $table = 'viewed_popups';

    protected $fillable = [
        'user_id',
        'popup_screen_id',
    ];
}
