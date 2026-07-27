<?php

namespace App\Http\Controllers\Webhook;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class AfricasTalkingSmsController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $data = $request->validate([
            'id' => ['required', 'string', 'max:160'],
            'status' => ['required', 'string', 'max:80'],
            'phoneNumber' => ['nullable', 'string', 'max:30'],
            'cost' => ['nullable', 'string', 'max:40'],
        ]);
        $message = SmsMessage::query()->withoutGlobalScopes()->where('provider_message_id', $data['id'])->first();
        if ($message) {
            $message->update([
                'status' => strtolower($data['status']),
                'delivered_at' => strtolower($data['status']) === 'success' ? now() : null,
                'provider_response' => $request->all(),
            ]);
        }

        return response('Accepted', 200);
    }
}
