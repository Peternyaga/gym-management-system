<?php

namespace App\Http\Controllers;

use App\Contracts\SmsProvider;
use App\Models\Invoice;
use App\Models\MemberProfile;
use App\Models\Membership;
use App\Models\MembershipPlan;
use App\Models\PhoneVerification;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class PublicSignupController extends Controller
{
    public function sendOtp(Request $request, Tenant $tenant, SmsProvider $sms): JsonResponse
    {
        $data = $request->validate(['phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/']]);
        $phone = $this->normalizePhone($data['phone']);
        $otp = (string) random_int(100000, 999999);
        $verification = PhoneVerification::query()->create([
            'tenant_id' => $tenant->id,
            'phone' => $phone,
            'otp_hash' => Hash::make($otp),
            'expires_at' => now()->addMinutes(5),
        ]);
        $sms->send($phone, "{$tenant->name}: your verification code is {$otp}. It expires in 5 minutes.");

        return response()->json([
            'verificationId' => $verification->id,
            'message' => 'A verification code has been sent.',
            'developmentCode' => app()->environment('local', 'testing') ? $otp : null,
        ]);
    }

    public function join(Request $request, Tenant $tenant): RedirectResponse
    {
        $data = $request->validate([
            'verification_id' => ['required', 'string'],
            'otp' => ['required', 'digits:6'],
            'plan_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'email' => ['nullable', 'email', 'max:160'],
            'password' => ['required', 'confirmed', 'min:8'],
            'emergency_contact_name' => ['required', 'string', 'max:120'],
            'emergency_contact_phone' => ['required', 'regex:/^(?:\+254|0)7\d{8}$/'],
            'adult_confirmed' => ['accepted'],
            'privacy_accepted' => ['accepted'],
            'membership_terms_accepted' => ['accepted'],
            'waiver_accepted' => ['accepted'],
            'notifications_accepted' => ['boolean'],
        ]);
        $phone = $this->normalizePhone($data['phone']);
        $verification = PhoneVerification::query()
            ->where('tenant_id', $tenant->id)
            ->whereKey($data['verification_id'])
            ->lockForUpdate()
            ->firstOrFail();
        if ($verification->phone !== $phone || $verification->verified_at || $verification->expires_at->isPast() || $verification->attempts >= 5 || ! Hash::check($data['otp'], $verification->otp_hash)) {
            $verification->increment('attempts');
            throw ValidationException::withMessages(['otp' => 'The verification code is invalid or expired.']);
        }
        $plan = MembershipPlan::query()
            ->where('tenant_id', $tenant->id)
            ->where('is_public', true)
            ->where('is_active', true)
            ->whereKey($data['plan_id'])
            ->firstOrFail();

        DB::transaction(function () use ($tenant, $data, $phone, $verification, $plan): void {
            $verification->update(['verified_at' => now()]);
            $user = User::query()->where('phone', $phone)->first();
            if ($user && ! Hash::check($data['password'], $user->password)) {
                throw ValidationException::withMessages(['password' => 'This phone already has an account. Use its existing password.']);
            }
            $user ??= User::query()->create([
                'name' => $data['name'],
                'email' => $data['email'] ?: 'member-'.str()->uuid().'@phone.gymflow.invalid',
                'phone' => $phone,
                'email_verified_at' => now(),
                'password' => $data['password'],
            ]);
            abort_if($tenant->users()->whereKey($user->id)->exists(), 409, 'This phone is already registered at the gym.');
            $tenant->users()->attach($user->id, [
                'role' => 'member',
                'status' => 'active',
                'location_id' => $plan->location_id,
                'joined_at' => now(),
            ]);
            $member = MemberProfile::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $plan->location_id,
                'user_id' => $user->id,
                'member_number' => 'MEM-'.str_pad((string) (MemberProfile::query()->where('tenant_id', $tenant->id)->withTrashed()->count() + 1), 5, '0', STR_PAD_LEFT),
                'name' => $data['name'],
                'phone' => $phone,
                'email' => $data['email'] ?: null,
                'emergency_contact_name' => $data['emergency_contact_name'],
                'emergency_contact_phone' => $this->normalizePhone($data['emergency_contact_phone']),
                'adult_confirmed' => true,
                'status' => 'active',
                'joined_at' => now(),
            ]);
            foreach ([
                'privacy' => true,
                'membership_terms' => true,
                'waiver' => true,
                'notifications' => (bool) ($data['notifications_accepted'] ?? false),
            ] as $type => $accepted) {
                $consentId = DB::table('consent_versions')->where('tenant_id', $tenant->id)->where('type', $type)->whereNotNull('published_at')->latest('version')->value('id');
                if (! $consentId) {
                    $consentId = (string) str()->ulid();
                    DB::table('consent_versions')->insert([
                        'id' => $consentId,
                        'tenant_id' => $tenant->id,
                        'type' => $type,
                        'version' => 1,
                        'title' => ucfirst(str_replace('_', ' ', $type)),
                        'body' => "Default {$type} terms. Replace in gym settings before production.",
                        'required' => $type !== 'notifications',
                        'published_at' => now(),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                DB::table('member_consents')->insert([
                    'id' => (string) str()->ulid(),
                    'tenant_id' => $tenant->id,
                    'member_id' => $member->id,
                    'consent_version_id' => $consentId,
                    'accepted' => $accepted,
                    'method' => 'self_service',
                    'ip_address' => request()->ip(),
                    'accepted_at' => $accepted ? now() : null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            Membership::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $plan->location_id,
                'member_id' => $member->id,
                'plan_id' => $plan->id,
                'status' => 'pending',
                'plan_snapshot' => $plan->toArray(),
                'starts_at' => now(),
                'ends_at' => $plan->type === 'fixed' ? now()->addDays($plan->duration_days) : null,
                'next_billing_at' => $plan->type === 'recurring' ? now() : null,
                'credits_remaining' => $plan->type === 'pack' ? $plan->pack_credits : null,
            ]);
            $total = $plan->price_minor + $plan->joining_fee_minor;
            $invoice = Invoice::query()->create([
                'tenant_id' => $tenant->id,
                'location_id' => $plan->location_id,
                'member_id' => $member->id,
                'number' => 'INV-'.now()->format('Ym').'-'.str_pad((string) (Invoice::query()->where('tenant_id', $tenant->id)->count() + 1), 5, '0', STR_PAD_LEFT),
                'status' => 'issued',
                'currency' => 'KES',
                'subtotal_minor' => $total,
                'total_minor' => $total,
                'issued_at' => now(),
                'due_at' => now(),
            ]);
            $invoice->items()->create([
                'description' => $plan->name,
                'quantity' => 1,
                'unit_price_minor' => $total,
                'total_minor' => $total,
            ]);
            session()->flash('success', "Welcome to {$tenant->name}. Invoice {$invoice->number} is ready for M-Pesa payment.");
        });

        return redirect()->route('gym.public', $tenant->slug);
    }

    private function normalizePhone(string $phone): string
    {
        $phone = preg_replace('/\s+/', '', $phone);

        return str_starts_with($phone, '0') ? '+254'.substr($phone, 1) : $phone;
    }
}
