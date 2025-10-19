<?php



namespace App\Http\Controllers\Web;



use Illuminate\Http\Request;

use App\Models\ProductsOrder;

use App\Http\Controllers\Controller;

use App\Models\User;

use App\Models\Products;





class SuperLikesController extends Controller

{

     public function __construct()

    {

        $this->middleware('auth:user');

    }

    

    public function viewPage()

    {

        $products = Products::get();

        return view('web.super_likes')->with(['products' => $products]);

    }

    

    public function purchaseSuperLike(Request $request)

    {

         

        $getData = Products::where(['product_id'=>$request->product_id,'type'=>'super_like','status'=>'active'])->first();



        if ($getData) {

            $user           = $request->user();



            $data = [

                'product_id'=>$getData->product_id,

                'user_id'   => $user->id,

                'payment_status' => 'Paid',

                'payment_type'  => 'Play Store',

                'qty'=>$getData->qty,

            ];

            

            $createOrder = ProductsOrder::create($data);

            if ($createOrder) {

                return 'Super Likes Purchased Successfully!';   

            }

        }

    }

    

   

}