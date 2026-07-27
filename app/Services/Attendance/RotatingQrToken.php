<?php

namespace App\Services\Attendance;

use App\Models\Tenant;
use Illuminate\Support\Str;

class RotatingQrToken
{
    public function issue(Tenant $tenant, string $memberId, ?int $minute = null): string
    {
        $payload = json_encode([
            'tenant' => $tenant->id,
            'member' => $memberId,
            'minute' => $minute ?? (int) floor(now()->timestamp / 60),
            'nonce' => substr(hash('sha256', "{$tenant->id}:{$memberId}"), 0, 10),
        ], JSON_THROW_ON_ERROR);
        $encoded = Str::toBase64($payload);
        $signature = hash_hmac('sha256', $encoded, (string) config('app.key'));

        return "gymqr.{$encoded}.{$signature}";
    }

    /** @return array{tenant:string,member:string,minute:int,nonce:string}|null */
    public function verify(string $token): ?array
    {
        $parts = explode('.', $token, 3);
        if (count($parts) !== 3 || $parts[0] !== 'gymqr') {
            return null;
        }
        if (! hash_equals(hash_hmac('sha256', $parts[1], (string) config('app.key')), $parts[2])) {
            return null;
        }
        $payload = json_decode((string) Str::fromBase64($parts[1]), true);
        $currentMinute = (int) floor(now()->timestamp / 60);
        if (! is_array($payload) || ! isset($payload['minute']) || abs($currentMinute - (int) $payload['minute']) > 1) {
            return null;
        }

        return $payload;
    }
}
