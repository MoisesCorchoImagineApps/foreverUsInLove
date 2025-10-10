<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;
use Illuminate\Database\Eloquent\Model;

class Height extends Model
{
    protected $table = 'height';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'title',
    ];

    public function getTitleAttribute($value)
    {
       
      $parts = explode('.', $value);
        if (count($parts) >= 2) {
            list($feet, $inch) = $parts;
            $result = $feet . "'" . $inch . "\"";
        } else {
            
            $result = $value . "'0\"";
        }
        
        return (str_replace("\\\"", "\"", $result));
    }
    
    public function getHeightAttribute($value)
    {
        return number_format($value, 1);
    }


    protected function addUpdateHeight($params = [])
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
        $result        = $model->save();
        if($result) {
            return $model;
        }

        return false;
    }
}
