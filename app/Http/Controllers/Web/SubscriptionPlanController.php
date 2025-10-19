<?php



namespace App\Http\Controllers\Web;



use Illuminate\Http\Request;

use App\Http\Controllers\Controller;

//use App\Models\User;

use App\Models\Plan;

use Carbon\Carbon;

use App\Models\Notifcation;

use App\Models\Order;







class SubscriptionPlanController extends Controller

{

     public function __construct()

    {

        $this->middleware('auth:user');

    }

    

    public function viewPage()

    {

        return view('web.subscription_plan');

    }

    

    public function viewList()

    {

        $plans = Plan::whereNotIn('id',[1,7])->get();

        //print_r($plans);exit;

        return view('web.subscription_plan_list')->with(['plans'=>$plans]);

    }

    

    public function purchasePlan(Request $request)

    {

           



        $user      = \Auth::user();

        $order     = Order::where('user_id', $user->id)->where('status', 'Active')->orderBy('id', 'desc')->first();

        $startDate = Carbon::toDay()->setTimezone('Asia/Kolkata');

        

        

        $params    = $request->all();



        if($order) {

            //return $this->errorResponse(['is_once_purchased'=> '1'], 'Already activated plan');

           // return 'Plan is already activated!';

           return '0';

        }

        $plan      = Plan::where(['id'=> $request->plan_id])->first();



       

        $month                            = '+'.$plan->month." month";

        $params['start_date']             = $startDate->format('Y-m-d');

        //$params['coins']                  = $params['coins'];

        $params['end_date']               = $startDate->addDays($plan->plan_duration)->format('Y-m-d');

        $params['payment_status']         = 'Paid';

        $params['status']                 = 'Active';

        $params['user_id']                = $user->id;

        $params['subscription_id']        = $request->plan_id;

        $params['like_per_day']           = isset($plan->like_per_day) ? $plan->like_per_day : 0;

        $params['plan_type']              = isset($plan->plan_type) ? $plan->plan_type : 0;

        

        $order = Order::addUpdateOrder($params);



        //save video call duration in user table

        $user->available_video_call_duration = $plan->video_call_duration;

        $user->save();





        if(!$order) {

          // return $this->errorResponse([], 'Something went wrong!');

          return '1';

        }

        

        else

        {

             // send notification start

        $pushTittle = 'Plan Purchased';

        $message = 'You have successfully subscribed '.$plan->title.' plan';

        //$message           = ' You are able to access this room';

        

        $responsedata = [                

            'type'              => 'users_subscribe_plan',

        ];



        $pushData = [

            'message' => $responsedata

        ];



        if ($user->fcm_token) {

            $this->sendPushNotifcationComman($user->fcm_token,$pushTittle, $message,$user->id, $pushData);

        }

        $data = [

            'icon'=>asset('images/favicon/apple-touch-icon-152x152.png'),

            'plan_name'=>$plan->title,

            'plan_desc'=>$plan->description,

        ];

        $notiParams = [

            'user_id'  => $user->id,

            'sender_id'=> $user->id,

            'title'    => $pushTittle,

            'message'  => $message,

            'type'     => 'Subscribe Plan',

            'data'     => json_encode($data),

        ];



        Notifcation::addNotificationHistory($notiParams);

        // send notification end

        

        return '2';

        

        }





       



       // return $this->successResponse($order, 'Success');

    }

    

    public function freeTrialActivation (Request $request)

    {

           $user      = \Auth::user();

        $order     = Order::where('user_id', $user->id)->where('status', 'Active')->orderBy('id', 'desc')->first();

        $startDate = Carbon::toDay()->setTimezone('Asia/Kolkata');

        

        $params    = $request->all();



        if($order) {

           // return $this->errorResponse([], 'Already activated plan');

           return '0';

        }





        $isUsedTrial = Order::where('user_id', $user->id)->where(['plan_type'=>'free'])->orderBy('id', 'desc')->first();

        if ($isUsedTrial) {

           // return $this->errorResponse([], 'You Already Used Free Trial');

           return '1';

        }



        $plan                             = Plan::where(['id'=> 7,'plan_type'=>'free'])->first();

        

        

        $params['start_date']             = $startDate->format('Y-m-d');

        //$params['coins']                  = $params['coins'];

        $params['end_date']               = $startDate->addDays('7')->format('Y-m-d');

        $params['payment_status']         = 'Paid';

        $params['status']                 = 'Active';

        $params['user_id']                = $user->id;

        $params['subscription_id']        = 6;

        $params['like_per_day']           = isset($plan->like_per_day) ? $plan->like_per_day : 0;

        $params['plan_type']              = isset($plan->plan_type) ? $plan->plan_type : 0;

        $params['payment_type']           = 'play_store';

        

        $order = Order::addUpdateOrder($params);



        

        if(!$order) {

          // return $this->errorResponse([], 'Something went wrong!');

          return '2';

        }

        else

        {

            return '3';

        }



       // return $this->successResponse($order, 'Success');    

    }

   

}