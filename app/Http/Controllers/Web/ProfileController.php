<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\Height;
use App\Models\RelationshipStatus;
use App\Models\UserImages;
use App\Models\LookingFor;
use App\Models\Language;
use App\Models\User;
use App\Models\Education;
use App\Models\Drugs;
use App\Models\Smoking;
use App\Models\Drink;
use App\Models\DietaryLifestyle;
use App\Models\Interests;
use App\Models\Pets;
use App\Models\Horoscope;
use App\Models\PoliticalLeaning;
use App\Models\Religion;
use App\Models\CovidVaccine;
use App\Models\Arts;
use App\Models\FirstDateIceBreaker;
use Auth;
use App\Models\UserQuestions;
use DB;
use Carbon\Carbon;

class ProfileController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function changeEmail(Request $request)
    {
        //check uniqueueness of mailid
        $found = User::where('email',$request->email)->count();

        if($found == '1')
        {
            return '0';
        }
        else
        {

           // User::where("id",Auth::user()->id)->update(array('email' => $request->email));

             //send mail
             
            $otp  = substr(number_format(time() * rand(),0,'',''),0,4);
            
           // User::where("id",Auth::user()->id)->update(array('email_verified_otp' => $otp));
           

            $data = [

                'otp'     => $otp,

                'subject' => 'Email OTP Verification - For Ever Us In Love',

               ];
            
            User::where("id",Auth::user()->id)->update(array('email_verified_otp' => $otp));

            $this->sendMail('email_verify', $data, $request->email, '');

           // $msg = 'We have send you verify mail in your email account, Please check and verify!';
            //end

            return '1';

        }
    }

    public function changeOtp(Request $request)
    {
       $count =  User::where("email_verified_otp",$request->otp)->where("id",Auth::user()->id)->count();

       if($count == '1')
       {
         // new_mailid
          User::where("id",Auth::user()->id)->update(array('email' => $request->new_mailid));
          return '1';
       }
       else
       {
          return '0';
       }
    }
    
    public function viewPage()
    {
        $lookingfor = LookingFor::get();
        $height = Height::get();
        $language = Language::get();
        $education = Education::get();
        $relationship = RelationshipStatus::get();
        $profile_pic = UserImages::where('user_id',Auth::user()->id)->first();
        $user_height = Height::join('users','users.height','=','height.id')->where('users.id',Auth::user()->id)->pluck('height.id')->toArray();
        $otherImages = UserImages::where('user_id',Auth::user()->id)->skip(1)->take(5)->get();
        $smoking = Smoking::get();
        $dietary_lifestyle = DietaryLifestyle::get();
        $substances = Drugs::get();
        $drinks = Drink::get();
        $interests = Interests::get();
        $pets = Pets::get();
        $political_views = PoliticalLeaning::get();
        $horoscopes = Horoscope::get();

        $total_images = UserImages::where('user_id',Auth::user()->id)->count();
        
        $vaccinate = CovidVaccine::get();
        $religion = Religion::get();
        $ice_breaker = FirstDateIceBreaker::get();
        $arts = Arts::get();

        
        $user_looking_for = UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','looking_for')->pluck("question_id")->toArray();
        $user_lang = UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','language')->pluck("question_id")->toArray();
        $user_edu = UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','education')->pluck("question_id")->toArray();
        $user_smoke = UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','smoking')->pluck("question_id")->toArray();
        
        $user_drink = UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','drink')->pluck("question_id")->toArray();
        $user_diet= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','dietary_lifestyle')->pluck("question_id")->toArray();
        $user_int= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','interests')->pluck("question_id")->toArray();
        $user_pet= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','pets')->pluck("question_id")->toArray();
        $user_horo= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','horoscope')->pluck("question_id")->toArray();
        $user_ice= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','first_date_ice_breaker')->pluck("question_id")->toArray();
        $user_art= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','arts')->pluck("question_id")->toArray();
        $user_vacci= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','covid_vaccine')->pluck("question_id")->toArray();
        $user_reli= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','religion')->pluck("question_id")->toArray();
        $user_poli= UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','political_leaning')->pluck("question_id")->toArray();
        $user_drug=UserQuestions::where("user_id",\Auth::user()->id)->where('question_type','drugs')->pluck("question_id")->toArray();
        
        
        $data_user = User::with(['userEducations',
            'userRelationshipStatus'
            ])->where('id', \Auth::user()->id)->first();
          
       
        return view('web.edit_profile')->with(['user_drug'=>$user_drug,'user_poli'=>$user_poli,'user_reli' => $user_reli,'user_vacci'=> $user_vacci,'user_art'=>$user_art,'user_ice'=>$user_ice,
        'user_horo' => $user_horo,'user_pet' => $user_pet,'user_int'=>$user_int,'user_diet'=>$user_diet,'user_drink'=>$user_drink,
        'user_smoke'=>$user_smoke,'ice_breaker'=>$ice_breaker,'arts'=>$arts,'religion' => $religion,'political_views' => $political_views,
        'vaccinate'=>$vaccinate,'interests'=>$interests,'dietary_lifestyle' => $dietary_lifestyle,'pets'=>$pets,'drinks'=>$drinks,
        'smoking'=>$smoking,'user_edu' => $user_edu,'education'=>$education,'user_lang'=>$user_lang,'user_looking_for'=>$user_looking_for,
        'data_user'=>$data_user,'user_height' => $user_height,'profile_pic'=>$profile_pic,'height'=>$height,'lookingfor'=>$lookingfor,
        'otherImages'=>$otherImages,'relationship'=>$relationship,'language'=>$language,'horoscopes' => $horoscopes,'substances'=>$substances,'total_images'=>$total_images]);
    }
    
    public function updateProfileData(Request $request)
    {
      // print_r($request->all());exit;   
       
        $uid= \Auth::user()->id;
        //print_r($uid);exit;
        
        if(isset($params['profile_video']) && !empty($params['profile_video'])) {
            $image  = 'user_profile_image/'.time().rand().'.'.$params['profile_video']->extension();
            $params['profile_video']->move(public_path('user_profile_image'), $image);
            //$params['profile_video'] = $image;
               
           }

           $carbonBirthdate = Carbon::createFromFormat('d-m-Y', $request->dob);        
           $currentDate = Carbon::now();
           $age = $carbonBirthdate->diffInYears($currentDate);
           
            
        User::where("phone", \Auth::user()->phone)->update(array('first_name' => $request->first_name,
        'last_name'=>$request->last_name,
        'email' =>$request->email,
        'gender'=>$request->gender,
        'height'=>$request->height,
        'dob'=>$request->dob,
        'user_intrested_in'=> $request->user_intrested_in,
        'relationship_status'=>$request->relationship_status,
        'address'=>$request->address,
        'about'=>$request->about,
        'job_title' => $request->job_title,
        'latitude' => $request->latitude,
        'longitude' => $request->longitude,
        'age' => $age,
        //'profile_video' => $image,
        
         ));
         
         
         
      //  UserQuestions::where(['user_id'=>$uid[0],'question_type'=>'language','question_type'=>'looking_for','question_type' => 'education','question_type'=>'relationship_status'])->delete();
       /* UserQuestions::where('user_id',$uid)->where('question_type','language')->delete();
        UserQuestions::where('user_id',$uid)->where('question_type','looking_for')->delete();
        UserQuestions::where('user_id',$uid)->where('question_type','relationship_status')->delete();
        UserQuestions::where('user_id',$uid)->where('question_type','education')->delete();*/
        
        
       UserQuestions::where('user_id',$uid)->delete();

         if($request->languages != NULL)
         {
                   foreach ($request->languages as $key => $value) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $value,
                            'question_type' => 'language', 
                        ]);
                        
                        /*UserQuestions::updateOrCreate(['user_id' => $uid[0]],
                        ['question_type' => 'language','question_id'=>$value]);*/
                   }
         }
         
           if($request->looking_for != NULL)
         {
                   foreach ($request->looking_for as $key => $looking) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $looking,
                            'question_type' => 'looking_for', 
                        ]);
                        
                       
                   }
         }
         
         if($request->education != NULL )
         {
             UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->education,
                            'question_type' => 'education', 
                        ]);
         }
         
          if($request->relationship_status != NULL)
         {
             UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->relationship_status,
                            'question_type' => 'relationship_status', 
                        ]);
         }
         
         
          
           
        
        
        
         if(!empty($request->profile_video)) {
            
            //foreach ($request->image as $key => $image) {

                $imagePath  = 'user_profile_image/'.time().rand().'.'.$request->profile_video->extension();
                
                $request->profile_video->move(public_path('user_profile_image'), $imagePath);

               /* $imageParams     = [
                    'user_id' => $user->id,
                    'url'     => $imagePath,
                ];*/
                
                        User::where("phone",$request->mobile)->update(array(
                        'profile_video' => $imagePath,
                        
                         ));

            //}
        }
           
           
           
         
         if(!empty($request->image)) {
            
            foreach ($request->image as $key => $image) {

                $imagePath  = 'user_profile_image/'.time().rand().'.'.$image->extension();
                
                $image->move(public_path('user_profile_image'), $imagePath);

                $imageParams     = [
                    'user_id' => $uid,
                    'url'     => $imagePath,
                ];
                $addUpdateImages = UserImages::addUpdateImages($imageParams);

            }
        }
        
        //additional questions
        
         if($request->smoking != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->smoking,
                            'question_type' => 'smoking', 
                        ]);
         }

         if($request->drug != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->drug,
                            'question_type' => 'drugs', 
                        ]);
         }
         
         if($request->political_leaning != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->political_leaning,
                            'question_type' => 'political_leaning', 
                        ]);
         }
         
         if($request->first_date_ice_breaker != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->first_date_ice_breaker,
                            'question_type' => 'first_date_ice_breaker', 
                        ]);
         }
         
          if($request->covid_vaccine != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->covid_vaccine,
                            'question_type' => 'covid_vaccine', 
                        ]);
         }
         
         if($request->religion != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->religion,
                            'question_type' => 'religion', 
                        ]);
         }
        
        
        if($request->drink != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->drink,
                            'question_type' => 'drink', 
                        ]);
         }
         
         if($request->horoscope != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $request->horoscope,
                            'question_type' => 'horoscope', 
                        ]);
         }
        
         if($request->arts != NULL)
         {
                   foreach ($request->arts as $key => $art) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $art,
                            'question_type' => 'arts', 
                        ]);
                        
                   }
         }
        
         if($request->dietary_lifestyle != NULL)
         {
                   foreach ($request->dietary_lifestyle as $key => $diet) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $diet,
                            'question_type' => 'dietary_lifestyle', 
                        ]);
                        
                   }
         }
         
          if($request->interests != NULL)
         {
                   foreach ($request->interests as $key => $int) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $int,
                            'question_type' => 'interests', 
                        ]);
                        
                   }
         }
         
          if($request->pets != NULL)
         {
                   foreach ($request->pets as $key => $pet) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid,
                            'question_id' => $pet,
                            'question_type' => 'pets', 
                        ]);
                        
                   }
         }
         
         
        
        //end
       
       
       
       
       //email sending
      // $email = User::where("phone",$request->mobile)->pluck('email')->toArray();
       
       
         /*if(!empty($email)) {

	    
	         $otp  = substr(number_format(time() * rand(),0,'',''),0,4);
	            
	            User::where("phone",$request->mobile)->update(array('email_verified_otp' => $otp));
	           

	            $data = [

	                'otp'     => $otp,

	                'subject' => 'Email OTP Verification - For Ever Us In Love',

	               ];
	            
	            User::where("phone",$request->phone)->update(array('email_verified_otp' => $otp));

	            $this->sendMail('email_verify', $data, $email[0], '');

	            $msg = 'We have send you verify mail in your email account, Please check and verify!';
	    }*/
       //end
       
       return redirect('/edit_profile');
       
    }
    
    public function deleteUserUploads(Request $request)
    {
        DB::table('user_images')
          ->where('id',$request->id)
          ->delete();
    }
    
    public function deleteUserVideo(Request $request)
    {
        DB::table('users')
            ->where('id',$request->id)
                 ->update(array('profile_video' => NULL));
    }
    
   
}