<?php

namespace App\Modules\Platform\Preview;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureLocalPreview
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(app()->environment('local') && config('preview.enabled') === true, 404);
        $response = $next($request);
        $response->headers->set('Cache-Control', 'no-store, private');
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
