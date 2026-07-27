<?php

namespace App\Support;

use App\Models\Tenant;
use LogicException;

class TenantContext
{
    private ?Tenant $tenant = null;

    public function set(Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }

    public function clear(): void
    {
        $this->tenant = null;
    }

    public function current(): ?Tenant
    {
        return $this->tenant;
    }

    public function id(): string
    {
        return $this->tenant?->getKey() ?? throw new LogicException('No tenant has been resolved.');
    }
}
