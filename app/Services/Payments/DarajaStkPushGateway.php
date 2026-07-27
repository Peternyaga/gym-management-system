<?php

namespace App\Services\Payments;

use App\Contracts\StkPushGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DarajaStkPushGateway implements StkPushGateway
{
    public function initiate(array $request): array
    {
        $baseUrl = rtrim((string) config('services.mpesa.base_url'), '/');
        $shortcode = (string) config('services.mpesa.shortcode');
        $passkey = (string) config('services.mpesa.passkey');
        $consumerKey = (string) config('services.mpesa.consumer_key');
        $consumerSecret = (string) config('services.mpesa.consumer_secret');

        if ($baseUrl === '' || $shortcode === '' || $passkey === '' || $consumerKey === '' || $consumerSecret === '') {
            throw new RuntimeException('M-Pesa credentials are not configured.');
        }

        $token = Http::timeout(8)
            ->withBasicAuth($consumerKey, $consumerSecret)
            ->get("{$baseUrl}/oauth/v1/generate", ['grant_type' => 'client_credentials'])
            ->throw()
            ->json('access_token');
        $timestamp = now()->format('YmdHis');
        $phone = ltrim($request['phone'], '+');

        $result = Http::timeout(10)->withToken($token)->post("{$baseUrl}/mpesa/stkpush/v1/processrequest", [
            'BusinessShortCode' => $shortcode,
            'Password' => base64_encode($shortcode.$passkey.$timestamp),
            'Timestamp' => $timestamp,
            'TransactionType' => config('services.mpesa.transaction_type', 'CustomerPayBillOnline'),
            'Amount' => (int) ceil($request['amountMinor'] / 100),
            'PartyA' => $phone,
            'PartyB' => $shortcode,
            'PhoneNumber' => $phone,
            'CallBackURL' => $request['callbackUrl'],
            'AccountReference' => mb_substr($request['accountReference'], 0, 12),
            'TransactionDesc' => mb_substr($request['description'], 0, 32),
        ])->throw()->json();

        return [
            'merchantRequestId' => $result['MerchantRequestID'] ?? null,
            'checkoutRequestId' => $result['CheckoutRequestID'] ?? null,
            'accepted' => ($result['ResponseCode'] ?? null) === '0',
            'responseCode' => $result['ResponseCode'] ?? null,
            'responseDescription' => $result['ResponseDescription'] ?? 'No response description.',
        ];
    }
}
