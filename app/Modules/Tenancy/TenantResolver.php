<?php

namespace App\Modules\Tenancy;

use App\Modules\Schools\Models\School;
use InvalidArgumentException;

final class TenantResolver
{
    public function bySlug(string $slug): School
    {
        if (! preg_match('/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $slug) || strlen($slug) > 63) {
            throw new InvalidArgumentException('Invalid school slug.');
        }

        return School::where('slug', $slug)->where('status', 'active')->firstOrFail();
    }

    public function byHostname(string $hostname): School
    {
        $hostname = strtolower(rtrim($hostname, '.'));
        if (strlen($hostname) > 253 || ! filter_var($hostname, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) || ! str_contains($hostname, '.')) {
            throw new InvalidArgumentException('Invalid hostname.');
        }

        return School::where('status', 'active')->whereHas('domains', fn ($q) => $q->where('hostname', $hostname)->whereNotNull('verified_at'))->firstOrFail();
    }
}
