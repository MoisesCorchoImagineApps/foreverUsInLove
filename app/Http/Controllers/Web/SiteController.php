<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use Carbon\Carbon;
use Auth;
use App\Models\Height;
use App\Models\RelationshipStatus;
use App\Models\UserImages;
use App\Models\Education;
use App\Models\Pets;
use App\Models\LookingFor;
use App\Models\Language;
use App\Models\PoliticalLeaning;
use App\Models\Interests;
use App\Models\Horoscope;
use App\Models\Drink;
use App\Models\Pages;
use App\Models\Smoking;
use App\Models\DietaryLifestyle;
use DB;
use App\Models\Religion;
use App\Models\CovidVaccine;
use App\Models\Arts;
use App\Models\FirstDateIceBreaker;
use App\Models\Drugs;
use App\Models\UserQuestions;


class SiteController extends Controller
{
       // use AuthenticatesUsers;
       
     public function checkEmail(Request $request)
     {
         $count = User::where('email',$request->email)->count();
         
         if($count == '0')
         {
             return '1';
         }
         else
         {
             return '0';
         }
         
         //return $count
         
     }

    public function storeToken(Request $request)
    {
         User::where("phone",$request->phone)->update(array('device_key' => $request->token));
        return response()->json(['Token successfully stored.']);
    }
    
    
    public function index()
    {
        return view('web.index');
    }
    
    public function signin()
    {
        return view('web.signin');
    }
    
    public function logoutUser()
    {
       
        Auth::guard('user')->logout();
        return redirect('/');
    }
    
    public function sign_in(Request $request)
    {
        $check_phone = User::where('phone',$request->phone)->count();
        
       // return $check_phone;exit;
        
        if($check_phone == '1' )
        {
            //update otp
              $otp        = substr(number_format(time() * rand(),0,'',''),0,4);
            User::where("phone",$request->phone)->update(array('login_otp' => $otp));
            
            return $otp;
    
            
        }
        else if($check_phone == '0')
        {
            
                    $otp        = substr(number_format(time() * rand(),0,'',''),0,4);
            
                    $user        = new User();
                    $user->phone = $request->phone; 
                    $user->login_otp        = $otp;
                    $user->otp_expird_time  = date('Y-m-d H:i:s');
                    $user->save();
                    
                    return $otp;
        }
        
        
        else
        {
            //create new entry in DB.
            
              $otp        = substr(number_format(time() * rand(),0,'',''),0,4);
            
                    $user        = new User();
                    $user->phone = $request->phone; 
                    $user->login_otp        = $otp;
                    $user->otp_expird_time  = date('Y-m-d H:i:s');
                    $user->save();
            
              
              return $request->phone;
                    
                    //return 'o';
            //end
            
        }
        
        
    }
    
    public function otpResend(Request $request)
    {
            $otp        = substr(number_format(time() * rand(),0,'',''),0,4);
            User::where("phone",$request->phone)->update(array('login_otp' => $otp));
            return $otp;
    }
    
    public function verifySiginOtp(Request $request)
    {
         $check = User::where('phone',$request->phone)->where('login_otp',$request->otp)->count();
         
         
         if($check == '1')
         {
             
             $check_signup_status =  User::where('phone',$request->phone)->where('login_otp',$request->otp)->where('email_verified',1)->count();
             
             
             if($check_signup_status == '1')
             {
                 //login
                 //return '2';
                 
                    $user = User::where('phone',$request->phone)->first();
                  
                    if($user)
                    {
                  
                            /*\Auth::login($user);*/
                            
                                \Auth::guard('user')->login($user);                               
                               return json_encode(array("value" => '1', "login_userid" => $user->id));
    
                                //return '1';

                           // return redirect('/discover');
                
                    }
                    else
                    {
                        return '2';
                    }
                                 
             }
             else
             {
                 //move to signup details form.
                return '3';
               // return redirect('/create_profile');
             }
             
             
         }
         else
         {
             return '4'; //invalid otp.
         }
         
         
         
         
         
    }
    
