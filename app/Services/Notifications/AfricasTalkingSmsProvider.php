<?php

namespace App\Services\Notifications;

use App\Contracts\SmsProvider;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class AfricasTalkingSmsProvider implements SmsProvider
{
    public function send(string $phoneE164, string $message): array
    {
        $username = (string) config('services.africastalking.username');
        $apiKey = (string) config('services.africastalking.api_key');
        if ($apiKey === '') {
            if (app()->environment('local', 'testing')) {
                logger()->info('Development SMS', ['to' => $phoneE164, 'message' => $message]);

                return ['accepted' => true, 'messageId' => 'local-'.str()->uuid(), 'status' => 'logged', 'response' => []];
            }
            throw new RuntimeException('Africa’s Talking credentials are not configured.');
        }

        $response = Http::asForm()
            ->timeout(8)
            ->withHeaders(['apiKey' => $apiKey, 'Accept' => 'application/json'])
            ->post('https://api.africastalking.com/version1/messaging', array_filter([
                'username' => $username,
                'to' => $phoneE164,
                'message' => $message,
                'from' => config('services.africastalking.sender_id'),
            ]))
            ->throw()
            ->json();
        $recipient = data_get($response, 'SMSMessageData.Recipients.0', []);

        return [
            'accepted' => in_array($recipient['status'] ?? '', ['Success', 'Sent'], true),
            'messageId' => $recipient['messageId'] ?? null,
            'status' => $recipient['status'] ?? 'unknown',
            'response' => $response,
        ];
    }
}
