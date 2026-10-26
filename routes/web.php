<?php

use App\Http\Controllers\Admin\OutreachCampaignController;
use App\Http\Controllers\Admin\OutreachDashboardController;
use App\Http\Controllers\Admin\OutreachLeadController;
use App\Http\Controllers\OutreachUnsubscribeController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/outreach/unsubscribe/{lead}', OutreachUnsubscribeController::class)
    ->name('outreach.unsubscribe');

Route::prefix('/admin/outreach')->name('admin.outreach.')->group(function (): void {
    Route::get('/', OutreachDashboardController::class)->name('dashboard');

    Route::get('/leads', [OutreachLeadController::class, 'index'])->name('leads.index');
    Route::post('/leads', [OutreachLeadController::class, 'store'])->name('leads.store');
    Route::post('/leads/{lead}/recalculate', [OutreachLeadController::class, 'recalculate'])->name('leads.recalculate');
    Route::post('/leads/purge-withdrawn', [OutreachLeadController::class, 'purgeWithdrawnRecord'])->name('leads.purge_withdrawn');

    Route::get('/campaigns', [OutreachCampaignController::class, 'index'])->name('campaigns.index');
    Route::post('/campaigns', [OutreachCampaignController::class, 'store'])->name('campaigns.store');
    Route::get('/campaigns/{campaign}', [OutreachCampaignController::class, 'show'])->name('campaigns.show');
    Route::post('/campaigns/{campaign}/timing-policy', [OutreachCampaignController::class, 'updateTimingPolicy'])->name('campaigns.timing_policy');
    Route::post('/campaigns/{campaign}/build-queue', [OutreachCampaignController::class, 'buildQueue'])->name('campaigns.build_queue');
    Route::post('/campaigns/{campaign}/send', [OutreachCampaignController::class, 'send'])->name('campaigns.send');
});
