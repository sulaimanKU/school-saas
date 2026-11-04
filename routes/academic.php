<?php

use App\Modules\Academics\Controllers\AcademicController;
use App\Modules\Academics\EnsureAcademicPreview;
use App\Modules\Platform\Preview\EnsureLocalPreview;
use Illuminate\Support\Facades\Route;

Route::prefix('preview/schools/{school}/academic')->middleware([EnsureLocalPreview::class, EnsureAcademicPreview::class])->where(['school' => 'demo-[a-z0-9-]+', 'resource' => 'sessions|classes|sections|subjects|class-subjects|teachers|teacher-assignments|students|enrollments', 'record' => '[0-9]+'])->group(function () {
    Route::get('/{resource}', [AcademicController::class, 'page'])->defaults('mode', 'index')->name('academic.index');
    Route::get('/{resource}/create', [AcademicController::class, 'page'])->defaults('mode', 'create')->name('academic.create');
    Route::get('/{resource}/{record}/edit', [AcademicController::class, 'page'])->defaults('mode', 'edit')->name('academic.edit');
    Route::get('/{resource}/{record}', [AcademicController::class, 'page'])->defaults('mode', 'show')->name('academic.show');
    Route::post('/{resource}', [AcademicController::class, 'store'])->name('academic.store');
    Route::put('/{resource}/{record}', [AcademicController::class, 'update'])->name('academic.update');
    Route::delete('/{resource}/{record}', [AcademicController::class, 'destroy'])->name('academic.destroy');
    Route::post('/{resource}/{record}/change', [AcademicController::class, 'change'])->name('academic.change');
});
