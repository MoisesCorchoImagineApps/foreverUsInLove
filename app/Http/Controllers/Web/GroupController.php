<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RoomJoinMember;
use App\Models\Room;
use App\Models\Order;
use App\Models\UserImages;
use App\Lib\RtcTokenBuilder;
use Auth;
use App\Models\RequestedRoomJoinMember;
use App\Models\OnGoingGroupCall;
use App\Models\Notifcation;
use DB;
use App\Models\GroupCallRequests;



class GroupController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }

    public function checkIncomingGroupCalls(Request $request) {
     //   $recipientUserId = $request->input('receiver_id');
   // return $request->room_id;exit;
        // Check for pending group call requests for the recipient

      $userId = Auth::user()->id;

    /*$groupCallRequest = GroupCallRequests::join('room', 'room.room_id', '=', 'group_call_requests.room_id')
        ->select('group_call_requests.*', 'room.room_id', 'room.room_name')
        ->where('call_status', 'pending')
        ->where('group_call_requests.room_id', $request->room_id)
        ->where('group_call_requests.sender_id','!=',Auth::user()->id)
        ->whereRaw('JSON_CONTAINS(members, \'{"user_id": ' . $userId . '}\')')
        ->latest()
        ->get();*/

        $groupCallRequest = GroupCallRequests::join('room', 'room.room_id', '=', 'group_call_requests.room_id')
        ->join('on_going_group_call','on_going_group_call.room_id','=','room.room_id')
        ->select('group_call_requests.*', 'room.room_id', 'room.room_name','on_going_group_call.channel_name')
        ->where('call_status', 'pending')
        ->where('group_call_requests.room_id', $request->room_id)
        ->where('group_call_requests.sender_id','!=',Auth::user()->id)
        ->whereRaw('JSON_CONTAINS(members, \'{"user_id": ' . $userId . '}\')')
        ->latest()
        ->get();
    
    
        //if ($groupCallRequest != NULL) {
            if ($groupCallRequest->isNotEmpty()) {
            $initiationTime = $groupCallRequest[0]->created_at->setTimezone('Asia/Kolkata'); // Convert to IST

            // Define the timeout duration (e.g., 30 seconds)
            $timeoutDuration = 10;
            
            // Calculate the time elapsed since the call initiation
            $currentTime = now();
            $timeElapsed = $currentTime->diffInSeconds($initiationTime);
    
            if ($timeElapsed > $timeoutDuration) {
                // Update the call status to "timed out"
                //$groupCallRequest->update(['call_status' => 'timed out']);

                GroupCallRequests::where('call_status', 'pending')
                ->where('group_call_requests.room_id', $request->room_id)
                ->whereRaw('JSON_CONTAINS(members, \'{"user_id": ' . $userId . '}\')')->update(['call_status'=> 'timed out']);

                return response()->json(['incomingCall' => false]);
            }
    
            // Return the group call request data to the client-side JavaScript
            return response()->json([
                'incomingCall' => true,
                'callData' => $groupCallRequest,
               // 'recipients' => $recipients,
            ]);
        } else {
            return response()->json(['incomingCall' => false]);
        }
    }
    

    public function initiateGroupCall(Request $request) {
        $room = Room::find($request->input('room_id'));
        
        if (!$room) {
            return response()->json(['message' => 'Room not found'], 404);
        }
        
        // Extract the user IDs of members in the room
        //$memberIds = $room->members->pluck('user_id')->toArray();
           // Call the getRoomMembers method to retrieve members for the specified room
           $membersData = DB::table('room')
           ->where('room.room_id', $request->room_id)
           ->join('room_join_member', 'room.room_id', '=', 'room_join_member.room_id')
           ->select('room_join_member.user_id as user_id')
           ->get();
        
        // Insert a record into the group_call_requests table
        GroupCallRequests::create([
            'sender_id' => Auth::user()->id, // Use the authenticated user's ID
            'room_id' => $room->room_id,
            'call_status' => 'pending',
            'members' => json_encode($membersData), // Store the member IDs in JSON format
        ]);
        
        // You can return a response or perform additional actions as needed
        return response()->json(['message' => 'Group call initiated']);
    }
    
    
    public function groupVideoCall($room_id,$channel_name)
    {
         $is_room_valid = RoomJoinMember::where('user_id', \Auth::user()->id)->where('room_id',$room_id)->count();
        
        if($is_room_valid == '0')
        {
              $room_details = '0';
              $room_join_members = '0';
        }
        else
        {
              $room_details = Room::where('room_id',$room_id)->first();
              $room_join_members =  RoomJoinMember::where("room_join_member.room_id",$room_id)
                       ->select('users.*','room_join_member.*','room_join_member.user_id AS member_id','user_images.url')
                       ->leftjoin('users','users.id','=','room_join_member.user_id')
                       ->leftjoin('user_images','user_images.user_id','=','users.id')
                       ->groupBy('room_join_member.user_id')
                       ->get();
                       
            
                       
        }
        
        
        $token = OnGoingGroupCall::where('channel_name',$channel_name)->pluck('token')->toArray();
        //print_r($receiver_token);exit;
        
        //return view('web.single_video_call')->with(['user_data'=>$user_data,'channel_name'=>$channel_name,'receiver_token'=>$receiver_token[0]]);
       // print_r($room_details);exit;
        
        return view('web.group_video_call')->with(['room_join_members'=>$room_join_members,'room_details'=>$room_details,'token'=>$token[0],'channel_name'=>$channel_name]);
        
        
        
    }
    
    public function initiateGroupVideoCall(Request $request)
    {
        $appID = '1e52f180e8564424b2a0ef840d0a8015';
        $appCertificate = '704835b0b7d34899870757100deb6c45';
        
        $room = Room::where('room_id',$request->room_id)->first();
     
        $checkOngoingCall = OnGoingGroupCall::where('room_id',$request->room_id)->count();
        if ($checkOngoingCall) 
        {
            //return $this->errorResponse([], 'call already ongoing');   
            return '0';
        }

        $channelName = $this->generateRandomChannel(8);

        $roomJoinedUser = $room->roomJoinMember->count();

        for ($x = 1; $x <= $roomJoinedUser; $x++) {
            $userId = $this->generateRandomUid();
            $role = RtcTokenBuilder::RoleAttendee;
            $expireTimeInSeconds = 3600;
            $currentTimestamp = now()->getTimestamp();
            $privilegeExpiredTs = $currentTimestamp + $expireTimeInSeconds;

            $rtcToken = RtcTokenBuilder::buildTokenWithUserAccount($appID, $appCertificate, $channelName, 0, $role, $privilegeExpiredTs);

            $obj = new OnGoingGroupCall();
            $obj->room_id       = $request->room_id;
            $obj->channel_name  = $channelName;
            $obj->u_id          = $userId;
            $obj->token         = $rtcToken;
            $obj->save();
        }
        

        //send notification
        $user = $request->user();
        $pushTittle         = $user->first_name .' '.$user->last_name. ' has sent video call request';
        
        $room->channel_name = $channelName;
        $room->call_request_status = 1;
        $room->save();
        
        $responsedata = [
            'room_id'        => isset($room->room_id) ? $room->room_id : '',
            'channel_name'        => isset($room->channel_name) ? $room->channel_name : '',
            'room_image'        => isset($room->room_icon) ? $room->room_icon : '',
            'room_name'              => isset($room->room_name) ? $room->room_name : '',
            'created_at'        => date_format($room->created_at,"Y-m-d H:i:s"),
            'total_users'      => $room->total_users,
            'token' => $rtcToken,
            'uid' => $userId,
        ];

        $pushData = [
            'message' => $responsedata
        ];

        $message           = 'Hi '.$user->first_name .' '.$user->last_name. ' request to Group call in '.$room->channel_name;

        if (!empty($room->roomJoinMember)) {

            foreach ($room->roomJoinMember as $key => $member) {
            $getUser = $member->user;    
                 //WEB PUSH NOTIFICATION
            
               // $pushTittle = $user->first_name.' is calling You';
                  $device_key = User::where('id',[$getUser->id])->pluck('device_key')->all();
                       $web_noti_data = [
                    "registration_ids" => $device_key,
                    "notification" => [
                        "title" => $pushTittle,
                        "body" => $message, 
                        "click_action" => "https://app-backend.foreverusinlove.com/group_video_call/".$request->room_id.'/'.$channelName,
                        "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
                    ]
                ];
                
                //print_r($web_noti_data);exit;
                $this->sendWebNotification($web_noti_data);
            //END
                
                
                
               // $getUser = $member->user;
                if ($user->id == $getUser->id) {
                    continue;
                }
                if (!empty($getUser->fcm_token)) {
                    $this->sendPushNotifcationComman($getUser->fcm_token,$pushTittle,$message,$getUser->id,$pushData);
                }
                $data = [
                    'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                    'room_name'=>$room->room_name,
                    'channel_name'=>$room->channel_name,
                    'room_icon'=>$room->room_icon,
                    'total_users'=>$room->total_users
                ];

                $params = [
                    'user_id'  => $getUser->id,
                    'sender_id'=> $user->id,
                    'title'    => $pushTittle,
                    'message'  => $message,
                    'type'     => 'Group Call Request',
                    'data'     => json_encode($data),
                ];

                Notifcation::addNotificationHistory($params);
            }
        }
        
        /*$name_of_channel = $createData->channel_name;
        $token = $createData->reaciver_token;*/
         return json_encode(array("channelName"=>$channelName,"rtcToken"=>$rtcToken));
        
        
        //return $this->successResponse([], 'Success');
    }
    
    public function ViewGroupChatPage($id)
    {
        $user_img = UserImages::where('user_id',\Auth::user()->id)->pluck('url')->toArray();
        $is_room_valid = RoomJoinMember::where('user_id', \Auth::user()->id)->where('room_id',$id)->count();
        
        if($is_room_valid == '0')
        {
              $room_details = '0';
              $room_join_members = '0';
        }
        else
        {
              $room_details = Room::where('room_id',$id)->first();
              $room_join_members =  RoomJoinMember::where("room_join_member.room_id",$id)
                       ->select('users.*','room_join_member.*','room_join_member.user_id AS member_id','user_images.url')
                       ->leftjoin('users','users.id','=','room_join_member.user_id')
                       ->leftjoin('user_images','user_images.user_id','=','users.id')
                       ->groupBy('room_join_member.user_id')
                       ->get();
                       
            
                       
        }
        
        return view('web.group_chat')->with(['user_img'=>$user_img,'room_join_members'=> $room_join_members,'room_details' => $room_details]);
    }
    
    public function index()
    {
        
        $isOrderActive = Order::where('user_id',\Auth::user()->id)->count();

        
              
        if ($isOrderActive == '0')
        {
              $joined_room = '0';
              $available_rooms ='0';
              $requested_room = '0';
              //$who_likes_me = '0';
        }
        else
        {

            //print_r('abd');exit;
             //JOINED GROUPS
                
                   $result = RoomJoinMember::with('user','getRoom')->where('user_id', \Auth::user()->id)->get();
          

 
                    if(isset($result) && $result->count() != 0) 
                    {
                        
                        $result     = $result->toArray();
                        $result     = array_column($result, 'room_id');
                        $joined_room  = Room::whereIn('room_id', $result)/*->where('status','active')*/->get();
                        
                        if($joined_room->count() == 0 )
                        {
                             $joined_room = '1';
                        }
                    }
                    else
                    {
                        $joined_room = '1';
                    }
        
                //END
                
                //AVAILABLE ROOMS
                               
                        
                        $room    = Room::where('status', 'Active')->get();
                       // print_r($room);exit;
                        $available_rooms   = [];
                        if(!empty($room)) {
                            foreach ($room as $key => $r)
                            {
                                            $checkJoin = RoomJoinMember::where('user_id', \Auth::user()->id)->where('room_id', $r->room_id)->first();
                                            if($checkJoin) {
                                                continue;
                                            }
                            
                                            $checkRequested = RequestedRoomJoinMember::where('user_id', \Auth::user()->id)->where('room_id', $r->room_id)->first();
                                            if($checkRequested) {
                                                continue;
                                            }
                            
                                            $available_rooms[] = $r;
                                
                            }
                        }
                        
                      //  print_r($available_rooms);exit;
                
                //END
                
                //REQUESTED ROOMS
                                  
                        $result = RequestedRoomJoinMember::where('user_id', \Auth::user()->id)->get();
                        //print_r($result);
                        
                         if(isset($result) && $result->count() != 0) 
                        {
                            $result     = $result->toArray();
                            $result     = array_column($result, 'room_id');
                            $requested_room      = Room::whereIn('room_id', $result)->get();
                
                                if($requested_room->count() == 0 )
                                {
                                     $requested_room = '0';
                                }
                        }
                        else
                        {
                             $requested_room = '1';
                        }
                       

                //END
                
        
        }       
        
       // print_r($requested_room);exit;

        return view('web.groups')->with(['joined_room'=>$joined_room,'available_rooms'=>$available_rooms,'requested_room'=>$requested_room]);
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