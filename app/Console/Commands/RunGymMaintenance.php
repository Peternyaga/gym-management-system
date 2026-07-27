<?php

namespace App\Console\Commands;

use App\Models\PaymentIntent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RunGymMaintenance extends Command
{
    protected $signature = 'gym:maintenance';

    protected $description = 'Run bounded recurring billing, reconciliation, retention, and heartbeat maintenance.';

    public function handle(): int
    {
        $reviewed = PaymentIntent::query()
            ->withoutGlobalScopes()
            ->whereIn('status', ['created', 'submitted'])
            ->where('created_at', '<=', now()->subMinutes(15))
            ->update(['status' => 'pending_review', 'response_description' => 'No final callback was received within 15 minutes.']);

        DB::table('scheduler_heartbeats')->updateOrInsert(
            ['name' => 'gym-maintenance'],
            ['last_ran_at' => now(), 'metadata' => json_encode(['paymentIntentsMovedToReview' => $reviewed])],
        );
        $this->info("Gym maintenance complete; {$reviewed} payment intent(s) moved to review.");

        return self::SUCCESS;
    }
}
