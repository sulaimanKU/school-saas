<?php

namespace App\Modules\Academics;

use App\Modules\Platform\Preview\DemoCatalog;
use App\Modules\Schools\Models\School;
use App\Modules\Tenancy\TenantContext;
use Closure;
use Illuminate\Http\Request;

class EnsureAcademicPreview
{
    public function handle(Request $request, Closure $next)
    {
        abort_unless(app()->environment('local') && config('preview.enabled') === true && config('preview.academic_crud') === true, 404);
        $slug = $request->route('school');
        abort_unless(array_key_exists($slug, DemoCatalog::SCHOOLS), 404);
        $school = School::where('slug', $slug)->where('is_demo', true)->where('status', 'active')->firstOrFail();
        app(TenantContext::class)->set($school);

        return $next($request);
    }
}
