<?php

use App\Modules\Platform\Preview\EnsureLocalPreview;
use App\Modules\Platform\Preview\PreviewController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/academic.php';
require __DIR__.'/operations.php';
require __DIR__.'/finance.php';

Route::get('/', function () {
    abort_unless(app()->environment('local') && config('preview.enabled'), 404);

    return redirect('/preview');
});

// Guard at request time, even when routes were cached in another environment.
// Production tenant management endpoints intentionally do not exist yet.
Route::prefix('preview')->middleware(EnsureLocalPreview::class)->group(function () {
    Route::get('/', PreviewController::class)->defaults('page', 'overview')->name('preview.overview');
    foreach (['schools', 'plans', 'settings'] as $page) {
        Route::get('/'.$page, PreviewController::class)->defaults('page', $page)->name('preview.'.$page);
    }
    Route::get('/schools/{school}/{page?}', PreviewController::class)
        ->where('school', 'demo-[a-z0-9-]+')->where('page', 'profile|dashboard|students|academics|attendance|examinations|fees|classes|calendar|subjects|timetables|parents|clubs|communications|accounts|expenses|accounting|transport|staff|marketing|campuses|school-settings|sections|teachers|teacher-assignments|enrollments|class-subjects|sessions')
        ->defaults('page', 'profile')->name('preview.school');
});
