<?php

namespace App\Modules\Academics;

use Closure;
use Illuminate\Http\Request;

/** Fail closed before session/CSRF middleware, including cached routes and write verbs. */
class AcademicEnvironmentGuard
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->is('preview/schools/*/academic/*')) {
            abort_unless(app()->environment('local') && config('preview.enabled') === true && config('preview.academic_crud') === true, 404);
        }

        return $next($request);
    }
}
