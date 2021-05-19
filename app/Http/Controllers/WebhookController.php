<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Models\PaymentLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class WebhookController extends Controller
{
    public function midtransHandler(Request $request)
    {
        $data = $request->all();

        $signatureKey = $data['signature_key'];

        $orderId = $data['order_id'];
        $statusCode = $data['status_code'];
        $grossAmount = $data['gross_amount'];
        $serverKey = env('MIDTRANS_SERVER_KEY');

        $mySignatureKey = hash('sha512', $orderId . $statusCode . $grossAmount . $serverKey);

        $transactionStatus = $data['transaction_status'];
        $type = $data['payment_type'];
        $fraudStatus = $data['fraud_status'];

        if ($signatureKey !== $mySignatureKey) {
            return response()->json([
                'status' => 'error',
                'code' => 400,
                'message' => 'invalid signature'
            ], 400);
        }

        $realOrderId = explode('-', $orderId);
        $order = Order::find($realOrderId[0]);
        echo $realOrderId[0];
        if (!$order) {
            return response()->json([
                'status' => 'error',
                'code' => 404,
                'message' => 'order id not found'
            ], 404);
        }

        if ($order->status === 'success') {
            return response()->json([
                'status' => 'error',
                'code' => 405,
                'message' => 'operation not permitted'
            ], 405);
        }

        if ($transactionStatus == 'capture') {
            if ($fraudStatus == 'challenge') {
                $order->status = 'challenge';
            } else if ($fraudStatus == 'accept') {
                $order->status = 'success';
            }
        } else if ($transactionStatus == 'settlement') {
            $order->status = 'success';
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            $order->status = 'failure';
        } else if ($transactionStatus == 'pending') {
            $order->status = 'pending';
        }

        PaymentLog::create([
            'status' => $transactionStatus,
            'raw_response' => json_encode($data),
            'order_id' => $realOrderId[0],
            'payment_type' => $type
        ]);
        $order->save();

        if ($order->status === 'success') {
            $url = env('SERVICE_COURSE_URL') . 'api/user-courses/premium';
            try {
                $response = Http::post($url, [
                    'user_id' => $order->user_id,
                    'course_id' => $order->course_id
                ]);
                $data = $response->json();

                return response()->json($data);
            } catch (Throwable $th) {
                return response()->json([
                    'status' => 'error',
                    'code' => 500,
                    'message' => 'service course unavailable'
                ]);
            }
        }

        return response()->json('Ok');
    }
}
