<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use Auth;
use Illuminate\Support\Facades\Http;
use Kreait\Firebase\Messaging;
use Kreait\Firebase\Database;




class FirebaseController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }
    

    public function initializeFirebase()
    {
        /*$firebaseConfig = [
             
                apiKey: "AIzaSyBa0bj1AU-_R1woXIgqGXN0u3kQmppkP9A",
                authDomain: "forever2-c9cef.firebaseapp.com",
                databaseURL: "https://forever2-c9cef-default-rtdb.firebaseio.com",
                projectId: "forever2-c9cef",
                storageBucket: "forever2-c9cef.appspot.com",
                messagingSenderId: "366052867227",
                appId: "1:366052867227:web:0c6503faf108d50dce9a3e",
                measurementId: "G-P63J70TFRS"
             
        ];*/

        $firebaseConfig = [
            'apiKey' => 'AIzaSyBa0bj1AU-_R1woXIgqGXN0u3kQmppkP9A',
            'authDomain' => 'forever2-c9cef.firebaseapp.com',
            'databaseURL' => 'https://forever2-c9cef-default-rtdb.firebaseio.com',
            'projectId' => 'forever2-c9cef',
            'storageBucket' => 'forever2-c9cef.appspot.com',
            'messagingSenderId' => '366052867227',
            'appId' => '1:366052867227:web:0c6503faf108d50dce9a3e',
            'measurementId' => 'G-P63J70TFRS',
        ];
        

        config(['services.firebase' => $firebaseConfig]);

        $messaging = app(Messaging::class);
        $database = app(Database::class);

        // Initialize Firebase
        $messaging->useApp($database->getApp());
    }

    public function requestNotificationPermission(Request $request)
    {
        $phone = $request->input('phone');

        $messaging = app(Messaging::class);

        try {
            $token = $messaging->requestPermission()->getToken();

            // Store the token in your database
            // Replace the following line with your database logic
            // e.g., User::where('phone', $phone)->update(['fcm_token' => $token]);

            return response()->json(['status' => 'success', 'token' => $token]);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }

    public function updateOnlineStatus(Request $request)
    {
        $currentUserLoginId = $request->input('login_userid');
        $currentTimestamp = now()->timestamp;

        $database = app(Database::class);
        $onlineStatusRef = $database->getReference("onlineStatus/{$currentUserLoginId}");

        try {
            $onlineStatusRef->set([
                'lastSeen' => $currentTimestamp,
                'status' => '1',
            ]);

            return response()->json(['status' => 'success']);
        } catch (\Exception $e) {
            return response()->json(['status' => 'error', 'message' => $e->getMessage()]);
        }
    }
   
    

   
    
   
 
   
}