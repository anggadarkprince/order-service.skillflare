<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Illuminate\Http\Request;
use Midtrans\Config;
use Midtrans\Snap;

class OrderController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->input('user_id');
        $orders = Order::query();

        $orders->when($userId, function($query) use ($userId) {
            return $query->where('user_id', '=', $userId);
        });

        return response()->json([
            'status' => 'success',
            'code' => 200,
            'data' => $orders->get()
        ]);
    }

    private function getMidtransSnapUrl($params)
    {
        Config::$serverKey = env('MIDTRANS_SERVER_KEY');
        Config::$isProduction = (bool) env('MIDTRANS_PRODUCTION');
        Config::$is3ds = (bool) env('MIDTRANS_3DS');

        return Snap::createTransaction($params)->redirect_url;
    }

    public function create(Request $request)
    {
        $user = $request->input('user');
        $course = $request->input('course');

        $order = Order::create([
            'user_id' => $user['id'],
            'course_id' => $course['id'],
            'price' => $course['price']
        ]);

        $midtransParams = [
            'transaction_details' => [
                'order_id' => $order->id,
                'gross_amount' => $course['price']
            ],
            'item_details' => [
                [
                    'id' => $course['id'],
                    'price' => $course['price'],
                    'quantity' => 1,
                    'name' => $course['title'],
                    'brand' => 'Skillflare',
                    'category' => 'Online Course'
                ]
            ],
            'customer_details' => [
                'name' => $user['name'],
                'email' => $user['email']
            ],
        ];

        $midtransSnapUrl = $this->getMidtransSnapUrl($midtransParams);

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
    }
}
