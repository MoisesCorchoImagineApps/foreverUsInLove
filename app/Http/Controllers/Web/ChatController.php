<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Models\UserPrivateChat;
use App\Models\ReviewLatterProfile;
use App\Models\Notifcation;
use App\Http\Controllers\Controller;
use App\Models\UsersMessages;
use App\Models\User;
use App\Models\UsersLikes;
use App\Models\UserView;
use App\Models\ReportsManagement;
use App\Models\Order;
use App\Models\UsersReport;
use DB;
use App\Models\UsersPrivateMessages;
use App\Models\SingleCallRequests;
use Auth;
use App\Models\OnGoingCall;

class ChatController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function declineSinglevideoall(Request $request)
    {
        SingleCallRequests::where('receiver_id',$request->user_id)->delete();
    }

    public function consumeSinglevideocall(Request $request)
    {
              $call_data = OnGoingCall::where(['reaciver_user_id'=>$request->user_id])->first();
              SingleCallRequests::where('receiver_id',$request->user_id)->delete();

              return json_encode(array("name_of_channel"=>$call_data->channel_name,"token"=>$call_data->reaciver_token));
    }

     // Handle the AJAX request to initiate a call
    public function initiateCall(Request $request) {
       // return $request->recipientUserId;exit;
        $recipientUserId = $request->input('recipientUserId');

        // Insert a record into the call_requests table
        $callRequest = SingleCallRequests::create([
            'sender_id' => Auth::user()->id, // Use the authenticated user's ID
            'receiver_id' => $recipientUserId,
            'call_status' => 'pending',
        ]);

        // You can return a response or perform additional actions as needed
        return response()->json(['message' => 'Call initiated']);
    }

    // Handle the AJAX request to check for incoming calls
    public function checkIncomingCalls(Request $request) {
      //  return $request->all();exit;
        $recipientUserId = $request->input('receiver_id');

        // Check for pending call requests for the recipient
       /* $callRequest = SingleCallRequests::where('receiver_id', $recipientUserId)
            ->where('call_status', 'pending')
            ->latest() // Get the latest pending call request
            ->first();*/

            $callRequest = SingleCallRequests::join('users','users.id','=','single_call_requests.sender_id')
            ->select('users.first_name','users.last_name','single_call_requests.*')
            ->where('receiver_id', $recipientUserId)
            ->where('call_status', 'pending')
            ->latest() // Get the latest pending call request
            ->first();

         //   return $callRequest;exit;

        if ($callRequest) {

$initiationTime = $callRequest->created_at->setTimezone('Asia/Kolkata'); // Convert to IST

// Define the timeout duration (e.g., 30 seconds)
$timeoutDuration = 20;

// Calculate the time elapsed since the call initiation
$currentTime = now();
$timeElapsed = $currentTime->diffInSeconds($initiationTime);
   

if ($timeElapsed > $timeoutDuration) {
    // Update the call status to "timed out"
    $callRequest->update(['call_status' => 'timed out']);
    return response()->json(['incomingCall' => false]);
}

            // Calculate the time elapsed since the call initiation
            /*$initiationTime = strtotime($callRequest->created_at);
            $currentTime = time();
            $timeElapsed = $currentTime - $initiationTime;

            // Define the timeout duration (e.g., 30 seconds)
            $timeoutDuration = 30;*/

           /* if ($timeElapsed > $timeoutDuration) {
                // Update the call status to "timed out"
                $callRequest->update(['call_status' => 'timed out']);
                return response()->json(['incomingCall' => false]);
            }*/

            // Return the call request data to the client-side JavaScript
            return response()->json([
                'incomingCall' => true,
                'callData' => $callRequest,
            ]);
        } else {
            return response()->json(['incomingCall' => false]);
        }
    }
    
    public function sendPrivateMessage(Request $request)
    {
       // return $request->all();exit;
        $user           = \Auth::user();
        $params         = $request->all();
        $checkMatchId   = UsersLikes::where('like_from', $user->id)->where('match_id', $params['match_id'])->where('match_status', 'match')->first();

        
        $message              = new UsersPrivateMessages;
        $message->match_id    = $params['match_id'];
        $message->sender_id   = $user->id;
        $message->receiver_id = $checkMatchId->like_to;
        $message->message     = $params['message'];
        $message->read_status = 'Unread';
        $message->like        = 'no';
        $message->save();
        
        $from                 = User::find($checkMatchId->like_to);
        $pushTittle           = $user->first_name .' '.$user->last_name. ' has sent you a message';

        $senderUser         = User::where('id', $message->sender_id)->first();
        $unreadCount        = UsersPrivateMessages::where('receiver_id', $message->sender_id)->where('read_status', 'unread')->count();
        $responsedata = [
            'message'           => $message->message,
            'message_id'        => $message->id,
            'sender_id'         => $message->sender_id,
            'like_status'       => $message->like,
            'like_status'       => $message->like,
            'match_id'       => $message->match_id,
            'sender_user_image' => isset($senderUser->userImages) ? $senderUser->userImages : [],
            'name'              => isset($senderUser->first_name) ? $senderUser->first_name : '',
            'created_at'        => date_format($message->created_at,"Y-m-d H:i:s"),
            'unread_count'      => $unreadCount,
            'type'              => 'message'
        ];

        $pushData = [
            'message' => $responsedata
        ];
        $noticationStatus     = $this->sendPushNotifcation($from->fcm_token,$pushTittle, $params['message'], $from->id, $user->id, $pushData, $unreadCount, 'message');
        
         $device_key = User::where('id',$from->id)->pluck('device_key')->all();
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $request->message, 
                "click_action" => "https://app-backend.foreverusinlove.com/chat",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END

       // return $this->successResponse($responsedata, 'Message send Successfully');
    }
    
    public function reportUser(Request $request)
    {
        
         $user     = \Auth::user();
       /* $messages = array(
            'user_id.required'     => 'User id field is required.',
            'report_id.required_if'   => 'Report id field is required.',
            'type.required'        => 'Type field is required.',
        );*/

      
        $params    = $request->all();
        $checkUser = UsersLikes::where('like_to', $params['user_id'])->where('like_from', $user->id)->first();

        if(!$checkUser)
        {
            $reportDetails = '';
            if (isset($request->report_id)) {
                $reportDetails = ReportsManagement::where('id', $request->report_id)->first();
            }

            
            // Unmatch User report
            $checkUserDetail = UsersLikes::where('like_from', $params['user_id'])->where('like_to', $user->id)->first();
            if(!empty($checkUserDetail))
            {
                $checkUserDetail->match_status = 'unmatch';
                $checkUserDetail->save();
            }
            //UsersLikes::where('match_id',$checkUser->match_id)->delete();

            // delete from private chat
            UserPrivateChat::where(['request_from'=>$params['user_id'],'request_to'=>$user->id,'request_status'=>'accepted'])->delete();
            UserPrivateChat::where(['request_to'=>$params['user_id'],'request_from'=>$user->id,'request_status'=>'accepted'])->delete();

            // delete fro both side view
            UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();
            UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();            

            $userReport = new UsersReport;

            $userReport->user_id       = $params['user_id'];
            $userReport->reporter_id   = $user->id;
            $userReport->report_reason = isset($request->report_id) ? $request->report_id : '';
            $userReport->message       = $params['description'];
            $userReport->type          = $params['type'];

            $userReport->save();
        } else {
            $reportDetails = '';
            if (isset($request->report_id)) {
                $reportDetails = ReportsManagement::where('id', $request->report_id)->first();
            }
           
            UsersLikes::where('match_id',$checkUser->match_id)->update(['match_status'=>'unmatch','match_id'=>0,'like_status'=>'nope','matched_at'=>NULL]);

            // delete from private chat
            UserPrivateChat::where(['request_from'=>$params['user_id'],'request_to'=>$user->id,'request_status'=>'accepted'])->delete();
            UserPrivateChat::where(['request_to'=>$params['user_id'],'request_from'=>$user->id,'request_status'=>'accepted'])->delete();

            // delete from both side view
            UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();
            UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();
            

            UsersMessages::where('match_id', $checkUser->match_id)->delete();

            $usersReport                = new UsersReport();

            $usersReport->user_id       = $params['user_id'];
            $usersReport->reporter_id   = $user->id;
            $usersReport->report_reason = isset($request->report_id) ? $request->report_id : '';
            $usersReport->message       = (!empty($reportDetails)) ? $reportDetails->name : '';
            $usersReport->type          = $params['type'];
            $usersReport->save();
        }

            // send Notification

            $reportedUser = User::where('id',$request->user_id)->first();

            $pushTittle = $user->first_name .' '.$user->last_name.' has reported your profile';
            $message           = $user->first_name .' '.$user->last_name.' has reported your profile';
            
            
            $device_key = User::where('id',$reportedUser->id)->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $message, 
                "click_action" => "https://app-backend.foreverusinlove.com/groups",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END
            
            
            $responsedata = [                
                'type'              => 'users_report',
            ];

            $pushData = [
                'message' => $responsedata
            ];

            if ($reportedUser->fcm_token) {
                $noticationStatus     = $this->sendPushNotifcationComman($reportedUser->fcm_token,$pushTittle, $message, $request->user_id, $pushData);
            }
            $data = [
                'icon'=>asset('images/favicon/apple-touch-icon-152x152.png')
            ];
            $params = [
                'user_id'  => $request->user_id,
                'sender_id'=> $user->id,
                'title'    => $pushTittle,
                'message'  => $message,
                'type'     => 'user report',
                'data'     => json_encode($data),
            ];

            Notifcation::addNotificationHistory($params);
            
            return redirect('/chat');
    }
    
    public function viewChatRoomPage($user_id)
    {
        $user_data = User::where('users.id', $user_id)
        ->join('user_images','user_images.user_id','=','users.id')
        ->select('users.*','user_images.url','users.id AS uid')
        ->first();
        
        $matchid = UsersLikes::where('like_from',\Auth::user()->id)->where('like_to',$user_id)->pluck('match_id')->toArray();
        
        $report_reason = ReportsManagement::where('status','Active')->get();
        
        return view('web.chat_room')->with(['report_reason' => $report_reason,'user_data'=>$user_data,'matchid'=>$matchid]);
    }
    
    public function SendChatRequest(Request $request)
    {
          

        $user      = \Auth::user();

        // Check User Account Plan
         $isOrderActive = Order::where('user_id',\Auth::user()->id)->count();
        
       // if (empty($user->orderActive)) {
       if ($isOrderActive == '0'){
            //return $this->errorResponse([], "Please Upgrade Your Account To Make Private chat");
            return '0';
        }

        // Check User profile match or not
        $checkMeatch = UsersLikes::where(['like_from'=>$user->id,'like_to'=>$request->req_to_id,'match_status'=>'match'])->first();
        if (!empty($checkMeatch)) {
           // return $this->errorResponse([], "User already match with this profile");   
           return '1';
        }
        
        // check is request reacived from this user
        $data = ['request_from'=>$request->req_to_id,'request_to'=>$user->id];
        $checkisreq = UserPrivateChat::where($data)->first();
        if ($checkisreq) {
           // return $this->errorResponse([], "You have already received their request");
           return '2';
        }

        // check if request rejected
        $data = ['request_from'=>$user->id,'request_to'=>$request->req_to_id];
        $checkisrejected = UserPrivateChat::where($data)->first();
        if (!empty($checkisrejected) && $checkisrejected->request_status == 'rejected') {
           // return $this->errorResponse([], "This User already rejected your request");
           return '3';
        }
        

        // Check and save data in to private chat
        $data = [
            'request_from'=>$user->id,
            'request_to'=>$request->req_to_id,
            'request_status'=>'requested',
           // 'invite_msg'=>$request->invite_msg,
        ];

        $check = UserPrivateChat::where($data)->first();
        if (empty($check)) {
            $result = UserPrivateChat::create($data);

            // send Notification

            $requestTo = User::where('id',$request->req_to_id)->first();

            $name = 'Someone';
            if ($user->orderActive) {
                $name = $user->full_name;
            }
            
            $pushTittle = 'Private Chat Request';
            $message    = $name .' has sent you a private chat request';
            
            
             $device_key = User::where('id',$requestTo->id)->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $message, 
                "click_action" => "https://app-backend.foreverusinlove.com/groups",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END
            
            
            
            $responsedata = [                
                'type'              => null,
            ];

            $pushData = [
                'message' => $responsedata
            ];

            if ($requestTo->fcm_token) {
                $noticationStatus     = $this->sendPushNotifcationComman($requestTo->fcm_token,$pushTittle, $message,$request->user_id, $pushData);
            }

            $data = [
                'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                'user_profile'    => $requestTo,
                //'anonymous_profile'=>$requestTo->anonymousProfile
            ];
            $params = [
                'user_id'  => $request->req_to_id,
                'sender_id'=> $user->id,
                'title'    => $pushTittle,
                'message'  => $message,
                'type'     => 'Request To Private chat',
                'data'     => json_encode($data),
            ];

            Notifcation::addNotificationHistory($params);

           // return $this->successResponse($result, 'Success!');
           return '4';
        }
        else{
            //return $this->errorResponse([], "Request Already sent");
            return '5';
        }
    }
    
    
    public function rejectChatRequest(Request $request)
    {

        $user      = \Auth::user();

        $check = UserPrivateChat::with('getRequestFromUser')
            ->where(['request_from'=>$request->request_id,'request_to'=>$user->id,'request_status'=>'requested'])
            ->first();

        if ($check) {
            $check->request_status = 'rejected';
            $check->save();

            // send Notification

            $rejectedUser = User::where('id',$request->request_id)->first();

            $pushTittle = 'Private chat request rejected';
            $message    = $user->first_name .' '.$user->last_name.' has rejected your chat request';
            
            
            $device_key = User::where('id',$rejectedUser->id)->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $message, 
                "click_action" => "https://app-backend.foreverusinlove.com/groups",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END
            
            
            $responsedata = [                
                'type'              => 'private_chat_request_rejected',
            ];

            $pushData = [
                'message' => $responsedata
            ];

            if ($rejectedUser->fcm_token) {
                $noticationStatus     = $this->sendPushNotifcationComman($rejectedUser->fcm_token,$pushTittle, $message,$request->user_id, $pushData);
            }
            $data = [
                'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                'user_profile'    => $rejectedUser,
                'anonymous_profile'=>$rejectedUser->anonymousProfile,
            ];
            $params = [
                'user_id'  => $request->request_id,
                'sender_id'=> $user->id,
                'title'    => $pushTittle,
                'message'  => $message,
                'type'     => 'private_chat_request_rejected',
                'data'     => json_encode($data),
            ];

            Notifcation::addNotificationHistory($params);

        
        }
        
    }
    
    public function acceptChatRequest(Request $request)
    {
       
        $user      = \Auth::user();
        $check = UserPrivateChat::with('getRequestFromUser')
            ->where(['request_from'=>$request->request_id,'request_to'=>$user->id,'request_status'=>'requested'])
            ->first();

        if (!empty($check)) {

            $match_id        = @UsersLikes::max('match_id');

            if ($match_id != 0) {
                $match_id = $match_id + 1;
            } else {
                $match_id = 10001;
            }

            $createFirst = [
                'like_from'    => $user->id,
                'like_to'      => $request->request_id,
                'match_id'     => $match_id,
                'notification' => 'on',
                'plan_status'  => 'paid',
                'like_status'  => 'like',
                'match_status' => 'match',
                 'match_as'     => 'private_request',
                'read_status'  => 'unread',
            ];

            UsersLikes::updateOrCreate([
                'like_from'=>$createFirst['like_from'],
                'like_to'  => $createFirst['like_to'],
            ],$createFirst);

            $createSecond = [
                'like_from'    => $request->request_id,
                'like_to'      => $user->id,
                'match_id'     => $match_id,
                'notification' => 'on',
                'plan_status'  => 'paid',
                'like_status'  => 'like',
                'match_status' => 'match',
                 'match_as'     => 'private_request',
                'read_status'  => 'unread',
            ];

            UsersLikes::updateOrCreate([
                'like_from'=>$createSecond['like_from'],
                'like_to'  => $createSecond['like_to'],
            ],$createSecond);

            $check->request_status = 'accepted';
            $check->save();

            // remove data on match user start
                UserView::where(['user_id'=>$request->request_id,'viewer_id'=>$user->id])->delete();
                UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->request_id])->delete();
            // remove data on match user end

            // send Notification

            $acceptedUser = User::where('id',$request->request_id)->first();

            $pushTittle = 'Private chat request accepted';
            $message    = $user->first_name .' '.$user->last_name.' has accepted your chat request';
            
            
            $device_key = User::where('id',$acceptedUser->id)->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $message, 
                "click_action" => "https://app-backend.foreverusinlove.com/groups",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END
            
            
            $responsedata = [                
                'type'              => 'Private chat request accepted',
            ];

            $pushData = [
                'message' => $responsedata
            ];

            if ($acceptedUser->fcm_token) {
                $noticationStatus     = $this->sendPushNotifcationComman($acceptedUser->fcm_token,$pushTittle, $message,$request->user_id, $pushData);
            }
            $data = [
                'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                'user_profile'    => $acceptedUser,
                'anonymous_profile'=>$acceptedUser->anonymousProfile,
                'match_id'=>$match_id
            ];
            $params = [
                'user_id'  => $request->request_id,
                'sender_id'=> $user->id,
                'title'    => $pushTittle,
                'message'  => $message,
                'type'     => 'Private Chat Request Confirm',
                'data'     => json_encode($data),
            ];

            Notifcation::addNotificationHistory($params);

            //return $this->successResponse($check, 'Success!');
        }
       
    }
    
    public function viewPage()
    {
        
         $status = 'requested';
        
         $user      = \Auth::user();
        
        $sentWhere = ['request_from'=>$user->id];
        $reacivedWhere = ['request_to'=>$user->id];
        
        if (!empty($status))
        {
            if (in_array($status,['requested','accepted','rejected'])) 
            {
                $sentWhere['request_status'] = $status;
                $reacivedWhere['request_status'] = $status;
            }
           
        }
        $pending_req = UserPrivateChat::with(['getRequestToUser','getRequestToUser.userImages','getRequestToUser'])->where($sentWhere)->get();
        $received_req = UserPrivateChat::with(['getRequestFromUser','getRequestFromUser.userImages'])->where($reacivedWhere)->get();
        
       
                    
                 //  print_r($received_req);exit;

        return view('web.chat')->with([/*'accepted_requests' => $accepted_requests,*/'pending_req'=>$pending_req,'received_req'=>$received_req]);
    }
    
    public function acceptedReqList()
    {
        $user = \Auth::user();

         $accepted_requests=array();
        $accepted = DB::select("
                    SELECT * FROM (
                        SELECT
                        IF(u1.id='".$user->id."',u2.id,u1.id) AS user_id,
                        IF(u1.id='".$user->id."',u2.first_name,u1.first_name) AS first_name
                        ,m.*
                        FROM `user_private_chat` m
                        LEFT JOIN `users` u1 ON u1.id=m.request_from
                        LEFT JOIN `users` u2 ON u2.id=m.request_to
                        WHERE (m.request_to='".$user->id."'
                        OR m.request_from='".$user->id."' )
                        AND request_status = 'accepted'
                        )
                        AS tble
                        						
                    "); 
                    
                    
                     foreach($accepted AS $acc)
                       {

                          $msgCount = DB::select("SELECT COUNT(*) AS msg_count FROM `users_private_messages` 
                         WHERE read_status='Unread' AND sender_id='".$acc->user_id."' AND receiver_id='".\Auth::user()->id."' ");
                         
                         $lastMessage = DB::select("SELECT *  FROM `users_private_messages` 
                         WHERE sender_id='".$acc->user_id."' AND receiver_id='".\Auth::user()->id."'ORDER BY id DESC LIMIT 1");
                         
                         $profile_pic = DB::select("SELECT *  FROM `user_images` 
                         WHERE user_id='".$acc->user_id."' ORDER BY id ASC LIMIT 1");

                         $matchid =  UsersPrivateMessages::where(['read_status'=>'Unread','sender_id'=>$acc->user_id,'receiver_id'=>\Auth::user()->id])->pluck('match_id')->first();
                         
                         /*DB::select("SELECT match_id FROM `users_private_messages` 
                         WHERE read_status='Unread' AND sender_id='".$acc->user_id."' AND receiver_id='".\Auth::user()->id."' ORDER BY id DESC LIMIT 1");*/
                         
                         
                         $acc->msg_count = isset($msgCount[0]->msg_count) ? $msgCount[0]->msg_count : 0;
                         $acc->last_msg = $lastMessage;
                         $acc->profile_pic = $profile_pic;
                         $acc->match_id = $matchid;
                        
                         $accepted_requests[] = $acc;
                         
                       }


//print_r($accepted_requests);exit;

                       return view('web.accpted_requests')->with(["accepted_requests" => $accepted_requests]);
	  
    }
    
    public function unmatchUser(Request $request)
    {
         
               $user = \Auth::user();
               
               //part1
                // delete from private chat
            UserPrivateChat::where(['request_from'=>$request->user_id,'request_to'=>$user->id,'request_status'=>'accepted'])->delete();
            UserPrivateChat::where(['request_to'=>$request->user_id,'request_from'=>$user->id,'request_status'=>'accepted'])->delete();
            
			$checkUser = UsersLikes::where('like_to', $request->user_id)->where('like_from', $user->id)->first();

			 UsersLikes::where('match_id',$checkUser->match_id)->update(['match_status'=>'unmatch','match_id'=>0,'like_status'=>'nope','matched_at'=>NULL]);

		
               //end

             /*   UsersLikes::where('match_id',$like->match_id)->update(['match_status' => 'nope','read_status'  => 'unread','like_status'=>'nope']);*/

                // remove from oposite user list
               UsersLikes::where(['like_from'=>$request->user_id,'like_to'=> $user->id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$request->user_id,'viewer_id'=>$user->id])->delete();

               // remove from my like list
               UsersLikes::where(['like_from'=>$user->id,'like_to'=> $request->user_id,'like_status'=>'like'])->delete();
               UserView::where(['user_id'=>$user->id,'viewer_id'=>$request->user_id])->delete();
        

            $model = ReviewLatterProfile::where(['review_by'=>$user->id,'review_to'=>$request->user_id])->first();
            if (!empty($model)) {
                $model->delete();
            }
            
            return '1';
       
    }
    
   
    
   
}