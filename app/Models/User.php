<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Support\Arr;
use Laravel\Passport\HasApiTokens;


class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'first_name',
        'last_name',
        'email',
        'gender',
        'status',
        'phone',
        'dob',
        'about',
        'latitude',
        'job_title',
        'company',
        'longitude',
        'latitude',
        'age',
        'address',
        'email_verified',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
        'profile_video',
        'password',
        'coins',
        'height',
        'covid_vaccine',
        'drink',
        'drugs',
        'first_date_ice_breaker',
        'horoscope',
        'life_style',
        'political_leaning',
        'relationship_status',
        'religion',
        'smoking',
        'user_intrested_in',
        'hobbies',
        'available_video_call_duration',
        'is_plan_expired_notified',
        'fcm_token'
    ];
    protected $appends = ['full_name','total_video_call_duration','is_once_purchased'];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
        'smoking',
        'religion',
        'relationship_status',
        'political_leaning',
        'life_style',
        'horoscope',
        'first_date_ice_breaker',
        'drugs',
        'drink',
        'covid_vaccine',
        'image1',
        'image2',
        'image3',
        'image4',
        'image5',
        'image6',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function getTotalVideoCallDurationAttribute($value = '')
    {
        if ($this->orderActive) {
            $duration = !empty($this->orderActive->plan) ? $this->orderActive->plan->video_call_duration : 0;
        } else {
            $duration = 0;
        }

        return (string)$duration; // Convert the result to a string
    }

    public function getIsOncePurchasedAttribute()
    {
        
        $allOrder = $this->Orders;
        $isTrialUsed = $allOrder->where('subscription_id',6);
        if (!empty($isTrialUsed->toArray())) {
            return true;
        }
        return false;
    }

    public function getFullNameAttribute()
    {
        return $this->first_name.' '.$this->last_name;
    }

    public function getImage1Attribute($value)
    {
        return !empty($value) ? url('/')."/".$value : '';
    }

    public function getImage2Attribute($value)
    {
        return !empty($value) ? url('/')."/".$value : '';
    }

    public function getImage3Attribute($value)
    {
        return !empty($value) ? url('/')."/".$value : '';
    }

    public function getImage4Attribute($value)
    {
       return !empty($value) ? url('/')."/".$value : '';
    }

    public function getImage5Attribute($value)
    {
        return !empty($value) ? url('/')."/".$value : '';
    }

    public function getImage6Attribute($value)
    {
       return !empty($value) ? url('/')."/".$value : '';
    }

    public function getDistanceAttribute($value='')
    {
        if (!empty($this->userSettings)) {
            if (($this->userSettings->distance_unit == 'Mile' || $this->userSettings->distance_unit == 0) && $value > 0) {
                return round($value/1.609, 2);
            }
        }
        return $value;
    }

    public function getProfileVideoAttribute($value)
    {
       return !empty($value) ? url('/')."/".$value : '';
    }

    public function getPassionAttribute($value)
    {
        return !empty($value) ? unserialize($value) : [];
    }

    public function userKids()
    {
        return $this->hasMany('App\Models\UserKids', 'user_id','id');
    }

    public function userImages()
    {
        return $this->hasMany('App\Models\UserImages', 'user_id','id');
    }

    public function userSettings()
    {
        return $this->hasOne('App\Models\UserSettings', 'user_id','id');
    }

    public function Orders()
    {
        return $this->hasMany('App\Models\Order', 'user_id','id');
    }

    public function orderActive()
    {
        return $this->hasOne('App\Models\Order', 'user_id','id')->where('status', 'Active');
    }

    public function userQuestions()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id');
    }

    public function userProductsOrder()
    {
        return $this->hasOne('App\Models\ProductsOrder', 'user_id','id');//->where('status','Active');
    }

    public function userEducations()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','education.title'])
        ->leftJoin('education','education.id','user_questions.question_id')
        ->where('question_type','education');
    }

    public function userLookingFor()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','looking_for.title'])
        ->leftJoin('looking_for','looking_for.id','user_questions.question_id')
        ->where('question_type','looking_for');
    }

    public function userDietaryLifestyle()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','dietary_lifestyle.title'])
        ->leftJoin('dietary_lifestyle','dietary_lifestyle.id','user_questions.question_id')
        ->where('question_type','dietary_lifestyle');
    }

    public function userPets()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','pets.title'])
        ->leftJoin('pets','pets.id','user_questions.question_id')
        ->where('question_type','pets');
    }

    public function userArts()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','arts.title'])
        ->leftJoin('arts','arts.id','user_questions.question_id')
        ->where('question_type','arts');
    }

    public function userLanguage()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','language.title'])
        ->leftJoin('language','language.id','user_questions.question_id')
        ->where('question_type','language');
    }

    public function userInterests()
    {
        return $this->hasMany('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','interests.title'])
        ->leftJoin('interests','interests.id','user_questions.question_id')
        ->where('question_type','interests');
    }

    public function userDrink()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','drink.title'])
        ->leftJoin('drink','drink.id','user_questions.question_id')
        ->where('question_type','drink');
    }

    public function userDrugs()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','drugs.title'])
        ->leftJoin('drugs','drugs.id','user_questions.question_id')
        ->where('question_type','drugs');
    }

    public function userHoroscope()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','horoscope.title'])
        ->leftJoin('horoscope','horoscope.id','user_questions.question_id')
        ->where('question_type','horoscope');
    }

    public function userReligion()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','religion.title'])
        ->leftJoin('religion','religion.id','user_questions.question_id')
        ->where('question_type','religion');
    }    
    
    public function userPoliticalLeaning()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','political_leaning.title'])
        ->leftJoin('political_leaning','political_leaning.id','user_questions.question_id')
        ->where('question_type','political_leaning');
    }

    public function userRelationshipStatus()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','relationship_status.title'])
        ->leftJoin('relationship_status','relationship_status.id','user_questions.question_id')
        ->where('question_type','relationship_status');
    }

    public function userLifeStyle()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','life_style.title'])
        ->leftJoin('life_style','life_style.id','user_questions.question_id')
        ->where('question_type','life_style');
    }
    
    public function userFirstDateIceBreaker()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','first_date_ice_breaker.title'])
        ->leftJoin('first_date_ice_breaker','first_date_ice_breaker.id','user_questions.question_id')
        ->where('question_type','first_date_ice_breaker');
    }

    public function userCovidVaccine()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','covid_vaccine.title'])
        ->leftJoin('covid_vaccine','covid_vaccine.id','user_questions.question_id')
        ->where('question_type','covid_vaccine');
    }

    public function userSmoking()
    {
        return $this->hasOne('App\Models\UserQuestions', 'user_id','id')
        ->select(['user_id','question_id','question_type','smoking.title'])
        ->leftJoin('smoking','smoking.id','user_questions.question_id')
        ->where('question_type','smoking');
    }

    protected function addUpdateUser($params = [])
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
