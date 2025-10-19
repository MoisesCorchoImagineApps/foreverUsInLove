<?php

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\UserView;
use App\Models\UsersLikes;
use App\Models\Order;


class LikesAndViewsController extends Controller
{
     public function __construct()
    {
        $this->middleware('auth:user');
    }
    
    public function likesAndViews()
    {
            $user = \Auth::user();
            
            $saved_likes= '0' ;
             
            $isOrderActive = Order::where('user_id',\Auth::user()->id)->count();
              
        if ($isOrderActive == '0')
        {
              $who_view_me = '0';
              $who_likes_me = '0';
        }
        else
        {
             //WHO VIEW ME
                $result  = UserView::where('viewer_id', $user->id)->get();
 
                    if(isset($result) && $result->count() != 0) 
                    {
                        
                        $result   = $result->toArray();
                        
                        $userIds  = array_column($result, 'user_id');
                        
                        
                        $who_view_me = User::select('users.id','user_images.url','users.first_name')
                                       ->leftjoin('user_images','user_images.user_id','=','users.id')
                                       ->whereIn('users.id', $userIds)
                                       ->groupBy('user_images.user_id')
                                       ->get();
                        
                        
                        
                      
                    }
        
                    else
                    {
                            $who_view_me = '1';
                    }
                //END
                
                //WHO LIKES ME
                     $result  = UsersLikes::where('like_to', $user->id)
                                ->whereIn('like_status', ['like','super_like'])
                                ->whereNotIn('match_status', ['match'])
                                ->orderBy('like_status','DESC')
                                ->get();
 
 
                    if(isset($result) && $result->count() != 0) 
                    {
                        
                        $result   = $result->toArray();
                        
                        $userIds  = array_column($result, 'like_from');
                        
                        
                        $who_likes_me = User::select('users.first_name','users.id','user_images.url')
                                       ->leftjoin('user_images','user_images.user_id','=','users.id')
                                       ->whereIn('users.id', $userIds)
                                       ->groupBy('user_images.user_id')
                                       ->get();

                    }
        
                    else
                    {
                            $who_likes_me = '1';
                    }
                //END
                
                //SAVED LIKES
                
                        $result = UsersLikes::where('like_from', $user->id)->where('like_status','review')->get();
                        
                       // print_r($result);
                
                        $userIds = [];
                        if(!empty($result)) {
                            foreach ($result as $key => $like) {
                                $userIds[] = $like->like_to;
                            }
                        }
                        
                        if(isset($result) && $result->count() != 0) 
                        {
                           // $saved_likes = User::with('userKids')->whereIn('id', $userIds)->get();
                            $saved_likes =  User::select('users.id','user_images.url','users.first_name')
                                       ->leftjoin('user_images','user_images.user_id','=','users.id')
                                       ->whereIn('users.id', $userIds)
                                       ->groupBy('user_images.user_id')
                                       ->get();
                        }
                        else
                        {
                            $saved_likes = '0';
                        }
                
                        
                //END
      
        }
      
        //End
        
        //print_r($saved_likes);exit;
        return view('web.likes_and_views')->with(['saved_likes'=>$saved_likes,'who_view_me'=>$who_view_me,'who_likes_me'=>$who_likes_me]);
    }
    
   
}