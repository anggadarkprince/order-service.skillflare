<?php

namespace App\Http\Controllers;

use App\Http\Requests\CreateOrderRequest;
use App\Http\Resources\OrderCollection;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\DB;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    /**
     * Show order data.
     *
     * @param Request $request
     * @return OrderCollection
     */
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $orders = Order::query();

        $orders->when($userId, function($query) use ($userId) {
            return $query->where('user_id', '=', $userId);
        });

        return new OrderCollection($orders->paginate());
    }

    /**
     * Generate midtrans snap url.
     *
     * @param $order
     * @param $course
     * @param $user
     * @return mixed
     */
    private function getMidtransSnapUrl($order, $course, $user)
    {
        $midtransParams = [
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => $order->price
            ],
            'item_details' => [
                [
                    'id' => $course['id'],
                    'price' => $course['price'],
                    'name' => $course['title'],
                    'quantity' => 1,
                    'brand' => 'Skillflare',
                    'category' => 'Online Course'
                ]
            ],
            'customer_details' => [
                'name' => $user['name'],
                'email' => $user['email']
            ],
        ];

        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        Config::$is3ds = (bool) env('MIDTRANS_3DS');

        return Snap::createTransaction($midtransParams)->redirect_url;
    }

    /**
     * Create new order data.
     *
     * @param CreateOrderRequest $request
     * @return JsonResponse
     */
    public function create(CreateOrderRequest $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        return DB::transaction(function () use ($user, $course) {
            // create new order
            $order = Order::create([
                'user_id' => $user['id'],
                'course_id' => $course['id'],
                'price' => $course['price']
            ]);

            // create midtrans transaction
            $midtransSnapUrl = $this->getMidtransSnapUrl($order, $course, $user);

            // update payment data
            $order->snap_url = $midtransSnapUrl;
            $order->metadata = [
                'course_id' => $course['id'],
                'course_price' => $course['price'],
                'course_title' => $course['title'],
                'course_thumbnail' => $course['thumbnail'],
                'course_level' => $course['level'],
            ];
            $order->save();

            return response()->json([
                'status' => 'success',
                'code' => 200,
                'data' => $order
            ]);
        });
    }
}
