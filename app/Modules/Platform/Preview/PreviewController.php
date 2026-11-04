<?php

namespace App\Modules\Platform\Preview;

use Illuminate\Http\Request;
use Inertia\Inertia;

class PreviewController
{
    public function __invoke(PreviewData $data, Request $request)
    {
        $financePages = ['accounts' => 'dashboard', 'fees' => 'payments', 'expenses' => 'expenses', 'accounting' => 'reports'];
        if (app()->environment('local') && config('preview.finance') === true && isset($financePages[$request->route('page')]) && $request->route('school')) {
            $data->get($request->route('school'));

            return redirect('/preview/schools/'.$request->route('school').'/finance/'.$financePages[$request->route('page')]);
        }
        $operational = ['attendance' => 'student-attendance', 'timetables' => 'timetable', 'examinations' => 'exams'];
        if (app()->environment('local') && config('preview.academic_crud') === true && isset($operational[$request->route('page')]) && $request->route('school')) {
            $data->get($request->route('school'));

            return redirect('/preview/schools/'.$request->route('school').'/academic/operations/'.$operational[$request->route('page')]);
        }
        $pages = ['students' => 'students', 'classes' => 'classes', 'subjects' => 'subjects', 'academics' => 'sessions', 'staff' => 'teachers'];
        $page = $request->route('page', 'overview');
        if (app()->environment('local') && config('preview.academic_crud') === true && isset($pages[$page]) && $request->route('school')) {
            // Validate the demo selection before redirecting to the independently guarded CRUD entrypoint.
            $data->get($request->route('school'));

            return redirect('/preview/schools/'.$request->route('school').'/academic/'.$pages[$page]);
        }
        $request->validate(['session_id' => ['nullable', 'integer', 'min:1']]);

        return Inertia::render('Preview', [...$data->get($request->route('school'), $request->integer('session_id') ?: null), 'page' => $page]);
    }
}
