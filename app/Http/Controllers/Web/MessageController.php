<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Lib\RtcTokenBuilder;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use DB;
use Carbon\Carbon;
use App\Models\Notifcation;
use App\Models\Order;
use App\Models\UsersMessages;
use App\Models\UsersLikes;
use App\Models\User;
use App\Models\OnGoingCall;
use App\Models\ReportsManagement;
use App\Models\UsersPrivateMessages;
use Auth;


class MessageController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function readConversation(Request $request)
    {
       // return $request->all();exit;

        $user = Auth::user();
        
         if($request->type != "private_chat")
         {
                $getLastMessage = UsersMessages::where('match_id',$request->match_id)->orderBy('id','desc')->first();         
                 UsersMessages::where('match_id',$request->match_id)->where('receiver_id',$user->id)->update(['read_status'=>'Read']);
         }
         else
         {           
                $getLastMessage = UsersPrivateMessages::where('match_id',$request->match_id)->orderBy('id','desc')->first();         
                 UsersPrivateMessages::where('match_id',$request->match_id)->where('receiver_id',$user->id)->update(['read_status'=>'Read']);
         }
    }
    
    public function matchedProfileList()
    {
         //match details
        $user            = \Auth::user();
        $conversation    = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->orderBy('created_at', 'DESC')->get();
        $allLikes        = UsersLikes::where('like_to', $user->id)->where('match_status', 'match')->whereNull('match_as')->orderBy('match_id','DESC')->get();
      
      
        $allConversation = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->get();
        $msgId = [];
        foreach ($allConversation as $key => $msg) // get users message id
        {
            $msgId[$key] = $msg->receiver_id;
        }

        $allId = [];
        foreach ($allLikes as $key => $likes) // get all like id
        {
            $allId[$key] = $likes->like_from;
        }
       
        $noMsgId = array_diff($allId, $msgId);
       
        $noConversation = [];
        
        $i = 0;
        foreach ($noMsgId as $key => $value) {
            // get user details who has not started conversation yet.
            $usersLikes = UsersLikes::where('like_from', $user->id)->where('like_to', $value)->whereIn('like_status', ['like','super_like'])->where('match_status', 'match')->first();
            $checkReceive = UsersMessages::where('sender_id', $value)->where('receiver_id', $user->id)->first();
            if (!empty($usersLikes) && !$checkReceive) {
                $noConversation[$i] = User::where('id', $value)->with(['userKids','userSettings'])->get();
                $i++;
            }
        }

        $newMatchCount               = 0;
        $conversationNotStartedArray = [];
        if(!empty($noConversation)) {
            $k = 0;
            foreach ($noConversation as $key => $value) {
                $users_likes = UsersLikes::where('like_from', $user->id)->where('like_to', $value[0]->id)->whereIn('like_status', ['like','super_like'])->where('match_status', 'match')->first();

                $conversationNotStartedArray[$k]['user_id']      = @$value[0]->id;
                $conversationNotStartedArray[$k]['user_name']    = @$value[0]->first_name;
                $conversationNotStartedArray[$k]['lastseen']    = @$value[0]->lastseen;
                $conversationNotStartedArray[$k]['image']       = @$value[0]->userImages;
                $conversationNotStartedArray[$k]['read_status']  = @$users_likes->read_status;
                $conversationNotStartedArray[$k]['like_status']  = @$users_likes->like_status;
                $conversationNotStartedArray[$k]['match_id']     = @$users_likes->match_id;
                $createdDate                                        = (string)$users_likes->created_at;
                $conversationNotStartedArray[$k]['created_date'] = $createdDate;

                if ($users_likes->read_status == 'unread') {
                    $newMatchCount++;
                }

                $k++;
            }
        }

        //end
        
        //msg conversation
        $user            = \Auth::user();
        
        $matchId=  DB::select("
                    SELECT cht.*          
                    FROM user_likes cht                    	
                    WHERE ((cht.like_to = '".$user->id."') 								
                    OR 
                    (cht.like_from = '".$user->id."'  ))  AND  cht.match_status = 'match'	ORDER BY cht.match_id DESC													
                    "); 


        $matchIds = [];
        if($matchId) {
            foreach ($matchId as $key => $value) {
                $matchIds[] = $value->match_id;
            }
        }

        $conversation    = UsersMessages::whereIn('match_id', $matchIds)->groupBy('match_id')->orderBy('created_at', 'DESC')->get();

        $allLikes        = UsersLikes::where('like_to', $user->id)->where('match_status', 'match')->orderBy('match_id','DESC')->get();
        $allConversation = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->get();
        $msgId = [];
        foreach ($allConversation as $key => $msg) // get users message id
        {
            $msgId[$key] = $msg->receiver_id;
        }

        $allId = [];
        foreach ($allLikes as $key => $likes) // get all like id
        {
            $allId[$key] = $likes->like_from;
        }

        $noMsgId                  = array_diff($allId, $msgId);
        $k                        = 0;
        $conversationStartedArray = [];
        $userId                   = $user->id;

        foreach ($conversation as $key => $value) {
            $usersLikes = UsersLikes::where('match_id', $value->match_id)->first();
            if (!empty($usersLikes) && $usersLikes->match_status == "match") {
                $lastMessage = UsersMessages::where('match_id', $value->match_id)->orderBy('id', 'desc')->get()->first();
                $unreadmsgCount = UsersMessages::where('match_id', $value->match_id)->where('read_status','Unread')->where('sender_id' ,'!=',$userId)->count();
                if($lastMessage->sender_id == $user->id) {
                    $matchUser = User::find($lastMessage->receiver_id);
                } else {
                    $matchUser = User::find($lastMessage->sender_id);
                }
            
                $conversationStartedArray[$k]['user_id'] = $matchUser->id;
                $conversationStartedArray[$k]['lastseen'] = $matchUser->lastseen;
                $conversationStartedArray[$k]['user_name'] = $matchUser->first_name;
                $conversationStartedArray[$k]['sender_id'] = $lastMessage->sender_id;
                $conversationStartedArray[$k]['user_image_url'] = $matchUser->userImages;
                $conversationStartedArray[$k]['message'] = @$lastMessage->message;
                $conversationStartedArray[$k]['unread_message_count'] = $unreadmsgCount;
                $conversationStartedArray[$k]['read_status']  = $lastMessage->read_status;
                $conversationStartedArray[$k]['like_status']  = @$usersLikes->like_status;
                $conversationStartedArray[$k]['match_id']     = @$value->match_id;
                $createdDate                                  = $lastMessage->created_at;
                $conversationStartedArray[$k]['created_at']   = $createdDate;
                $conversationStartedArray[$k]['created_at']   = $createdDate;
                $k++;
            }

        }

        //end
        
        
        return view('web.matched_profile_list')->with(['conversationStartedArray' => $conversationStartedArray]);   
    }
    
    public function sendMessage(Request $request)
    {
       /*return $request->all();exit;*/
        $user           = \Auth::user();
        $params         = $request->all();
        $checkMatchId   = UsersLikes::where('like_from', $user->id)->where('match_id', $params['match_id'])->where('match_status', 'match')->first();
        

        $message              = new UsersMessages;
        $message->match_id    = $params['match_id'];
        $message->sender_id   = $user->id;
        $message->receiver_id = $checkMatchId->like_to;
        $message->message     = $params['message'];
        $message->read_status = 'Unread';
        $message->like        = 'no';
        $message->save();

        $from                 = User::find($checkMatchId->like_to);
        $pushTittle           = $user->first_name .' '.$user->last_name. ' has sent you a message';
        
        
         $device_key = User::where('id',$from->id)->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $request->message, 
                "click_action" => "https://app-backend.foreverusinlove.com/messages",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END
        
        

        $senderUser         = User::where('id', $message->sender_id)->first();
        $unreadCount        = UsersMessages::where('receiver_id', $message->sender_id)->where('read_status', 'unread')->count();
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

    }
    
    public function videoCall($user_id,$channel_name)
    {
        $user_data = User::where('users.id', $user_id)
        ->join('user_images','user_images.user_id','=','users.id')
        ->select('users.*','user_images.url','users.id AS uid')
        ->first();
        
        $receiver_token = OnGoingCall::where('channel_name',$channel_name)->pluck('reaciver_token')->toArray();
        //print_r($receiver_token);exit;
        
        return view('web.single_video_call')->with(['user_data'=>$user_data,'channel_name'=>$channel_name,'receiver_token'=>$receiver_token[0]]);
    }
    
    public function initiateVideoCall(Request $request)
    {
        $user = \Auth::user();

        $appID = '1e52f180e8564424b2a0ef840d0a8015';
        $appCertificate = '704835b0b7d34899870757100deb6c45';
        
     
        $channelName = $this->generateRandomChannel(8);
        $userId = $this->generateRandomUid();
        $role = RtcTokenBuilder::RoleAttendee;

        $expireTimeInSeconds = 3600;
        $currentTimestamp = now()->getTimestamp();
        $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

        $rtcToken1 = RtcTokenBuilder::buildTokenWithUserAccount($appID, $appCertificate, $channelName, 0, $role, $privilegeExpiredTs);
        
         


        if ($request->status == '1') {
          //  echo $userId;exit;
            $data = [
                'sender_user_id'=>$user->id,
                'reaciver_u_id' =>$userId,
                'channel_name'=> $channelName,
                'reaciver_token'=>$rtcToken1,
                'status'=>$request->status,
            ];    
        }else if ($request->status == '0') {
            $getExist = OnGoingCall::where(['sender_user_id'=>$user->id,'reaciver_user_id'=>$request->user_id])->first();
            $data = [
                'reaciver_user_id'=>$request->user_id,
                'sender_u_id'=>$userId,
                'channel_name'=> $getExist->channel_name,
                'sender_token'=>$rtcToken1,
                'status'=>$request->status,
            ];
        }

        $createData = OnGoingCall::updateOrCreate(['sender_user_id'=>$user->id,'reaciver_user_id'=>$request->user_id],$data);
        
         //WEB PUSH NOTIFICATION
            
                $pushTittle = $user->first_name.' is calling You';
                  $device_key = User::where('id',[$request->user_id])->pluck('device_key')->all();
                       $web_noti_data = [
                    "registration_ids" => $device_key,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => 'test', 
                        "click_action" => "https://app-backend.foreverusinlove.com/video_call/".$user->id.'/'.$createData->channel_name,
                        "icon" => "https://app-backend.foreverusinlove.com/public/images/logo/cropped-favicon.webp",
                    ]
                ];
                
                //print_r($web_noti_data);exit;
                $this->sendWebNotification($web_noti_data);
            //END
        
        // send notification
        if ($request->status == '1') {
            $getUser = User::where('id',$request->user_id)->first();
            $user_data  = $request->user();
            
            
            //print_r($user_data->userImages->toA);exit;
            
            if ($getUser->fcm_token) {

                $pushTittle = $user_data['first_name'].' is calling You';
               
                $message = '';
                $responsedata = [
                    'receiver_u_id'   => $createData->reaciver_u_id,
                    'channel_name'  => isset($createData->channel_name) ? $createData->channel_name : '',
                    'created_at'    => date_format($createData->created_at,"Y-m-d H:i:s"),
                     'channel_name'=> $channelName,
                    'reaciver_token'=>$rtcToken1,
                     'user_image' => $user_data->userImages->toArray(),
                     'user_name' => $user_data['full_name'].' '.$user_data['last_name'],
                     'user_id' => $user_data['id'],
                
                ];
                $pushData = [
                    'message' => $responsedata
                ];

                $this->sendPushNotifcationComman($getUser->fcm_token,$pushTittle,$message,$getUser->id,$pushData);
                
                 

                $data = [
                    'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                    'receiver_u_id'   =>  $createData->reaciver_u_id,
                    'channel_name'  =>  $createData->channel_name,
                    'receiver_token'     =>  $createData->receiver_token,
                    
                ];
                
                

                $params = [
                    'user_id'  => $getUser->id,
                    'sender_id'=> $user->id,
                    'title'    => $pushTittle,
                    'message'  => $message,
                    'type'     => 'single_video_call',
                    'data'     => json_encode($data),
                ];

                Notifcation::addNotificationHistory($params);
            }
           
           
        }

        $name_of_channel = $createData->channel_name;
        $token = $createData->reaciver_token;
         return json_encode(array("name_of_channel"=>$name_of_channel,"token"=>$token));
        //return redirect('/video_call/'.$createData->channel_name);
        //return $this->successResponse($createData, 'Success');
    }
    
    public function viewMessagePage()
    {
        //match details
        $user            = \Auth::user();
        $conversation    = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->orderBy('created_at', 'DESC')->get();
        $allLikes        = UsersLikes::where('like_to', $user->id)->where('match_status', 'match')->whereNull('match_as')->orderBy('match_id','DESC')->get();
      
      
        $allConversation = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->get();
        $msgId = [];
        foreach ($allConversation as $key => $msg) // get users message id
        {
            $msgId[$key] = $msg->receiver_id;
        }

        $allId = [];
        foreach ($allLikes as $key => $likes) // get all like id
        {
            $allId[$key] = $likes->like_from;
        }
       
        $noMsgId = array_diff($allId, $msgId); 
        $noConversation = [];
        
        $i = 0;
        foreach ($noMsgId as $key => $value) {
            // get user details who has not started conversation yet.
            $usersLikes = UsersLikes::where('like_from', $user->id)->where('like_to', $value)->whereIn('like_status', ['like','super_like'])->where('match_status', 'match')->first();
            $checkReceive = UsersMessages::where('sender_id', $value)->where('receiver_id', $user->id)->first(); 
            if (!empty($usersLikes) && !$checkReceive) {
                $noConversation[$i] = User::where('id', $value)->with(['userKids','userSettings'])->get();
                $i++;
            }
        }

        $newMatchCount               = 0;
        $conversationNotStartedArray = [];
        if(!empty($noConversation)) {
            $k = 0;
            foreach ($noConversation as $key => $value) {
                $users_likes = UsersLikes::where('like_from', $user->id)->where('like_to', $value[0]->id)->whereIn('like_status', ['like','super_like'])->where('match_status', 'match')->first();
       

                $conversationNotStartedArray[$k]['user_id']      = @$value[0]->id;
                $conversationNotStartedArray[$k]['user_name']    = @$value[0]->first_name;
                $conversationNotStartedArray[$k]['lastseen']    = @$value[0]->lastseen;
                $conversationNotStartedArray[$k]['image']       = @$value[0]->userImages;
                $conversationNotStartedArray[$k]['read_status']  = @$users_likes->read_status;
                $conversationNotStartedArray[$k]['like_status']  = @$users_likes->like_status;
                $conversationNotStartedArray[$k]['match_id']     = @$users_likes->match_id;
                $createdDate                                        = (string)$users_likes->created_at;
                $conversationNotStartedArray[$k]['created_date'] = $createdDate;

                if ($users_likes->read_status == 'unread') {
                    $newMatchCount++;
                }

                $k++;
            }
        }
        //end
        
        //msg conversation
        $user            = \Auth::user();
        
        $matchId=  DB::select("
                    SELECT cht.*          
                    FROM user_likes cht                    	
                    WHERE ((cht.like_to = '".$user->id."') 								
                    OR 
                    (cht.like_from = '".$user->id."'  ))  AND  cht.match_status = 'match'	ORDER BY cht.match_id DESC													
                    "); 


        $matchIds = [];
        if($matchId) {
            foreach ($matchId as $key => $value) {
                $matchIds[] = $value->match_id;
            }
        }

        $conversation    = UsersMessages::whereIn('match_id', $matchIds)->groupBy('match_id')->orderBy('created_at', 'DESC')->get();

        $allLikes        = UsersLikes::where('like_to', $user->id)->where('match_status', 'match')->orderBy('match_id','DESC')->get();
        $allConversation = UsersMessages::where('sender_id', $user->id)->groupBy('receiver_id')->get();
        $msgId = [];
        foreach ($allConversation as $key => $msg) // get users message id
        {
            $msgId[$key] = $msg->receiver_id;
        }

        $allId = [];
        foreach ($allLikes as $key => $likes) // get all like id
        {
            $allId[$key] = $likes->like_from;
        }

        $noMsgId                  = array_diff($allId, $msgId);
        $k                        = 0;
        $conversationStartedArray = [];
        $userId                   = $user->id;

        foreach ($conversation as $key => $value) {
            $usersLikes = UsersLikes::where('match_id', $value->match_id)->first();
            if (!empty($usersLikes) && $usersLikes->match_status == "match") {
                $lastMessage = UsersMessages::where('match_id', $value->match_id)->orderBy('id', 'desc')->get()->first();
                $unreadmsgCount = UsersMessages::where('match_id', $value->match_id)->where('read_status','Unread')->where('sender_id' ,'!=',$userId)->count();
                if($lastMessage->sender_id == $user->id) {
                    $matchUser = User::find($lastMessage->receiver_id);
                } else {
                    $matchUser = User::find($lastMessage->sender_id);
                }
            
                $conversationStartedArray[$k]['user_id'] = $matchUser->id;
                $conversationStartedArray[$k]['lastseen'] = $matchUser->lastseen;
                $conversationStartedArray[$k]['user_name'] = $matchUser->first_name;
                $conversationStartedArray[$k]['sender_id'] = $lastMessage->sender_id;
                $conversationStartedArray[$k]['user_image_url'] = $matchUser->userImages;
                $conversationStartedArray[$k]['message'] = @$lastMessage->message;
                $conversationStartedArray[$k]['unread_message_count'] = $unreadmsgCount;
                $conversationStartedArray[$k]['read_status']  = $lastMessage->read_status;
                $conversationStartedArray[$k]['like_status']  = @$usersLikes->like_status;
                $conversationStartedArray[$k]['match_id']     = @$value->match_id;
                $createdDate                                  = $lastMessage->created_at;
                $conversationStartedArray[$k]['created_at']   = $createdDate;
                $conversationStartedArray[$k]['created_at']   = $createdDate;
                $k++;
            }

        }

        //end
        
        
        return view('web.messages')->with(['conversationStartedArray' => $conversationStartedArray,'conversationNotStartedArray'=>$conversationNotStartedArray]);
    }
    
    public function viewMessageRoomPage($user_id)
    {
        $matchid = UsersLikes::where('like_from',\Auth::user()->id)->where('like_to',$user_id)->pluck('match_id')->toArray();
        $report_reason = ReportsManagement::where('status','Active')->get();
        $user_data = User::where('users.id', $user_id)->join('user_images','user_images.user_id','=','users.id')->select('users.*','user_images.url','users.id AS uid')->first();
        //print_r($user_data);exit;
        return view('web.message_room')->with(['report_reason'=>$report_reason,'user_data' => $user_data,'matchid'=>$matchid]);
    }
    
     public function generateRandomChannel($length = 8) {
        $characters = '0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }

    public function generateRandomUid($length = 9) {
        $characters = '0123456789';
        $charactersLength = strlen($characters);
        $randomString = '';
        for ($i = 0; $i < $length; $i++) {
            $randomString .= $characters[rand(0, $charactersLength - 1)];
        }
        return $randomString;
    }
    
   
   
}