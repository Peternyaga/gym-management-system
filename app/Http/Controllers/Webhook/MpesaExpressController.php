<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Services\Payments\MpesaCallbackProcessor;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class MpesaExpressController extends Controller
{
    public function __invoke(Request $request, string $deploymentToken, MpesaCallbackProcessor $processor): JsonResponse
    {
        abort_unless(hash_equals((string) config('services.mpesa.deployment_token'), $deploymentToken), 404);
        $processor->process($request->json()->all());

        return response()->json(['ResultCode' => 0, 'ResultDesc' => 'Accepted']);
    }
}
