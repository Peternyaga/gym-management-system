<?php

namespace Database\Seeders;

use App\Models\Checkin;
use App\Models\ClassInstance;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\Payment;
use App\Models\User;
use App\Services\TenantProvisioner;
use App\Support\TenantContext;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::factory()->create([
            'name' => 'GymFlow Platform Admin',
            'email' => 'admin@gymflow.test',
            'phone' => '+254712000000',
            'is_platform_admin' => true,
        ]);
        $owner = User::factory()->create([
            'name' => 'Amina Wanjiku',
            'email' => 'owner@gymflow.test',
            'phone' => '+254712000001',
        ]);

        $tenant = app(TenantProvisioner::class)->provision($owner, 'Pamoja Strength Club');
        app(TenantContext::class)->set($tenant);
        $location = $tenant->locations()->firstOrFail();

        $plans = collect([
            ['name' => 'Open Gym', 'description' => 'Unlimited gym-floor access every day.', 'type' => 'recurring', 'price_minor' => 450000, 'billing_interval' => 'monthly'],
            ['name' => 'Strength Unlimited', 'description' => 'Open gym plus every coached group class.', 'type' => 'recurring', 'price_minor' => 700000, 'billing_interval' => 'monthly'],
            ['name' => '10 Class Pack', 'description' => 'Ten coached sessions, valid for 90 days.', 'type' => 'pack', 'price_minor' => 550000, 'pack_credits' => 10, 'credit_scope' => 'class_credit', 'pack_expiry_days' => 90],
        ])->map(fn (array $plan) => MembershipPlan::query()->create([
            ...$plan,
            'tenant_id' => $tenant->id,
            'location_id' => $location->id,
            'joining_fee_minor' => 0,
            'interval_count' => 1,
            'grace_days' => 3,
            'is_public' => true,
            'is_active' => true,
        ]));

        $names = ['Brian Otieno', 'Mercy Njeri', 'Kevin Mwangi', 'Faith Achieng', 'John Kamau', 'Linet Chebet', 'David Mutua', 'Sharon Atieno'];
        $members = collect($names)->map(function (string $name, int $index) use ($tenant, $location, $plans) {
            $member = MemberProfile::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'member_number' => 'MEM-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                'name' => $name,
                'phone' => '+254712000'.str_pad((string) ($index + 10), 3, '0', STR_PAD_LEFT),
                'email' => strtolower(str_replace(' ', '.', $name)).'@example.test',
                'adult_confirmed' => true,
                'status' => 'active',
                'joined_at' => now()->subDays(40 - $index * 3),
            ]);
            $plan = $plans[$index % 2];
            Membership::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'status' => $index === 6 ? 'grace' : 'active',
                'plan_snapshot' => $plan->toArray(),
                'starts_at' => now()->subDays(20),
                'next_billing_at' => now()->addDays(10),
                'grace_ends_at' => $index === 6 ? now()->addDays(2) : null,
            ]);

            return $member;
        });

        foreach ($members->take(4) as $index => $member) {
            $amount = $plans[$index % 2]->price_minor;
            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'member_id' => $member->id,
                'number' => 'INV-2026-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'status' => $index < 2 ? 'paid' : 'issued',
                'currency' => 'KES',
                'subtotal_minor' => $amount,
                'total_minor' => $amount,
                'paid_minor' => $index < 2 ? $amount : 0,
                'issued_at' => now()->subDays(4 - $index),
                'due_at' => now()->addDays($index + 1),
                'paid_at' => $index < 2 ? now()->subDays(2) : null,
            ]);
            $invoice->items()->create([
                'description' => $plans[$index % 2]->name.' membership',
                'quantity' => 1,
                'unit_price_minor' => $amount,
                'tax_rate_bps' => 0,
                'tax_minor' => 0,
                'total_minor' => $amount,
            ]);
            if ($index < 2) {
                $payment = Payment::query()->create([
                    'tenant_id' => $tenant->id,
                    'source' => 'mpesa',
                    'status' => 'confirmed',
                    'receipt_number' => 'TST'.str_pad((string) ($index + 1), 7, '0', STR_PAD_LEFT),
                    'phone' => $member->phone,
                    'amount_minor' => $amount,
                    'paid_at' => now()->subDays(2 - $index),
                    'metadata' => ['seeded' => true],
                ]);
                DB::table('payment_allocations')->insert([
                    'id' => (string) str()->ulid(),
                    'payment_id' => $payment->id,
                    'invoice_id' => $invoice->id,
                    'amount_minor' => $amount,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        $classTypeId = (string) str()->ulid();
        DB::table('class_types')->insert([
            'id' => $classTypeId,
            'tenant_id' => $tenant->id,
            'name' => 'Strength Circuit',
            'description' => 'Coach-led full body strength.',
            'default_duration_minutes' => 60,
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        foreach ([1, 2, 4, 6] as $day) {
            $start = now()->addDays($day)->setTime(18, 0);
            ClassInstance::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'class_type_id' => $classTypeId,
                'name' => $day % 2 ? 'Strength Circuit' : 'Mobility Flow',
                'capacity' => 18,
                'starts_at' => $start,
                'ends_at' => $start->copy()->addHour(),
                'status' => 'scheduled',
            ]);
        }

        foreach ($members->take(5) as $index => $member) {
            Checkin::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $location->id,
                'member_id' => $member->id,
                'membership_id' => $member->memberships()->value('id'),
                'method' => 'staff',
                'result' => 'accepted',
                'checked_in_at' => now()->startOfDay()->addHours(6 + $index),
            ]);
        }

        app(TenantContext::class)->clear();
    }
}
