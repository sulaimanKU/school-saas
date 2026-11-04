<?php

use App\Modules\Academics\EnsureAcademicPreview;
use App\Modules\Academics\Operations\OperationsController;
use App\Modules\Platform\Preview\EnsureLocalPreview;
use Illuminate\Support\Facades\Route;

Route::prefix('preview/schools/{school}/academic/operations')->middleware([EnsureLocalPreview::class, EnsureAcademicPreview::class])->where(['school' => 'demo-[a-z0-9-]+', 'record' => '[0-9]+', 'exam' => '[0-9]+', 'student' => '[0-9]+'])->group(function () {
    Route::get('/reports/export', [OperationsController::class, 'export'])->name('operations.export');
    Route::get('/report-cards/{record}', [OperationsController::class, 'card'])->name('operations.card');
    Route::get('/results/{exam}/students/{student}', [OperationsController::class, 'card'])->name('operations.draft');
    Route::post('/results/{record}/{operation}', [OperationsController::class, 'publication'])->where('operation', 'publish|reopen')->defaults('resource', 'results')->name('operations.publication');
    Route::post('/{resource}/submit', [OperationsController::class, 'submit'])->where('resource', 'student-attendance|staff-attendance|marks')->name('operations.submit');
    Route::post('/{resource}', [OperationsController::class, 'save'])->where('resource', 'working-days|periods|timetable|grading|exam-types|exams|schedules')->name('operations.store');
    Route::put('/{resource}/{record}', [OperationsController::class, 'save'])->where('resource', 'working-days|periods|timetable|grading|exam-types|exams|schedules')->name('operations.update');
    Route::delete('/{resource}/{record}', [OperationsController::class, 'destroy'])->where('resource', 'working-days|periods|timetable|grading|exam-types|exams|schedules')->name('operations.destroy');
    Route::get('/{page}', [OperationsController::class, 'page'])->where('page', 'student-attendance|staff-attendance|working-days|periods|timetable|teacher-timetable|grading|exam-types|exams|schedules|marks|results|reports|audit')->name('operations.page');
});
