<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use App\Models\User;
use App\Models\Plan;
use Illuminate\Support\Arr;
use App\Models\RelationshipStatus;
use App\Models\Notifcation;
use App\Models\UsersReport;
use App\Models\Height;
use App\Models\UsersMessages;
use App\Models\UsersLikes;
use Illuminate\Support\Facades\Response;
use App\Models\ReviewLatterProfile;
use App\Models\ProductsOrder;
use DB;
use Carbon\Carbon;
use App\Models\LookingFor;
use Log;
use App\Models\Settings;
use App\Models\UserView;
use App\Models\UserImages;
use App\Models\UserFilter;
use App\Models\UserDefaultSettings;
use App\Models\Drugs;
use App\Models\Religion;
use App\Models\CovidVaccine;
use App\Models\Arts;
use App\Models\FirstDateIceBreaker;
use App\Models\Education;
use App\Models\Pets;
use App\Models\Language;
use App\Models\PoliticalLeaning;
use App\Models\Interests;
use App\Models\Horoscope;
use App\Models\Drink;
use App\Models\Smoking;
use App\Models\DietaryLifestyle;
use Validator;
use App\Models\Order;

class DiscoverController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }
    
       public function commaStringToArray($string){
        if (!empty($string)) {
            return explode(",",$string);
        }
        return [];
    }
    
    public function view_profile(Request $request)
    {
        $messages = array(
            'viewer_id.required'     => 'Viewer id field is required.',
        );

        $validator = Validator::make($request->all(),[
            'viewer_id'      => 'required',
        ],$messages);

        if ($validator->fails()) {
            return $this->errorResponse([], $validator->errors());
        }

        $user              = $request->user();
        $params            = $request->all();
        $params['user_id'] = $user->id; 
        $checkAlreadyView  = UserView::where('user_id', $user->id)->where('viewer_id', $params['viewer_id'])->first();
        if($checkAlreadyView) {
            $params['id']  = isset($checkAlreadyView->id) ? $checkAlreadyView->id : '';
        }
        $result            = UserView::addUpdateUserView($params);

        // send notification
        $viewerUser = User::where('id',$request->viewer_id)->first();

        $pushTittle = $user->first_name .' '.$user->last_name.' has viewed your profile';
        $message = $user->first_name .' '.$user->last_name.' has viewed your profile';
        
        $responsedata = [                
            'type'              => 'user view',
        ];

        $pushData = [
            'message' => $responsedata
        ];
        
        
        $devicekey = User::where('id',$viewerUser->id)->pluck('device_key')->all();

           
            //WEB PUSH NOTIFICATION
                       $web_noti_data = [
                    "registration_ids" => $devicekey,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => $message, 
                        "click_action" => "https://app-backend.foreverusinlove.com/discover",
                        "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
                    ]
                ];
        
                  $this->sendWebNotification($web_noti_data);
            //END    

        if ($viewerUser->fcm_token) {
            $noticationStatus     = $this->sendPushNotifcationComman($viewerUser->fcm_token,$pushTittle, $message,$request->user_id, $pushData);
        }
        $data = [
            'icon'=>asset('images/favicon/apple-touch-icon-152x152.png')
        ];
        $params = [
            'user_id'  => $request->user_id,
            'sender_id'=> $user->id,
            'title'    => $pushTittle,
            'message'  => $message,
            'type'     => 'user view',
            'data'     => json_encode($data),
        ];

        Notifcation::addNotificationHistory($params);

        
    }

    
    public function swipe_profiles(Request $request)
    {
      
//return $request->status;exit;
        $params     = $request->all();
             $likeuser   = User::find($request->user_id);

         $user = \Auth::user();
        $isUserLike = UsersLikes::where('like_from', $user->id)->where('like_to', $request->user_id)->first();
        
        // check user Order and plan start

        $order                     = $user->orderActive;

        if ($order) {
            
           // return 'i4i';exit;
            $plan                  = Plan::where('plan_type', Plan::ProPlanType)->first();

            $likeCount        = $plan->like_per_day;
            $superLikeCount        = $plan->super_like_par_day;
            $planStatus = 'paid';
            $remainingLikesCount =  100000;
            
        }else{
            //return 'ii';exit;
            $plan              = Plan::where('plan_type', Plan::FreePlanType)->first();

            $likeCount        = $plan->like_per_day;
            $superLikeCount        = $plan->super_like_par_day;
            $profileViewsIimit         = isset($freeSettings['profile_views_limit']) ? $freeSettings['profile_views_limit'] : 0;
            $planStatus                = 'free';
            if ($request->status == 'like') {
                $remainingLikesCount        = $this->remainingLikesCount($user->id, $likeCount, $planStatus);
                if ($remainingLikesCount <= 0) {
                    //return $this->errorResponse(['remaining_likes_count'=>$remainingLikesCount], 'Your like quota has been executed for today');   
                    return '0';
                    exit;
                }
            }
        }
        
        $currentRoute = $request->path();
        $remainingSuperLikes        = $this->remainingSuperLikes($user->id, $superLikeCount, $planStatus,$currentRoute);
        if ($request->status == 'super_like') {
            
            if ($remainingSuperLikes <= 0) {
               // return $this->errorResponse(['remaining_super_likes_count'=>$remainingSuperLikes], 'Your super like quota has been executed for today');   
               return '1';
               exit;
            }
        }

        $matchParams = [
            'like_from'    => $user->id,
            'like_to'      => $request->user_id,
            'notification' => 'on',
            'plan_status'  => $planStatus,
            'like_status'  => $request->status,
            'match_status' => 'nope',
            'read_status'  => 'unread',
        ];

        $msg        = '';
        $pushTittle = '';
        if($isUserLike) {
            $matchParams['id'] = $isUserLike->id;
        }

        if (in_array($request->status, ['nope','like'])) {
            // remove from who like me list if anyone like me but i unlike it
            if ($request->status == 'nope') {

                // remove from oposite user list
               UsersLikes::where(['like_from'=>$request->user_id,'like_to'=> $user->id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();

               // remove from my like list
               UsersLikes::where(['like_from'=>$user->id,'like_to'=> $request->user_id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();

               
            }
            if ($request->status == 'like') {
                $checkprofileMatch = UsersLikes::where(['like_from'=>$user->id,'like_to'=> $request->user_id])->first();
                if (!empty($checkprofileMatch) && $checkprofileMatch->match_id > 0) {
                   // return $this->errorResponse([], 'User already match with this profile');       
                   return '2';
                   exit;
                }
                UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();
                UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();
            }
        }
//return $matchParams;exit;
        $usersLikes      = UsersLikes::addUpdateUsersLikes($matchParams);
        
        if ($usersLikes) {

            if ($request->status == 'unmatch') {
                UsersLikes::where('match_id',$like->match_id)->update(['match_status' => 'nope','read_status'  => 'unread','like_status'=>'nope']);

                // remove from oposite user list
               UsersLikes::where(['like_from'=>$request->user_id,'like_to'=> $user->id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();

               // remove from my like list
               UsersLikes::where(['like_from'=>$user->id,'like_to'=> $request->user_id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();
            }

            $model = ReviewLatterProfile::where(['review_by'=>$user->id,'review_to'=>$request->user_id])->first();
            if (!empty($model)) {
                $model->delete();
            }
        }

        // add or update user like end


        // check For user match start
        $checkIsUserLike = UsersLikes::where('like_from', $user->id)->where('like_to', $request->user_id)->whereIn('like_status', ['like','super_like'])->first();
        $checkIsUserLikeFrom   = UsersLikes::where('like_from', $request->user_id)->where('like_to', $user->id)->whereIn('like_status', ['like','super_like'])->first();
        $match_id        = @UsersLikes::max('match_id');

        if ($match_id != 0) {
            $match_id = $match_id + 1;
        } else {
            $match_id = 10001;
        }

        $matchStatus = false;
        if($checkIsUserLike && $checkIsUserLikeFrom) {

            if ($request->status == 'like' || $request->status == 'super_like') {
                $match_status = 'match';
            }else {
                $match_status = $request->status;
            }

            $checkIsUserLike->match_id     = $match_id;
            $checkIsUserLike->match_status = 'match';
            $checkIsUserLike->matched_at   = date('Y-m-d H:i:s');
            $checkIsUserLike->save();

            $checkIsUserLikeFrom->match_id       = $match_id;
            $checkIsUserLikeFrom->match_status   = 'match';
            $checkIsUserLikeFrom->matched_at     = date('Y-m-d H:i:s');
            $checkIsUserLikeFrom->save();
            $matchStatus = true;

            //remove record from user view
            UserView::where('user_id', $request->user_id)->delete();

            //remove record for who like me. 
            UsersLikes::where('like_from', $request->user_id)->where('like_status', 'like')->whereNotIn('match_status', ['match'])->delete();
            
            //remove record for review latter
            ReviewLatterProfile::where(['review_by'=>$user->id,'review_to'=>$request->user_id])->delete();
        }
        
        $response = [];
        $pushData = [];
        $type     = 'like'; 
        if($matchStatus) {
            //$loginUserImage = $user->userImages;
            $likeUserImage  = User::where('id', $request->user_id)->first();
            
            //new
                $who_viewme = new UserView;
                $who_viewme->user_id = $request->user_id;
                $who_viewme->viewer_id = $user->id ;
				$who_viewme->save();
				
				$who_viewme = new UserView;
                $who_viewme->user_id = $user->id;
                $who_viewme->viewer_id = $request->user_id ;
				$who_viewme->save();
            //end

            $response       = [
                'match_status'         => 'match',
                'like_status'          => $request->status,
                'user_id'              => $user->id,
                'matched_user_id'      => $request->user_id,
                'match_id'             => $match_id,
                'user_image_url'       => !empty($user->userImages) ? $user->userImages : [],
                'match_user_image_url' => isset($likeUserImage) ? $likeUserImage->userImages : [],
                'match_user_name'      => isset($likeuser->first_name) ? $likeuser->first_name  : "",
            ];

            $pushTittle = 'Match your profile with '.$likeUserImage->first_name;
            $msg        = 'Congratulations! your profile matched with '.$likeUserImage->first_name.' '.$likeUserImage->last_name;
            
            $pushData   = array('match' => array('user_id' => $user->id,'type' => 'new_match'));
            // send to opposite user 
            $this->sendPushNotifcation($likeuser->fcm_token,'Congratulations!', 'You have a match with '.$user->first_name.' '.$user->last_name, $likeuser->id,$user->id, $pushData,0, 'new_match');
            
            $device_key = User::where('id',$likeuser->id)->pluck('device_key')->all();

           
            //WEB PUSH NOTIFICATION
                       $web_noti_data = [
                    "registration_ids" => $device_key,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => $msg, 
                        "click_action" => "https://gurutechnolabs.co.in/website/laravel/foreverus_in_love/discover",
                        "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
                    ]
                ];
        
                  $this->sendWebNotification($web_noti_data);
            //END
            
            

            // send to same user
            $this->sendPushNotifcation($user->fcm_token,'Congratulations!', 'You have a match with '.$likeuser->first_name.' '.$likeuser->last_name, $user->id,$likeuser->id, $pushData,0, 'new_match');
            
             $devicekey = User::where('id',$user->id)->pluck('device_key')->all();

           
            //WEB PUSH NOTIFICATION
                       $web_noti_data = [
                    "registration_ids" => $devicekey,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => $msg, 
                        "click_action" => "https://gurutechnolabs.co.in/website/laravel/foreverus_in_love/discover",
                        "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
                    ]
                ];
        
                  $this->sendWebNotification($web_noti_data);
            //END    
            
            
            /*return Response::json(array(
            'name_user' => $likeUserImage->first_name.' '.$likeUserImage->last_name,
            'uid' => $likeUserImage->id,
             ));*/
             
             
             // $res['success'] = true;
 
   return json_encode(array("uid"=>$likeUserImage->id,"name_user"=>$likeUserImage->first_name.' '.$likeUserImage->last_name));
  
             
           // return '3';
            exit;
        } else { 
            if(!$isUserLike) {
                $pushData = ['like' => ['user_id' => $user->id,'type' => 'like']];
                $msg        = '';
                $pushTittle = 'Like your profile by Someone';
                if ($user->orderActive) {
                    $msg        = 'Like your profile';
                    $pushTittle =  'Like your profile by '.$user->first_name;
                }
                if ($request->status == 'super_like') {
                    $msg        = 'Super Liked your profile';
                    $pushTittle = 'Someone has Super liked your profile.';    
                    if ($user->orderActive) {
                        $pushTittle = $user->first_name.' has Super liked your profile.';    
                    }
                    $type = 'super_like';
                }
            }

        } 
        if(!empty($msg) && in_array($request->status, ['like','super_like']) ) { 
            
            $devicekey = User::where('id',$likeuser->id)->pluck('device_key')->all();
            
             //WEB PUSH NOTIFICATION
                       $web_noti_data = [
                    "registration_ids" => $devicekey,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => $msg, 
                        "click_action" => "https://gurutechnolabs.co.in/website/laravel/foreverus_in_love/discover",
                        "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
                    ]
                ];
        
                  $this->sendWebNotification($web_noti_data);
            //END  
            
            
           $noticationStatus = $this->sendPushNotifcation($likeuser->fcm_token,$pushTittle, $msg, $likeuser->id,$user->id, $pushData,0, $type);
        }
        
        
        //new
              $freeSettings = Plan::where('plan_type', Plan::FreePlanType)->first();

        $freeLikesCount = !empty($freeSettings) ? $freeSettings->like_per_day : 0;
        
        $superLikeParDay                          = $freeSettings->super_like_par_day;
      
        $planStatus                               = 'free';
        $order = $user->orderActive;

        if($order) {
            $planStatus                           = 'paid';
            $freeLikesCount                       = !empty($order) ? $order->plan->getRawOriginal('like_per_day') : 0;
           
        }
        //end

       /* $response['remaining_likes_count']          = $this->remainingLikesCount($user->id, $likeCount, $planStatus);
       
        $response['remaining_super_likes_count']  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus) < 0 ? 0 : $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);
        $response['order']                       = $order;*/

       // return $this->successResponse($response, 'Success');
       return '4';

    }
    
    public function remainingLikesCount($userId, $freeLikesCount, $status = 'free')
    {

        if ($status == 'free') {
            $today_date         = Carbon::now();
            
            $freeLikesUsedCount = UsersLikes::where('like_from', $userId)
            ->where('like_status','!=','review')
            ->where('like_status','!=','super_like')
            ->where('plan_status', $status)
            ->whereDate('created_at', $today_date)->count();
            
            if($freeLikesUsedCount >= $freeLikesCount) {
                $freeLikesCount = 0;
            } else {
                $freeLikesCount = $freeLikesCount - $freeLikesUsedCount;
            }

            return $freeLikesCount;
        }
        if ($status == 'paid' && $freeLikesCount == '-1') {
            return 100000;
        }
        
    }
    
    public function remainingSuperLikes($userId, $superLikeCount, $status = 'free',$accessFrom = '')
    {


        $freeLikesUsedCount = UsersLikes::where('like_from', $userId)->where('like_status','super_like')->where('plan_status', $status)->whereDate('created_at','>=', Carbon::today())->count();
       
        //$purchased_likes = ProductsOrder::where('user_id',$userId)->orderBy('product_order_id','DESC')->first();
        
        $purchased_likes = ProductsOrder::where('user_id',$userId)->orderBy('product_order_id','DESC')->select(\DB::raw(' sum(qty) as total'))->pluck('total');
     // print_r($purchased_likes[0]);exit;
       
       $likes_purchased ="";
       
       if(!isset($purchased_likes[0]))
       {
          // echo '1';
          $likes_purchased = '0';
       }
       else
       {
          // echo '2';
           $likes_purchased = $purchased_likes[0];
          
       }
   // print_r($likes_purchased); exit;
       
        
        
        /*if($freeLikesUsedCount >= $superLikeCount) {
            $superLikeCount = 0;
        } */
        /*else {
            $superLikeCount = $superLikeCount - $freeLikesUsedCount;
        }*/
      
        if($freeLikesUsedCount >= '1')
        {
           //  $superLikeCount  =  $superLikeCount  + $purchased_likes[0]['qty'] - $freeLikesUsedCount;
           $superLikeCount  =  $superLikeCount  + $likes_purchased - $freeLikesUsedCount;
        }
          else if($freeLikesUsedCount == '0')
        {
            // $superLikeCount = $superLikeCount + $purchased_likes[0]['qty'];
            $superLikeCount = $superLikeCount + $likes_purchased;
        }
        
 //print_r($superLikeCount);exit;
        // check purchased superlike
        $authUser = Auth::user();
        
        if (!empty($authUser->userProductsOrder)) {
           // $superLikeCount = $superLikeCount+$authUser->userProductsOrder->qty;

            if ($accessFrom == 'api/swipe_profile' && $superLikeCount > 0) {
                $superLikePurchaseCount = $authUser->userProductsOrder->qty;
               // $authUser->userProductsOrder->qty = $superLikePurchaseCount-1;
                $authUser->userProductsOrder->save();
            }
        }
        
        
        
        return $superLikeCount;
    }
    
    
    
    public function viewFilter()
    {
        $relationship = RelationshipStatus::get();
        $looking = LookingFor::get();
        $height = Height::get();
        $pets = Pets::get();
        $education = Education::get();
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
        $language = Language::get();
        $substances = Drugs::get();
        
        $check_order = Order::where('user_id',\Auth::user()->id)->count();
        
        return view('web.filter')->with(['substances'=>$substances,'check_order' => $check_order ,'language'=> $language,'height'=> $height,'relationship'=>$relationship,'looking'=>$looking,'religion'=>$religion,'vaccinate'=>$vaccinate,'pets'=>$pets,'interests'=>$interests,'arts'=>$arts,'horoscopes' => $horoscopes,'ice_breaker'=>$ice_breaker,'political_views'=>$political_views,'smoking'=>$smoking,'education'=>$education,'drinks' => $drinks,'dietary_lifestyle' => $dietary_lifestyle]);
    }
    
    public function profileDetails($id)
    {
        $height = User::join('height','height.id','=','users.height')->where('users.id',$id)->pluck('title')->toArray();
        //print_r($height);exit;
        $user = User::with([
           // 'UserHobbies',
            'userEducations',
            'userLookingFor',
            'userDietaryLifestyle',
            'userPets',
            'userArts',
            'userLanguage',
            'userInterests',
            'userDrink',
            'userDrugs',
            'userHoroscope',
            'userReligion',
            'userPoliticalLeaning',
            'userRelationshipStatus',
            'userLifeStyle',
            'userFirstDateIceBreaker',
            'userCovidVaccine',
            'userSmoking',
            'orderActive'
        ]
        )->where('id', $id)->first();
        
        $userimgs = User::with('userImages')->where('id', $id)->first();

        
        
      // print_r($user['userReligion']);exit;
        //print_r($user['userLanguage'][0]['title']);exit;
        return view('web.profile_details')->with(['user'=>$user,'height'=>$height,'userimgs' => $userimgs]);
    }
    
    public function discover()
    {
        //$params = $request->all();		//return $params;
        $user   = Auth::user();		

        /*$latitude  = isset($params['latitude']) ? $params['latitude']   : $user->latitude;
        $longitude = isset($params['longitude']) ? $params['longitude'] : $user->longitude;*/
        
        $latitude  =  $user->latitude;
        $longitude =  $user->longitude;

       /* if (empty($latitude) || empty($longitude)) {
            return $this->errorResponse([], 'latitude and longitude should not empty');
        }*/

        if(isset($user->status) && $user->status != 'active') {
            //return $this->errorResponse([], 'While paused you won’t get new matches, but you will still be able to chat to the old ones. So, no more swiping');
            echo 'While paused you won’t get new matches, but you will still be able to chat to the old ones. So, no more swiping';
            exit;
        }

        // check user paid or not
        $order = $user->orderActive;
        if (empty($order)) {
            $freePlan = Plan::where('id',1)->first();

            $freefilter = explode(",",$freePlan->search_filters);

            //imp
            /*$filtered = Arr::except($request->all(), $freefilter);
            if (!empty($filtered)) {
               // return $this->errorResponse([], 'Upgrade Your plan to use all filter');
               echo 'Upgrade Your plan to use all filter';
               exit;
            }*/
        }

        // get users reported user
        $usersReport = UsersReport::where('reporter_id', $user->id)->pluck('user_id');
        
        // remove like from and too users start
        $userLike    = UsersLikes::where('like_from', $user->id)->get();

        // review later profile
        $reviewLatter = ReviewLatterProfile::where('review_by',$user->id)->pluck('review_to');


        $userIds  = [];
        if(!empty($userLike)) {
            foreach ($userLike as $key => $like) {
                $userIds[] = $like->like_to;
            }
        }

        if(!empty($usersReport)) {	
            foreach ($usersReport as $key => $report) {
                $userIds[] = $report;
            }
        }
        
        

        if(!empty($reviewLatter)) {  
            foreach ($reviewLatter as $key => $review) {
                $userIds[] = $review;
            }
        }
        $userIds = array_unique($userIds);
        

        $multipleQuestion = [];

        // for multiple start
       /* if(!empty($request->education)) {
            $multipleQuestion['educationData'] = $this->commaStringToArray($request->education);
        }
        
        if(!empty($request->looking_for)) {
            $multipleQuestion['lookingForData'] = $this->commaStringToArray($request->looking_for);
        }

        if(!empty($request->dietary_lifestyle)) {
            $multipleQuestion['dietaryLifestyleData'] = $this->commaStringToArray($request->dietary_lifestyle);
        }

        if(!empty($request->pets)) {
            $multipleQuestion['petsData'] = $this->commaStringToArray($request->pets);
        }

        if(!empty($request->arts)) {
            $multipleQuestion['artsData'] = $this->commaStringToArray($request->arts);
        }

        if(!empty($request->language)) {
            $multipleQuestion['languageData'] = $this->commaStringToArray($request->language);
        }*/

       /* if(!empty($request->interests)) {
            $multipleQuestion['interestsData'] = $this->commaStringToArray($request->interests);
        }
        
        $allMultiQuestionId = [];
        foreach ($multipleQuestion as $key => $question) {
            foreach ($question as $key => $queId) {
                $allMultiQuestionId[] = $queId;
            }
        }*/
        
        

        // for multiple end
        
         $user_list=array();

        $users   = User::where('users.id', '!=', $user->id)
            ->whereNotIn('users.id', $userIds)
            ->where('users.status', '=', 'active')
            ->where('users.first_name', '!=', '')
            ->where('users.email', '!=', '')
            ->where('users.phone', '!=', '')
            ->where('users.user_type', '=', 'user')
            ->where('email_verified','!=',0) 
            ->where('users.gender',$user->user_intrested_in)
            ->select(['users.*','users.id AS uid',DB::raw('(round( 6371 * acos( cos( radians('.$latitude.') ) * cos( radians( users.latitude ) ) * cos( radians( users.longitude ) - radians('.$longitude.') ) + sin( radians('.$latitude.') ) * sin( radians( users.latitude ) ) ))) AS distance')])->get();
//print_r($users);exit;

        if(isset($users))
                   {
                        foreach($users AS $user_data)
                        {
                            $attachment_array = DB::select("select * from user_images WHERE user_id='".$user_data->id."' LIMIT 1 ");
                          // $attachment_array = UserImages::where('user_id',$user_data->id)->first();
                                       
                                        $user_data->user_uploaded_images = $attachment_array;
                                        $user_list[] = $user_data;                       
                         }
                   }

//print_r($user_list[0]['user_uploaded_images'][0]->url);exit;

        if (!empty($user->user_intrested_in)) 
        {
          /*  if (strtolower($user->user_intrested_in) == 'both') {
                $users = $users->whereIn('users.gender',['female','male']);*/
                
            if (strtolower($user->user_intrested_in) == 'other') 
            {
                $users = $users->where('users.gender','other');
            }
            else
            {
                $users = $users->where('users.gender',$user->user_intrested_in);
            }
        }
        
        if(!empty($request->address)){
           
            //  $users = $users->where('users.address', '>=', $request->address);
             $users = $users->where('users.address', 'LIKE', '%'.urldecode($request->address).'%'); 
        }

        if(!empty($request->min_age)) {
            $users = $users->where('users.age', '>=', $request->min_age);
        }

        if(!empty($request->max_age)) {
            $users = $users->where('users.age', '<=', $request->max_age);
        }
        
        if (!empty($allMultiQuestionId)) {
            $users = $users->whereIn('user_questions.question_id', $allMultiQuestionId)->where('users.gender',$user->user_intrested_in);
        }

        if(!empty($request->covid_vaccine)) {
            $users = $users->where('users.covid_vaccine', $request->covid_vaccine);
        }

        if(!empty($request->drink)) {
            $users = $users->where('users.drink', $request->drink);
        }

        if(!empty($request->drugs)) {
            $users = $users->where('users.drugs', $request->drugs);
        }
        
        if(!empty($request->first_date_ice_breaker)) {
            $users = $users->where('users.first_date_ice_breaker', $request->first_date_ice_breaker);
        }

        if(!empty($request->horoscope)) {
            $users = $users->where('users.horoscope', $request->horoscope);
        }

        if(!empty($request->life_style)) {
            $users = $users->where('users.life_style', $request->life_style);
        }

        if(!empty($request->political_leaning)) {
            $users = $users->where('users.political_leaning', $request->political_leaning);
        }

        if(!empty($request->relationship_status)) {
            $users = $users->where('users.relationship_status', $request->relationship_status);
        }

        if(!empty($request->religion)) {
            $users = $users->where('users.religion', $request->religion);
        }

        if(!empty($request->smoking)) {
            $users = $users->where('users.smoking', $request->smoking);
        }

        /*if (!empty($request->max_height) && !empty($request->min_height)) {
            $users = $users->whereBetween('height',[$request->min_height,$request->max_height]);
        }*/ 
        
        /*Log::info($request->min_height);
        Log::info($request->max_height);*/
        
        if(!empty($request->min_height)) {
            //free
            $users = $users->where('height','>=', $request->min_height);
        }
        
        if(!empty($request->max_height)) {
            //free
            $users = $users->where('height','<=', $request->max_height);
        }

        
           if(!empty($request->min_distance) && $request->min_distance >= 0)
        {
            
            if(!empty($request->max_distance) && $request->max_distance <= 90)
            {
             
            }
            
            else if(!empty($request->max_distance) && $request->max_distance == 100)
            {
                  $users = $users->having('distance','>=', $request->min_distance);
            }
            
            
        }


       // $users = $user_list->/*groupBy('users.id')->*/orderBy('distance', 'ASC');
        
        // to get data from old filter start
        if (!empty($request->is_apply_filter)) {
            $filter = UserFilter::where('user_id', $user->id)->first();
            if(!empty($filter)) {
                $filter = $filter->toArray();
                $params = json_decode($filter['filter'],true);
            }
        }
        // to get data from old filter end


        // save filter request start
        /*if ($request->is_apply_filter == 0) {
            $userFilter    = UserFilter::where('user_id', $user->id)->first();
            $filterParam   = [];
            if($userFilter) {
                $filterParam['id'] = $userFilter->id;
            }
            $saveRequest = $request->except(['is_apply_filter']);
            $filterParam['filter']  = json_encode($saveRequest);
            $filterParam['user_id'] = $user->id;
            //$userId                 = $user->id;
            UserFilter::addUpdateUserFilter($filterParam);
        }*/
        
        // save filter request end

        //echo '<pre>';print_r($users->toSql());echo '<pre>';exit();
        //echo '<pre>';print_r($users->getBindings());echo '<pre>';exit();
        
      /*  $pageSize = isset($request->pageSize) ? $request->pageSize : 10;

        $users                                    = $users->paginate($pageSize);*/
        
      //  print_r($users);exit;
        
        //$page                                     = isset($params['page']) ? $params['page'] : 1;
       // $pageSize                                 = isset($params['pageSize']) ? $params['pageSize'] : 10;
        //$users                                    = $users->paginate($pageSize, ['*'], 'page', $page);
       ///////////// $user_                                   = $user_list->get();
        
       // print_r($users);exit;
        
         if(isset($request->min_distance) && $request->min_distance >= 0 && empty($request->address))
        { 
            if(isset($request->max_distance) && $request->max_distance <= 99)
            { 
                $users = $users->filter(function($result) use ($request){
                    return (($result->distance >= $request->min_distance) && ($result->distance <= $request->max_distance));
                })->sortBy('distance')->values();

                // $users = $users->having('distance','>=', $request->min_distance);
                // $users = $users->having('distance','<=', $request->max_distance);
                
            }
            
        }
        
        //$freeReviewLaterCount                     = 0;
        
        
        $freeSettings                             = Plan::where('plan_type', Plan::FreePlanType)->first();

        $freeLikesCount                           = !empty($freeSettings) ? $freeSettings->like_per_day : 0;
        $superLikeParDay                          = $freeSettings->super_like_par_day;
        //$profileViewsIimit                        = isset($freeSettings['profile_views_limit']) ? $freeSettings['profile_views_limit'] : 0;
        $planStatus                               = 'free';
        $order = $user->orderActive;

        if($order) {
            $planStatus                           = 'paid';
            $freeLikesCount                       = !empty($order) ? $order->plan->getRawOriginal('like_per_day') : 0;
            //$profileViewsIimit                    = isset($order['profile_views_limit']) ? $order['profile_views_limit'] : 0;
        }

      /////////  $response['remaining_likes_count']        = $this->remainingLikesCount($user->id, $freeLikesCount, $planStatus);
        //new
        
        // $user                 = $request->user();
      
           // $user->orderActive->plan;
       // $response['plan_details'] = $user->orderActive;
        
       /// $response['remaining_super_likes_count']  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);
       /////////// $response['remaining_super_likes_count']  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus) < 0 ? 0 : $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);
        
        //exit;
        
        
        //$response['remaining_profile_view_count'] = $this->remainingProfileViewsCount($user->id, $profileViewsIimit, $planStatus);
        //$response['remaining_review_later_count'] = $this->remainingReviewLaterCount($user->id, $freeReviewLaterCount);
        $response['users']                        = $users;
        
        $response['is_limited']              = 'yes';
        $response['is_order']                = 'no';
        $response['is_limited_profie_view']  = 'yes';
        

       // $userDefaultSettings                     = UserDefaultSettings::get()->pluck('value', 'key');
       // $response['minimum_age']                 = isset($userDefaultSettings['minimum_age']) ? (int)$userDefaultSettings['minimum_age'] : '';
       // $response['maximum_age']                 = isset($userDefaultSettings['maximum_age']) ? (int)$userDefaultSettings['maximum_age'] : '';
       // $settings                                = Settings::get()->pluck('value', 'key');
      /*  $response['android_version']             = isset($settings['android_version']) ? $settings['android_version'] : '';
        $response['ios_version']                 = isset($settings['ios_version']) ? $settings['ios_version'] : '';
        $response['params']                      = $params;
        $response['unread_count']                = UsersMessages::where('receiver_id', $user->id)->where('read_status', 'unread')->count();
        $response['order']                       = $order;
        $response['user_settings']               = $user->userSettings;*/

       /* return $this->successResponse($response, 'Success');*/
       
       
      // print_r($user_list[0]->user_uploaded_images->url);exit;
      
    
    
    //test
      $remaining_likes_count       = $this->remainingLikesCount($user->id, $freeLikesCount, $planStatus);
       
       $remaining_super_likes_count  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus) < 0 ? 0 : $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);
    //end
    
   //print_r($user_list);exit;
       
        return view('web.discover')->with(['user_list'=>$user_list,'remaining_likes_count' => $remaining_likes_count,'remaining_super_likes_count' => $remaining_super_likes_count]);
    }
    
    public function filterDiscover(Request $request)
    {
        $params = $request->all();		//return $params;
        
        
    //  print_r($params);exit;
        
        $user   = \Auth::user();		

        $latitude  = isset($params['latitude']) ? $params['latitude']   : $user->latitude;
        $longitude = isset($params['longitude']) ? $params['longitude'] : $user->longitude;

        /*if (empty($latitude) || empty($longitude)) {
            return $this->errorResponse([], 'latitude and longitude should not empty');
        }*/

        if(isset($user->status) && $user->status != 'active') {
           // return $this->errorResponse([], 'While paused you won’t get new matches, but you will still be able to chat to the old ones. So, no more swiping');
           echo 'test';exit;
        }

        // check user paid or not
        //$order = $user->orderActive;
        $check_order = Order::where('user_id',\Auth::user()->id)->count();
        
       
        
        //if (empty($order)) {
        
        if ($check_order == 0) 
        {
            $request->request->remove('_token');
            $request->request->remove('address');
            $request->request->remove('relationship_status');
            
            
            $freePlan = Plan::where('id',1)->first();

            $freefilter = explode(",",$freePlan->search_filters);
            
             

            $filtered = Arr::except($request->all(), $freefilter);
            
           // print_r($filtered);exit;
            
            if (!empty($filtered)) {
                return $this->errorResponse([], 'Upgrade Your plan to use all filter');
            }
        }

        // get users reported user
        $usersReport = UsersReport::where('reporter_id', $user->id)->pluck('user_id');
        
        // remove like from and too users start
        $userLike    = UsersLikes::where('like_from', $user->id)->get();

        // review later profile
        $reviewLatter = ReviewLatterProfile::where('review_by',$user->id)->pluck('review_to');


        $userIds  = [];
        if(!empty($userLike)) {
            foreach ($userLike as $key => $like) {
                $userIds[] = $like->like_to;
            }
        }

        if(!empty($usersReport)) {	
            foreach ($usersReport as $key => $report) {
                $userIds[] = $report;
            }
        }
        
        

        if(!empty($reviewLatter)) {  
            foreach ($reviewLatter as $key => $review) {
                $userIds[] = $review;
            }
        }
        $userIds = array_unique($userIds);
        

        $multipleQuestion = [];

        // for multiple start
        if(!empty($request->education)) {
          //  print_r(implode(',',$request->language));
            $multipleQuestion['educationData'] = $this->commaStringToArray(implode(',', $request->education));
        }
        
        if(!empty($request->user_looking_for)) {
            $multipleQuestion['lookingForData'] = $this->commaStringToArray(implode(',', $request->user_looking_for));
        }

        if(!empty($request->dietary_lifestyle)) {
            $multipleQuestion['dietaryLifestyleData'] = $this->commaStringToArray(implode(',', $request->dietary_lifestyle));
        }

        if(!empty($request->pets)) {
            $multipleQuestion['petsData'] = $this->commaStringToArray(implode(',', $request->pets));
        }

        if(!empty($request->arts)) {
            $multipleQuestion['artsData'] = $this->commaStringToArray(implode(',', $request->arts));
        }

        if(!empty($request->language)) {
            $multipleQuestion['languageData'] = $this->commaStringToArray(implode(',', $request->language));
        }

        if(!empty($request->interests)) {
            $multipleQuestion['interestsData'] = $this->commaStringToArray(implode(',', $request->interests));
        }
        
        $allMultiQuestionId = [];
        foreach ($multipleQuestion as $key => $question) {
            foreach ($question as $key => $queId) {
                $allMultiQuestionId[] = $queId;
            }
        }
       // print_r($allMultiQuestionId);exit;
        

        // for multiple end

        $users   = User::with([
           /* 'userEducations',
            'userLookingFor',
            'userDietaryLifestyle',
            'userPets',
            'userArts',
            'userLanguage',
            'userInterests',
            'userDrink',
            'userDrugs',
            'userHoroscope',
            'userReligion',
            'userPoliticalLeaning',
            'userRelationshipStatus',
            'userLifeStyle',
            'userFirstDateIceBreaker',
            'userCovidVaccine',
            'userSmoking',*/
            'userImages',
            'orderActive'
        ])->leftJoin('user_questions','user_questions.user_id','users.id')
            ->where('users.id', '!=', $user->id)
            ->whereNotIn('users.id', $userIds)
            ->where('users.status', '=', 'active')
            ->where('users.first_name', '!=', '')
            ->where('users.email', '!=', '')
            ->where('users.phone', '!=', '')
            ->where('users.user_type', '=', 'user')
            ->where('email_verified','!=',0) 
            ->select(['users.*',DB::raw('(round( 6371 * acos( cos( radians('.$latitude.') ) * cos( radians( users.latitude ) ) * cos( radians( users.longitude ) - radians('.$longitude.') ) + sin( radians('.$latitude.') ) * sin( radians( users.latitude ) ) ))) AS distance')]);
//print_r($users);exit;

        if (!empty($user->user_intrested_in)) 
        {
          /*  if (strtolower($user->user_intrested_in) == 'both') {
                $users = $users->whereIn('users.gender',['female','male']);*/
                
            if (strtolower($user->user_intrested_in) == 'other') 
            {
                $users = $users->where('users.gender','other');
            }
            else
            {
                $users = $users->where('users.gender',$user->user_intrested_in);
            }
        }
        
        if(!empty($request->address)){
           
            //  $users = $users->where('users.address', '>=', $request->address);
             $users = $users->where('users.address', 'LIKE', '%'.urldecode($request->address).'%'); 
        }

        if(!empty($request->min_age)) {
            $users = $users->where('users.age', '>=', $request->min_age);
        }

        if(!empty($request->max_age)) {
            $users = $users->where('users.age', '<=', $request->max_age);
        }
        
        if (!empty($allMultiQuestionId)) {
           // print_r($allMultiQuestionId);exit;
            //echo "basfsfpa";exit;
            $users = $users->whereIn('user_questions.question_id', $allMultiQuestionId)->where('users.gender',$user->user_intrested_in);
        }
//print_r($users->whereIn('user_questions.question_id', $allMultiQuestionId)->where('users.gender',$user->user_intrested_in));exit;
        if(!empty($request->covid_vaccine)) {
            $users = $users->where('users.covid_vaccine', $request->covid_vaccine);
        }

        if(!empty($request->drink)) {
            $users = $users->where('users.drink', $request->drink);
        }

        if(!empty($request->drugs)) {
            $users = $users->where('users.drugs', $request->drugs);
        }
        
        if(!empty($request->first_date_ice_breaker)) {
            $users = $users->where('users.first_date_ice_breaker', $request->first_date_ice_breaker);
        }

        if(!empty($request->horoscope)) {
          
            $users = $users->where('users.horoscope', $request->horoscope);
        }

        if(!empty($request->life_style)) {
            $users = $users->where('users.life_style', $request->life_style);
        }

        if(!empty($request->political_leaning)) {
            $users = $users->where('users.political_leaning', $request->political_leaning);
        }

        if(!empty($request->relationship_status)) {
            $users = $users->where('users.relationship_status', $request->relationship_status);
        }

        if(!empty($request->religion)) {
            $users = $users->where('users.religion', $request->religion);
        }

        if(!empty($request->smoking)) {
            $users = $users->where('users.smoking', $request->smoking);
        }

       
       
        if(!empty($request->min_height)) {
            //free
            $users = $users->where('height','>=', $request->min_height);
        }
        
        if(!empty($request->max_height)) {
            //free
            $users = $users->where('height','<=', $request->max_height);
        }

        
           if(!empty($request->min_distance) && $request->min_distance >= 0)
        {
            
            if(!empty($request->max_distance) && $request->max_distance <= 90)
            {
                   
            }
            
            else if(!empty($request->max_distance) && $request->max_distance == 100)
            {
                  $users = $users->having('distance','>=', $request->min_distance);
            }
            
            
        }


        $users = $users->groupBy('users.id')->orderBy('distance', 'ASC');
       // print_r($users);exit;
        
        // to get data from old filter start
       /* if (!empty($request->is_apply_filter)) {
            $filter = UserFilter::where('user_id', $user->id)->first();
            if(!empty($filter)) {
                $filter = $filter->toArray();
                $params = json_decode($filter['filter'],true);
            }
        }*/
        // to get data from old filter end


        // save filter request start
       /* if ($request->is_apply_filter == 0) {
            $userFilter    = UserFilter::where('user_id', $user->id)->first();
            $filterParam   = [];
            if($userFilter) {
                $filterParam['id'] = $userFilter->id;
            }
            $saveRequest = $request->except(['is_apply_filter']);
            $filterParam['filter']  = json_encode($saveRequest);
            $filterParam['user_id'] = $user->id;
            //$userId                 = $user->id;
            UserFilter::addUpdateUserFilter($filterParam);
        }*/
        
        // save filter request end

        //echo '<pre>';print_r($users->toSql());echo '<pre>';exit();
        //echo '<pre>';print_r($users->getBindings());echo '<pre>';exit();
        
      /*  $pageSize = isset($request->pageSize) ? $request->pageSize : 10;

        $users                                    = $users->paginate($pageSize);*/
     
        $users                                    = $users->get();
        
        
        
         if(isset($request->min_distance) && $request->min_distance >= 0 && empty($request->address))
        { 
            
            //echo "tetet";exit;
            if(isset($request->max_distance) && $request->max_distance <= 99)
            { 
                $users = $users->filter(function($result) use ($request){
                    return (($result->distance >= $request->min_distance) && ($result->distance <= $request->max_distance));
                })->sortBy('distance')->values();
                
            }
            
        }
        
        
        
      //  print_r($users[0]['userImages'][0]['url']);exit;
        
        //$freeReviewLaterCount                     = 0;
        
        
      /*  $freeSettings                             = Plan::where('plan_type', Plan::FreePlanType)->first();

        $freeLikesCount                           = !empty($freeSettings) ? $freeSettings->like_per_day : 0;
        $superLikeParDay                          = $freeSettings->super_like_par_day;
        //$profileViewsIimit                        = isset($freeSettings['profile_views_limit']) ? $freeSettings['profile_views_limit'] : 0;
        $planStatus                               = 'free';
        $order = $user->orderActive;*/

      /*  if($order) {
            $planStatus                           = 'paid';
            $freeLikesCount                       = !empty($order) ? $order->plan->getRawOriginal('like_per_day') : 0;
            //$profileViewsIimit                    = isset($order['profile_views_limit']) ? $order['profile_views_limit'] : 0;
        }*/

        //$response['remaining_likes_count']        = $this->remainingLikesCount($user->id, $freeLikesCount, $planStatus);
        //new
        
        // $user                 = $request->user();
      
        
        //$response['remaining_super_likes_count']  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus) < 0 ? 0 : $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);
        
        
       /* $response['users']                        = $users;
        
        $response['is_limited']              = 'yes';
        $response['is_order']                = 'no';
        $response['is_limited_profie_view']  = 'yes';*/
        

       /* $userDefaultSettings                     = UserDefaultSettings::get()->pluck('value', 'key');
        $response['minimum_age']                 = isset($userDefaultSettings['minimum_age']) ? (int)$userDefaultSettings['minimum_age'] : '';
        $response['maximum_age']                 = isset($userDefaultSettings['maximum_age']) ? (int)$userDefaultSettings['maximum_age'] : '';
        $settings                                = Settings::get()->pluck('value', 'key');
        $response['android_version']             = isset($settings['android_version']) ? $settings['android_version'] : '';
        $response['ios_version']                 = isset($settings['ios_version']) ? $settings['ios_version'] : '';
        $response['params']                      = $params;
        $response['unread_count']                = UsersMessages::where('receiver_id', $user->id)->where('read_status', 'unread')->count();
        $response['order']                       = $order;
        $response['user_settings']               = $user->userSettings;*/
        
        $freeSettings                             = Plan::where('plan_type', Plan::FreePlanType)->first();

        $freeLikesCount                           = !empty($freeSettings) ? $freeSettings->like_per_day : 0;
        $superLikeParDay                          = $freeSettings->super_like_par_day;
        //$profileViewsIimit                        = isset($freeSettings['profile_views_limit']) ? $freeSettings['profile_views_limit'] : 0;
        $planStatus                               = 'free';
        $order = $user->orderActive;

        if($order) {
            $planStatus                           = 'paid';
            $freeLikesCount                       = !empty($order) ? $order->plan->getRawOriginal('like_per_day') : 0;
            //$profileViewsIimit                    = isset($order['profile_views_limit']) ? $order['profile_views_limit'] : 0;
        }
        
          $remaining_likes_count       = $this->remainingLikesCount($user->id, $freeLikesCount, $planStatus);
       
       $remaining_super_likes_count  = $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus) < 0 ? 0 : $this->remainingSuperLikes($user->id, $superLikeParDay, $planStatus);

  //print_r($users[0]['userImages'][0]['url']);exit;
 //print_r($users);exit;
 
 return view('web.discover_filter')->with(['users' => $users, 'remaining_likes_count' => $remaining_likes_count,'remaining_super_likes_count'=>$remaining_super_likes_count]);;
 //exit;
 
//  redirect()->route('filter.result')->with( ['users' => $users, 'remaining_likes_count' => $remaining_likes_count,'remaining_super_likes_count'=>$remaining_super_likes_count] );

       // return view('web.discover_filter')->with(['users' => $users, 'remaining_likes_count' => $remaining_likes_count,'remaining_super_likes_count'=>$remaining_super_likes_count]);
  
        //print_r($response);exit;
       // return $this->successResponse($response, 'Success');

    }
  //  public function discover_filter(){return view('web.discover_filter');}
    
   
}