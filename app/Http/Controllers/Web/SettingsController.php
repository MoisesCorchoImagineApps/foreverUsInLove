<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Models\Suggestion;
use App\Models\ProductsOrder;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\ContactSupport;
use App\Models\Notifcation;
use App\Models\UserSettings;
use App\Models\Pages;
use Auth;
use Illuminate\Support\Facades\Http;


class SettingsController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }
    
    public function updateStatus(Request $request)
    {
        // Handle the AJAX request and update the user's status in Firebase
        $userId = Auth::user()->id;
       // $currentTimestamp = '"' . now()->timestamp . '"';
           $currentTimestampMilliseconds = now()->timestamp * 1000;


        
        // Perform the Firebase update using the Firebase PHP SDK or HTTP API
        // Example using HTTP API (replace with your Firebase details):
        $firebaseUrl = 'https://forever2-c9cef-default-rtdb.firebaseio.com';
        $firebaseSecret = '3vUEVYHyb5ytfNFO3kHB5RdTyRyoYlS4vTI2KWQZ';
        
        $response = Http::put("$firebaseUrl/onlineStatus/$userId.json?auth=$firebaseSecret", [
            'lastSeen' => $currentTimestampMilliseconds,
            'status' => '1',
        ]);

        if ($response->successful()) {
            return response()->json(['message' => 'Status updated in Firebase']);
        } else {
            return response()->json(['error' => 'Failed to update status'], 500);
        }
    }

    public function ViewFAQs()
    {
        return view('web.faqs');
    }
    
    public function saveContactSupport(Request $request)
    {
          $data = [
            'description'=>$request->description,
            'email'=>$request->email,
            'name'=>$request->name,
        ];

        ContactSupport::create($data);
    }
    
    public function saveSuggestion(Request $request)
    {
        $data = [
            'suggestion_desc'=>$request->suggestion_desc,
        ];

        Suggestion::create($data);
    }
    
    public function viewPage()
    {
        $privacy_policy = Pages::where('page_type','privacy_policy')->first();
        $basic_settings = UserSettings::where('user_id',\Auth::user()->id)->first();
        $terms_and_conditions = Pages::where('page_type','terms_and_conditions')->first(); 
        return view('web.settings')->with(['privacy_policy' => $privacy_policy,'terms_and_conditions' => $terms_and_conditions,'basic_settings' => $basic_settings]);
    }
    
    public function updatePushNotiStatus(Request $request)
    {
        UserSettings::where("user_id",\Auth::user()->id)->update(array('show_notification' => $request->show_notification));
    }
    
    public function updateDistanceStatus(Request $request)
    {
         UserSettings::where("user_id",\Auth::user()->id)->update(array('distance_unit' => $request->distance_unit));
    }
    
     public function updateEmailNotiStatus(Request $request)
    {
        UserSettings::where("user_id",\Auth::user()->id)->update(array('send_mail' => $request->send_mail));
    }
    
    public function viewNotifications()
    {
        $user         = \Auth::user(); 
       $notifcation  = Notifcation::where('user_id', $user->id)->whereNotIn('type',['message','review_later'])->orderBy('id', 'DESC')->get();
       $userSettings = UserSettings::where('user_id',$user->id)->first();
       foreach ($notifcation as $key => &$value) {
           $senderUser        = isset($value->sender_id) ? $value->sender_id : '';
           $senderUser        = User::where('id', $senderUser)->first();
           $value->first_name = isset($senderUser->first_name) ? $senderUser->first_name : '';
           $value->user_image = isset($senderUser->userImages) ? $senderUser->userImages : [];
       }
       $result = [
            'notifcation' => $notifcation,
            //'push_notifcation' => isset($userSettings->push_notification) ? $userSettings->push_notification : 0
       ];
      //print_r($notifcation[0]['user_image'][0]['url']);
        return view('web.notifications')->with(['notification'=>$notifcation]);
    }
    
   
    
   
}