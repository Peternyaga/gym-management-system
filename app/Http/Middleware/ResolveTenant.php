<?php

namespace App\Http\Middleware;

use App\Models\Tenant;
use App\Support\TenantContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolveTenant
{
    public function __construct(private readonly TenantContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $parameter = $request->route('tenant');
        $tenant = $parameter instanceof Tenant
            ? $parameter
            : Tenant::query()->where('slug', $parameter)->firstOrFail();

        $this->context->set($tenant);
        $request->route()->setParameter('tenant', $tenant);

        try {
            return $next($request);
        } finally {
            $this->context->clear();
        }
    }
}
