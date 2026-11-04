<?php

namespace App\Modules\Academics\Controllers;

use App\Modules\Academics\AcademicPolicy;
use App\Modules\Academics\AcademicResources;
use App\Modules\Academics\AcademicService;
use App\Modules\Academics\Models\AcademicRecord;
use App\Modules\Academics\Models\Enrollment;
use App\Modules\Academics\Models\Student;
use App\Modules\Academics\Requests\AcademicDeleteRequest;
use App\Modules\Academics\Requests\AcademicRecordRequest;
use App\Modules\Academics\Requests\EnrollmentChangeRequest;
use App\Modules\Platform\Preview\PreviewData;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

final class AcademicController
{
    private function record(Request $request): AcademicRecord
    {
        $class = AcademicResources::model($request->route('resource'));
        $query = $class::query();
        if ($class === Student::class && $request->route('mode') === 'show') {
            $query->withTrashed();
        }
        $record = $query->findOrFail($request->route('record'));
        abort_unless(app(AcademicPolicy::class)->allows($record), 403);

        return $record;
    }

    private function base(Request $request): string
    {
        return '/preview/schools/'.$request->route('school').'/academic/'.$request->route('resource');
    }

    public function page(Request $request, PreviewData $preview)
    {
        $resource = $request->route('resource');
        $class = AcademicResources::model($resource);
        $mode = $request->route('mode', 'index');
        $record = $request->route('record') ? $this->record($request) : null;
        $filters = $request->validate(['q' => ['nullable', 'string', 'max:100'], 'status' => ['nullable', 'string', 'max:30'], 'academic_session_id' => ['nullable', 'integer'], 'academic_class_id' => ['nullable', 'integer'], 'page' => ['nullable', 'integer', 'min:1']]);
        $query = $class::query();
        if ($resource === 'students' && ($filters['status'] ?? null) === 'archived') {
            $query->withTrashed();
        }
        $table = (new $class)->getTable();
        if ($q = $filters['q'] ?? null) {
            if ($resource === 'enrollments') {
                $query->whereHas('student', fn ($s) => $s->where(fn ($s) => $s->whereRaw('lower(full_name) LIKE ?', ['%'.strtolower($q).'%'])->orWhereRaw('lower(admission_number) LIKE ?', ['%'.strtolower($q).'%'])));
            }
            if ($resource === 'teacher-assignments') {
                $query->whereHas('teacher', fn ($t) => $t->whereRaw('lower(full_name) LIKE ?', ['%'.strtolower($q).'%']));
            }
            if ($resource === 'class-subjects') {
                $query->whereHas('subject', fn ($s) => $s->whereRaw('lower(name) LIKE ?', ['%'.strtolower($q).'%']));
            }
            $query->where(function ($query) use ($resource, $q) {
                foreach (match ($resource) {
                    'students' => ['full_name', 'admission_number', 'guardian_name'],'teachers' => ['full_name', 'employee_code'],'sessions' => ['name'],'classes','sections','subjects' => ['name', 'code'],default => []
                } as $column) {
                    $query->orWhereRaw('lower('.$column.') LIKE ?', ['%'.strtolower($q).'%']);
                }
            });
        }
        if ($status = $filters['status'] ?? null) {
            if (in_array($resource, ['sessions', 'classes', 'sections', 'subjects'])) {
                $query->where('active', $status === 'active');
            } elseif (in_array($resource, ['students', 'teachers', 'enrollments'])) {
                $query->where('status', $status);
            }
        }
        foreach (['academic_session_id', 'academic_class_id'] as $key) {
            if (! empty($filters[$key]) && Schema::hasColumn($table, $key)) {
                $query->where($key, $filters[$key]);
            }
        }
        $relations = match ($resource) {
            'sections' => ['academicClass'],'class-subjects' => ['academicClass', 'subject'],'teacher-assignments' => ['teacher', 'subject', 'section', 'academicClass', 'academicSession'],'enrollments' => ['student', 'section', 'academicClass', 'academicSession'],default => []
        };
        $rows = $query->with($relations)->orderByDesc('id')->paginate(15)->withQueryString();
        $options = [];
        foreach (['sessions', 'classes', 'sections', 'subjects', 'teachers', 'students'] as $key) {
            $model = AcademicResources::model($key);
            $options[$key] = $model::orderBy('id')->get()->map(fn ($r) => ['id' => $r->id, 'label' => $r->name ?? (($r->admission_number ?? $r->employee_code).' · '.$r->full_name), 'academic_class_id' => $r->academic_class_id, 'active' => $r->active, 'status' => $r->status]);
        }
        $history = $resource === 'students' && $record ? $record->enrollments()->with(['academicSession', 'academicClass', 'section'])->get() : [];
        $assignments = $resource === 'teachers' && $record ? $record->assignments()->with(['academicSession', 'academicClass', 'section', 'subject'])->get() : [];

        return Inertia::render('Academic', [...$preview->get($request->route('school')), 'page' => $resource, 'resource' => $resource, 'title' => AcademicResources::TITLES[$resource], 'mode' => $mode, 'record' => $record, 'rows' => $rows, 'fields' => AcademicResources::fields($resource), 'options' => $options, 'filters' => $filters, 'history' => $history, 'assignments' => $assignments, 'baseUrl' => $this->base($request), 'flash' => ['success' => $request->session()->get('success')]]);
    }

    private function safely(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (QueryException $e) {
            if (in_array($e->getCode(), ['23503', '23505', '23514', '23000', '23001'])) {
                throw ValidationException::withMessages(['record' => 'This change conflicts with existing records. Check duplicates and dependencies; linked records cannot be deleted.']);
            } throw $e;
        }
    }

    public function store(AcademicRecordRequest $request, AcademicService $service)
    {
        $record = $this->safely(fn () => $service->save($request->route('resource'), $request->validated()));

        return redirect($this->base($request).'/'.$record->id)->with('success', 'Record created successfully.');
    }

    public function update(AcademicRecordRequest $request, AcademicService $service)
    {
        $record = $this->record($request);
        $this->safely(fn () => $service->save($request->route('resource'), $request->validated(), $record));

        return redirect($this->base($request).'/'.$record->id)->with('success', 'Record updated successfully.');
    }

    public function destroy(AcademicDeleteRequest $request, AcademicService $service)
    {
        $record = $this->record($request);
        $this->safely(fn () => $service->delete($record));

        return redirect($this->base($request))->with('success', 'Record removed or archived successfully.');
    }

    public function change(EnrollmentChangeRequest $request, AcademicService $service)
    {
        $record = $this->record($request);
        abort_unless($record instanceof Enrollment, 404);
        $this->safely(fn () => $service->change($record, $request->validated()));

        return redirect($this->base($request))->with('success', 'Enrollment history updated successfully.');
    }
}
