<?php

namespace App\Domain\Finance;

use Closure;
use Illuminate\Http\Request;

final class FinanceEnvironmentGuard
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('preview/schools/*/finance', 'preview/schools/*/finance/*')) {
            abort_unless(app()->environment('local') && config('preview.enabled') === true && config('preview.finance') === true, 404);
        }

        return $next($request);
    }
}
