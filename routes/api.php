<?php

use App\Http\Controllers\OrderController;
use App\Http\Controllers\WebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/', function () {
    return response()->redirectToRoute('version');
});
Route::get('version', function () {
    return response()->json([
        'app' => env('APP_NAME', 'Order Service'),
        'code' => 'order-service.skillflare',
        'version' => 'v1.0'
    ]);
})->name('version');

Route::post('orders', [OrderController::class, 'create'])->name('orders.create');
Route::get('orders', [OrderController::class, 'index'])->name('orders.index');

Route::post('webhook', [WebhookController::class, 'midtransHandler'])->name('payments.webhook');
