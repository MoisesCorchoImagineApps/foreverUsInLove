<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\RoomJoinMember;
use App\Models\Room;
use App\Models\Order;
use DB;
use Auth;
use App\Models\RequestedRoomJoinMember;


class RoomController extends Controller
{
       /* const ROOM_LIMIT = 5;*/

     public function __construct()
    {
        $this->middleware('auth:user');
    }
    
    public function leaveRoom(Request $request)
    {
        $user = \Auth::user();
        $avail_room = Room::where('room_id',$request->room_id)->count();
        
        
        if($avail_room == 1)
        {
            RoomJoinMember::where('room_id',$request->room_id)->where('user_id',$user->id)->delete();
            
            //Add first user of queue into the room and notfiy that user.
            $current_room_count = RoomJoinMember::where('room_id',$request->room_id)->count();
            //print_r($current_room_count);exit;
           // if($current_room_count <  5)
           if($current_room_count <  20)
            {
                 // print_r('j');exit;
                $first_user = RequestedRoomJoinMember::where('room_id',$request->room_id)->pluck('user_id')->first();//get 1st user from queue
                
                //print_r($first_user);
                //delete it from queue.
               RequestedRoomJoinMember::where('room_id',$request->room_id)->where('user_id',$first_user)->delete();
                
                //add that new user into group & notify.
                
                
               $room_data['room_id'] = $request->room_id;
              $room_data['user_id'] = $first_user;
              
              if($first_user != NULL)
              {
              

              DB::table('room_join_member')->insert($room_data);
              
              }
              
                
                //NOTIFY
                
            $room_data = Room::where('room_id',$request->room_id)->first();
            $first_user_data = User::where('id',$first_user)->first();
            
           // print_r($room_data['room_name']);exit;
                
            $pushTittle         = 'Your are added to '.$room_data['room_name'].'  group.';
        
        $responsedata = [
            'room_id'        => isset($room_data['room_id']) ? $room_data['room_id'] : '',
            'channel_name'        => isset($room_data['channel_name']) ? $room_data['channel_name'] : '',
            'room_image'        => isset($room_data['room_icon']) ? $room_data['room_icon'] : '',
            'room_name'              => isset($room_data['room_name']) ? $room_data['room_name'] : '',
            'created_at'        => date_format($room_data['created_at'],"Y-m-d H:i:s"),
            //'total_users'      => $getRoom->total_users,
        ];

        $pushData = [
            'message' => $responsedata
        ];


 
        if($first_user_data != NULL)
        {
           // print_r($first_user_data['id']);exit;
           // print_r('test');exit;
            
            $message           = 'Hi '.$first_user_data['first_name'] .' '.$first_user_data['last_name']. ' you are added into '.$room_data['room_name'];
          // $device_key = $first_user_data['device_key'] ;
           
           //print_r($first_user_data['user_id']);exit;
                   $device_key = User::where('id',$first_user_data['id'])->pluck('device_key')->all();

         //  print_r(array($device_key));exit;
           
            //WEB PUSH NOTIFICATION
               $web_noti_data = [
            "registration_ids" => $device_key,
            "notification" => [
                "title" => $pushTittle,
                "body" => $message, 
                "click_action" => "https://gurutechnolabs.co.in/website/laravel/foreverus_in_love/groups",
                "icon" => "https://fastly.picsum.photos/id/1037/200/300.jpg?hmac=4eugJlD2Tm8e9ZZIDrSXnYPKNyVQ9Mm58HkQrLYBy1c",
            ]
        ];
        
        $this->sendWebNotification($web_noti_data);
            //END

//exit;
        //$message           = 'Hi '.$first_user_data['first_name'] .' '.$first_user_data['last_name']. ' you are added into '.$room_data['room_name'];

                if (!empty($first_user_data['fcm_token'])) {
                    $this->sendPushNotifcationComman($first_user_data['fcm_token'],$pushTittle,$message,$first_user_data['id'],$pushData);

                    $data = [
                        'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),
                        'room_name'=>$room_data['room_name'],
                        'channel_name'=>$room_data['channel_name'],
                        'room_icon'=>$room_data['room_icon']
                    ];
                    $params = [
                        'user_id'  =>$first_user_data['id'],
                        'sender_id'=> $first_user_data['id'],
                        'title'    => $pushTittle,
                        'message'  => $message,
                        'type'     => 'Room Joined',
                        'data'     => json_encode($data),
                    ];

                    Notifcation::addNotificationHistory($params);
                }
            }
          
                //END
                
                     //return $this->successResponse([], 'Success');
                
            }
          
        }
        /*else
        {
            return $this->errorResponse([], 'Invalid Room Id');    
        }*/
    }
    
    public function joinRoom(Request $request)
    {
        
        $user               = Auth::user();

        $params             = $request->all();

        $checkAlreadyRequested   = RequestedRoomJoinMember::where('room_id', $request->room_id)->where('user_id', $user->id)->first();
        
        if($checkAlreadyRequested) {
             return '0';
        }
        

        $checkAlreadyJoin   = RoomJoinMember::where('room_id', $request->room_id)->where('user_id', $user->id)->first();
        
        
        
        if ($checkAlreadyJoin) {
           return '1';
        }
        
        else
        {
            $params['user_id']  = $user->id;
            $countRoomMember = RoomJoinMember::where('room_id', $request->room_id)->count();
            
           // if ($countRoomMember >= 5) {
            if ($countRoomMember >= 20) {
                $result      = RequestedRoomJoinMember::addUpdateRoomRequestedMember($params);
                return '2';
            }
            $result = RoomJoinMember::addUpdateRoomJoinMember($params);
            return '3';
        }
    }
    
   
}