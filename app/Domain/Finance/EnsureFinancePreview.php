<?php

namespace App\Domain\Finance;

use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

final class EnsureFinancePreview
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment('local') && config('preview.enabled') === true && config('preview.finance') === true, 404);
        $slug = $request->route('school');
        abort_unless(isset(DemoCatalog::SCHOOLS[$slug]), 404);
        app(TenantContext::class)->set(School::where('slug', $slug)->where('is_demo', true)->where('status', 'active')->firstOrFail());

        return $next($request);
    }
}