    public function createProfileView(Request $request)
    {
    
        $lookingfor = LookingFor::get();
        $height = Height::get();
        $language = Language::get();
        $relationship = RelationshipStatus::get();
        $education = Education::get();
       
        
        return view('web.create_profile')->with(['education'=> $education,'height'=>$height,'lookingfor'=>$lookingfor,'relationship'=>$relationship,'language'=>$language]);;
    }
    
    
    public function createProfile(Request $request)
    {                
        $carbonBirthdate = Carbon::createFromFormat('d-m-Y', $request->dob);        
        $currentDate = Carbon::now();
        $age = $carbonBirthdate->diffInYears($currentDate);

        $uid=User::where("phone",$request->mobile)->pluck('id')->toArray();
        //print_r($uid);exit;
        
        if(isset($params['profile_video']) && !empty($params['profile_video'])) {
            $image  = 'user_profile_image/'.time().rand().'.'.$params['profile_video']->extension();
            $params['profile_video']->move(public_path('user_profile_image'), $image);
            //$params['profile_video'] = $image;
               
           }
           
            
        User::where("phone",$request->mobile)->update(array('first_name' => $request->first_name,
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
        
         ));
         
         
         
      //  UserQuestions::where(['user_id'=>$uid[0],'question_type'=>'language','question_type'=>'looking_for','question_type' => 'education','question_type'=>'relationship_status'])->delete();
        UserQuestions::where('user_id',$uid[0])->where('question_type','language')->delete();
        UserQuestions::where('user_id',$uid[0])->where('question_type','looking_for')->delete();
        UserQuestions::where('user_id',$uid[0])->where('question_type','relationship_status')->delete();
        UserQuestions::where('user_id',$uid[0])->where('question_type','education')->delete();
       

         if($request->languages != NULL)
         {
                   foreach ($request->languages as $key => $value) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid[0],
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
                            'user_id' => $uid[0],
                            'question_id' => $looking,
                            'question_type' => 'looking_for', 
                        ]);
                        
                       
                   }
         }
         
         if($request->education != NULL )
         {
             UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->education,
                            'question_type' => 'education', 
                        ]);
         }
         
          if($request->relationship_status != NULL)
         {
             UserQuestions::create([
                            'user_id' => $uid[0],
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
                    'user_id' => $uid[0],
                    'url'     => $imagePath,
                ];
                $addUpdateImages = UserImages::addUpdateImages($imageParams);

            }
        }
       
       
       
       
       //email sending
       $email = User::where("phone",$request->mobile)->pluck('email')->toArray();
       
     //  print_r($email);exit;
       
         if(!empty($email)) {

	     //  if((isset($user->email_verified) && $user->email_verified  == 0) || $user->email != $email) {
	         $otp  = substr(number_format(time() * rand(),0,'',''),0,4);
	            
	            User::where("phone",$request->mobile)->update(array('email_verified_otp' => $otp));
	           

	            $data = [

	                'otp'     => $otp,

	                'subject' => 'Email OTP Verification - ForEverUs In Love',

	               ];
	            
	            User::where("phone",$request->phone)->update(array('email_verified_otp' => $otp));

	            $this->sendMail('email_verify', $data, $email[0], '');

	            $msg = 'We have send you verify mail in your email account, Please check and verify!';

	       // }

	    }
       //end
       
       
       return redirect('/email_verification');
                 
    }
    
    public function viewEmailVerification()
    {
        return view('web.email_verification');
    }
    
    
    public function emailVerify(Request $request)
    {
         // $user = User::where('phone',$request->phone)->first();
          $count = User::where("phone",$request->phone)->where('email_verified_otp',$request->otp)->count();
          
          if($count == '0')
          {
              return '0';
          }
          else
          {
              //\Auth::guard('user')->login($user);
              User::where("phone",$request->phone)->update(array('email_verified_otp' => '','email_verified'=>'1'));
              return '1';
              
              
          }
	       
    }

    public function changeEmail(Request $request)
    {
       // return $request->all();exit;

            //check uniqueueness of mailid
            $found = User::where('email',$request->email)->count();

            if($found == '1')
            {
                return '0';
            }
            else
            {

                User::where("phone",$request->phone)->update(array('email' => $request->email));

                 //send mail
                 
                $otp  = substr(number_format(time() * rand(),0,'',''),0,4);
	            
	            User::where("phone",$request->phone)->update(array('email_verified_otp' => $otp));
	           

	            $data = [

	                'otp'     => $otp,

	                'subject' => 'Email OTP Verification - For Ever Us In Love',

	               ];
	            
	            User::where("phone",$request->phone)->update(array('email_verified_otp' => $otp));

	            $this->sendMail('email_verify', $data, $request->email, '');

	            $msg = 'We have send you verify mail in your email account, Please check and verify!';
                //end

                return '1';

            }
    }

    public function resendOtp(Request $request)
    {
             //send mail
                 
             $otp  = substr(number_format(time() * rand(),0,'',''),0,4);
	            
             User::where("email",$request->mail)->update(array('email_verified_otp' => $otp));
            

             $data = [

                 'otp'     => $otp,

                 'subject' => 'Email OTP Verification - For Ever Us In Love',

                ];
             
           //  User::where("phone",$request->phone)->update(array('email_verified_otp' => $otp));

             $this->sendMail('email_verify', $data, $request->mail, '');

             $msg = 'We have send you verify mail in your email account, Please check and verify!';
             //end
    }
    
    public function viewRegistrationComplete()
    {
        return view('web.registration_complete');
    }
    
    public function autheticate(Request $request)
    {
        $user = User::where('phone',$request->phone)->first();
        \Auth::guard('user')->login($user);
        return '1';
    }
    
    public function viewAdditionalQuestions()
    {
        $pets = Pets::get();
        $dietary_lifestyle = DietaryLifestyle::get();
        $smoking = Smoking::get();
        $interests = Interests::get();
        $horoscopes = Horoscope::get();
        $drinks = Drink::get();
        $vaccinate = CovidVaccine::get();
        $political_views = PoliticalLeaning::get();
        $religion = Religion::get();
        $ice_breaker = FirstDateIceBreaker::get();
        $arts = Arts::get();
        $substances = Drugs::get();
        
        return view('web.additional_questions')->with(['substances'=>$substances,'religion'=>$religion,'vaccinate'=>$vaccinate,'pets'=>$pets,'interests'=>$interests,'arts'=>$arts,'horoscopes' => $horoscopes,'ice_breaker'=>$ice_breaker,'political_views'=>$political_views,'smoking'=>$smoking,'drinks' => $drinks,'dietary_lifestyle' => $dietary_lifestyle]);
    }
    
    public function saveAdditionalQuestions(Request $request)
    {
        /*print_r($request->all());exit;*/
        $uid=User::where("phone",$request->mobile)->pluck('id')->toArray();
        
        
         if($request->smoking != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->smoking,
                            'question_type' => 'smoking', 
                        ]);
         }

         if($request->drug != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->drug,
                            'question_type' => 'drugs', 
                        ]);
         }
         
         if($request->political_leaning != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->political_leaning,
                            'question_type' => 'political_leaning', 
                        ]);
         }
         
         if($request->first_date_ice_breaker != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->first_date_ice_breaker,
                            'question_type' => 'first_date_ice_breaker', 
                        ]);
         }
         
          if($request->covid_vaccine != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->covid_vaccine,
                            'question_type' => 'covid_vaccine', 
                        ]);
         }
         
         if($request->religion != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->religion,
                            'question_type' => 'religion', 
                        ]);
         }
        
        
        if($request->drink != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->drink,
                            'question_type' => 'drink', 
                        ]);
         }
         
         if($request->horoscope != NULL)
         {
                        UserQuestions::create([
                            'user_id' => $uid[0],
                            'question_id' => $request->horoscope,
                            'question_type' => 'horoscope', 
                        ]);
         }
        
         if($request->arts != NULL)
         {
                   foreach ($request->arts as $key => $art) 
                   {
                        UserQuestions::create([
                            'user_id' => $uid[0],
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
                            'user_id' => $uid[0],
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
                            'user_id' => $uid[0],
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
                            'user_id' => $uid[0],
                            'question_id' => $pet,
                            'question_type' => 'pets', 
                        ]);
                        
                   }
         }
         
         
          $user = User::where('phone',$request->mobile)->first();
          \Auth::guard('user')->login($user);
          return redirect('/discover');
         
    }
        
    
    public function privacy_policy()
    {
        $privacy_policy = Pages::where('page_type','privacy_policy')->first();
        
        return view('web.privacy_policy')->with(['privacy_policy' => $privacy_policy]);
    }

    public function terms_and_conditions()
    {       
        $terms_and_conditions = Pages::where('page_type','terms_and_conditions')->first();
        
        return view('web.terms_and_conditions')->with(['terms_and_conditions' => $terms_and_conditions]);
    }
    
    
    
   
}
