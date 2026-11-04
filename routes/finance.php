<?php

use App\Domain\Finance\EnsureFinancePreview;
use App\Domain\Finance\FinanceController;
use Illuminate\Support\Facades\Route;

Route::prefix('preview/schools/{school}/finance')->middleware(EnsureFinancePreview::class)->where(['school' => 'demo-[a-z0-9-]+', 'record' => '[0-9]+'])->group(function () {
    Route::get('/reports/export', [FinanceController::class, 'export'])->name('finance.export');
    Route::get('/documents/{kind}/{record}', [FinanceController::class, 'document'])->where('kind', 'invoice|receipt')->name('finance.document');
    Route::get('/expenses/{record}/attachment', [FinanceController::class, 'attachment'])->name('finance.attachment');
    Route::post('/invoices/{record}/{action}', [FinanceController::class, 'action'])->where('action', 'issue|void|preview-penalty|post-penalty')->name('finance.invoice.action');
    Route::post('/payments/{record}/{action}', [FinanceController::class, 'action'])->where('action', 'reverse|allocate')->name('finance.payment.action');
    Route::post('/refunds/{record}/{action}', [FinanceController::class, 'action'])->where('action', 'post|reject')->name('finance.refund.action');
    Route::post('/assignments/{record}/{action}', [FinanceController::class, 'action'])->where('action', 'activate|deactivate')->name('finance.assignment.action');
    Route::post('/{resource}', [FinanceController::class, 'save'])->where('resource', 'categories|structures|assignments|generate|payments|discounts|late-fees|refunds|expenses|expense-categories|settings')->name('finance.store');
    Route::put('/{resource}/{record}', [FinanceController::class, 'save'])->where('resource', 'categories|structures|discounts|late-fees|expense-categories')->name('finance.update');
    Route::delete('/{resource}/{record}', [FinanceController::class, 'destroy'])->where('resource', 'categories|structures|discounts|late-fees|expense-categories')->name('finance.delete');
    Route::get('/{page?}', [FinanceController::class, 'page'])->where('page', 'dashboard|categories|structures|assignments|generate|invoices|payments|discounts|late-fees|refunds|expenses|expense-categories|defaulters|statements|reports|audit')->defaults('page', 'dashboard')->name('finance.page');
});
