<?php

use App\Http\Controllers\PlatformController;
use App\Http\Controllers\PublicGymController;
use App\Http\Controllers\PublicSignupController;
use App\Http\Controllers\Tenant\CheckinController;
use App\Http\Controllers\Tenant\DashboardController;
use App\Http\Controllers\Tenant\ExportController;
use App\Http\Controllers\Tenant\MemberController;
use App\Http\Controllers\Tenant\MemberPortalController;
use App\Http\Controllers\Tenant\PaymentController;
use App\Http\Controllers\Tenant\PlanController;
use App\Http\Controllers\Tenant\ScheduleController;
use App\Http\Controllers\Tenant\SupportAccessController;
use App\Http\Controllers\Tenant\ThemeController;
use App\Http\Controllers\Webhook\AfricasTalkingSmsController;
use App\Http\Controllers\Webhook\MpesaExpressController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::inertia('/', 'welcome')->name('home');
Route::post('/webhooks/mpesa/express/{deploymentToken}', MpesaExpressController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.mpesa');
Route::post('/webhooks/africastalking/sms', AfricasTalkingSmsController::class)
    ->middleware('throttle:120,1')
    ->name('webhooks.sms');

Route::middleware('tenant')->group(function (): void {
    Route::get('/g/{tenant}', PublicGymController::class)->name('gym.public');
    Route::post('/g/{tenant}/otp', [PublicSignupController::class, 'sendOtp'])->middleware('throttle:5,10')->name('gym.otp');
    Route::post('/g/{tenant}/join', [PublicSignupController::class, 'join'])->middleware('throttle:10,10')->name('gym.join');
    Route::get('/themes/{tenant}/{version}.css', [ThemeController::class, 'css'])->whereNumber('version')->name('theme.css');
    Route::get('/t/{tenant}/manifest.webmanifest', [ThemeController::class, 'manifest'])->name('tenant.manifest');
    Route::get('/t/{tenant}/login', fn () => Inertia::render('auth/login', [
        'canResetPassword' => Features::enabled(Features::resetPasswords()),
        'status' => session('status'),
    ]))->middleware('guest')->name('tenant.login');
});

Route::middleware(['auth', 'verified'])->group(function (): void {
    Route::get('/dashboard', function (Request $request) {
        if ($request->user()->is_platform_admin) {
            return redirect()->route('platform.dashboard');
        }
        $tenant = $request->user()->tenants()->wherePivot('status', 'active')->first();

        return $tenant
            ? redirect()->route($tenant->pivot->getAttribute('role') === 'member' ? 'member.home' : 'tenant.dashboard', $tenant->getAttribute('slug'))
            : abort(403, 'No active gym membership.');
    })->name('dashboard');
    Route::get('/platform', [PlatformController::class, 'index'])->name('platform.dashboard');
    Route::get('/platform/tenants/{tenant}', [PlatformController::class, 'show'])->name('platform.tenants.show');
    Route::patch('/platform/tenants/{tenant}/status', [PlatformController::class, 'updateStatus'])
        ->middleware('password.confirm')
        ->name('platform.tenants.status');

    Route::prefix('/t/{tenant}')->middleware(['tenant', 'tenant.member'])->group(function (): void {
        Route::get('/dashboard', DashboardController::class)->middleware('tenant.member:owner,manager,receptionist,trainer')->name('tenant.dashboard');
        Route::get('/members', [MemberController::class, 'index'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.members');
        Route::post('/members', [MemberController::class, 'store'])->middleware('tenant.member:owner,manager,receptionist');
        Route::get('/members/{member}', [MemberController::class, 'show'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.members.show');
        Route::patch('/members/{member}', [MemberController::class, 'update'])->middleware('tenant.member:owner,manager,receptionist');
        Route::post('/members/{member}/membership-action', [MemberController::class, 'membershipAction'])->middleware('tenant.member:owner,manager');
        Route::get('/plans', [PlanController::class, 'index'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.plans');
        Route::post('/plans', [PlanController::class, 'store'])->middleware('tenant.member:owner,manager');
        Route::get('/plans/{plan}', [PlanController::class, 'show'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.plans.show');
        Route::patch('/plans/{plan}', [PlanController::class, 'update'])->middleware('tenant.member:owner,manager');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->middleware('tenant.member:owner,manager');
        Route::get('/schedule', [ScheduleController::class, 'index'])->middleware('tenant.member:owner,manager,receptionist,trainer')->name('tenant.schedule');
        Route::post('/schedule', [ScheduleController::class, 'store'])->middleware('tenant.member:owner,manager');
        Route::get('/schedule/{class}', [ScheduleController::class, 'show'])->middleware('tenant.member:owner,manager,receptionist,trainer')->name('tenant.schedule.show');
        Route::patch('/schedule/{class}', [ScheduleController::class, 'update'])->middleware('tenant.member:owner,manager');
        Route::delete('/schedule/{class}', [ScheduleController::class, 'cancel'])->middleware('tenant.member:owner,manager');
        Route::patch('/schedule/{class}/bookings/{booking}/attendance', [ScheduleController::class, 'attendance'])->middleware('tenant.member:owner,manager,receptionist,trainer');
        Route::get('/payments', [PaymentController::class, 'index'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.payments');
        Route::get('/payments/invoices/{invoice}', [PaymentController::class, 'showInvoice'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.invoices.show');
        Route::get('/payments/{payment}', [PaymentController::class, 'showPayment'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.payments.show');
        Route::post('/payments/stk', [PaymentController::class, 'initiate'])->middleware('throttle:10,1')->name('tenant.payments.stk');
        Route::get('/check-in', [CheckinController::class, 'index'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.checkin');
        Route::post('/check-in', [CheckinController::class, 'store'])->middleware(['tenant.member:owner,manager,receptionist', 'throttle:30,1']);
        Route::get('/check-in/{checkin}', [CheckinController::class, 'show'])->middleware('tenant.member:owner,manager,receptionist')->name('tenant.checkin.show');
        Route::get('/member', [MemberPortalController::class, 'home'])->middleware('tenant.member:member')->name('member.home');
        Route::post('/member/bookings/{class}', [MemberPortalController::class, 'book'])->middleware(['tenant.member:member', 'throttle:20,1']);
        Route::delete('/member/bookings/{class}', [MemberPortalController::class, 'cancel'])->middleware(['tenant.member:member', 'throttle:20,1']);
        Route::get('/exports/etims', [ExportController::class, 'etims'])->middleware('tenant.member:owner,manager')->name('tenant.exports.etims');
        Route::get('/settings/support-access', [SupportAccessController::class, 'index'])->middleware('tenant.member:owner')->name('tenant.support-access');
        Route::post('/settings/support-access', [SupportAccessController::class, 'store'])->middleware(['tenant.member:owner', 'password.confirm', 'throttle:5,1']);
        Route::delete('/settings/support-access/{grant}', [SupportAccessController::class, 'destroy'])->middleware(['tenant.member:owner', 'password.confirm', 'throttle:5,1']);

        Route::prefix('/settings/theme')->middleware('tenant.member:owner,manager')->group(function (): void {
            Route::get('/', [ThemeController::class, 'edit'])->name('tenant.theme');
            Route::post('/draft', [ThemeController::class, 'createDraft']);
            Route::patch('/draft', [ThemeController::class, 'update']);
            Route::post('/assets', [ThemeController::class, 'uploadAsset'])->middleware('throttle:10,1');
            Route::delete('/assets/{asset}', [ThemeController::class, 'deleteAsset'])->middleware('throttle:10,1');
            Route::post('/validate', [ThemeController::class, 'validateTheme'])->middleware('throttle:20,1');
            Route::post('/publish', [ThemeController::class, 'publish'])->middleware(['tenant.member:owner', 'password.confirm', 'throttle:5,1']);
            Route::post('/rollback/{version}', [ThemeController::class, 'rollback'])->middleware(['tenant.member:owner', 'password.confirm', 'throttle:5,1']);
        });
    });
});

require __DIR__.'/settings.php';
