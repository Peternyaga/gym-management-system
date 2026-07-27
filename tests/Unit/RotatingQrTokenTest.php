<?php

namespace Tests\Unit;

use App\Models\Tenant;
use App\Services\Attendance\RotatingQrToken;
use Tests\TestCase;

class RotatingQrTokenTest extends TestCase
{
    public function test_tokens_are_signed_and_expire_after_the_allowed_window(): void
    {
        $tenant = new Tenant(['name' => 'Token Gym']);
        $tenant->id = '01TESTTENANT00000000000000';
        $service = app(RotatingQrToken::class);
        $currentMinute = (int) floor(now()->timestamp / 60);
        $token = $service->issue($tenant, '01TESTMEMBER00000000000000', $currentMinute);

        $this->assertSame('01TESTMEMBER00000000000000', $service->verify($token)['member']);
        $this->assertNull($service->verify($token.'tampered'));
        $this->assertNull($service->verify($service->issue($tenant, '01TESTMEMBER00000000000000', $currentMinute - 3)));
    }
}
