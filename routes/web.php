<?php

use App\Http\Controllers\AdvancedItineraryController;
use App\Http\Controllers\AverageFuelConsumptionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DriverItineraryController;
use App\Http\Controllers\ExcelViewerController;
use App\Http\Controllers\FuelConsumptionController;
use App\Http\Controllers\FuelPoController;
use App\Http\Controllers\FuelPoImportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LongIdlingRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleController;
use App\Http\Controllers\WorkNoteController;
use App\Http\Controllers\WorkTaskController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('reports/range', [ReportController::class, 'rangeView'])->name('reports.range');
    Route::match(['get', 'post'], 'reports-pdf/range', [ReportController::class, 'generateRangePdf'])->name('reports.generateRangePdf');
    Route::get('reports/template/download', [ReportController::class, 'downloadTemplate'])->name('reports.downloadTemplate');
    Route::resource('reports', ReportController::class);
    Route::patch('reports/{report}/complete', [ReportController::class, 'markComplete'])->name('reports.markComplete');
    Route::patch('reports/{report}/draft', [ReportController::class, 'markDraft'])->name('reports.markDraft');
    Route::match(['get', 'post'], 'reports/{report}/pdf', [ReportController::class, 'generatePdf'])->name('reports.generatePdf');
    Route::get('reports/{report}/excel', [ReportController::class, 'exportExcel'])->name('reports.exportExcel');
    Route::post('reports/{report}/import', [ReportController::class, 'importData'])->name('reports.importData');

    Route::post('reports-screenshots/upload', [LongIdlingRecordController::class, 'uploadGenericScreenshot'])->name('reports.uploadGenericScreenshot');
    Route::post('reports/{report}/screenshots', [LongIdlingRecordController::class, 'uploadScreenshot'])->name('reports.uploadScreenshot');
    Route::post('long-idling-records/{record}/image', [LongIdlingRecordController::class, 'uploadImage'])->name('records.uploadImage');
    Route::delete('long-idling-records/{record}', [LongIdlingRecordController::class, 'destroy'])->name('records.destroy');

    // Vehicle Master List & Archive Bin
    Route::get('vehicles/archive-bin', [VehicleController::class, 'archive'])->name('vehicles.archive');
    Route::post('vehicles/archive/restore-all', [VehicleController::class, 'restoreAll'])->name('vehicles.restoreAll');
    Route::delete('vehicles/archive/empty-bin', [VehicleController::class, 'emptyBin'])->name('vehicles.emptyBin');
    Route::post('vehicles/{id}/restore', [VehicleController::class, 'restore'])->name('vehicles.restore');
    Route::delete('vehicles/{id}/force-delete', [VehicleController::class, 'forceDelete'])->name('vehicles.forceDelete');
    Route::get('vehicles/template/download', [VehicleController::class, 'downloadTemplate'])->name('vehicles.downloadTemplate');
    Route::post('vehicles/import', [VehicleController::class, 'importData'])->name('vehicles.importData');
    Route::resource('vehicles', VehicleController::class);
    Route::post('vehicles/{vehicle}/image', [VehicleController::class, 'uploadImage'])->name('vehicles.uploadImage');

    // GPS Device Monitoring
    Route::get('devices/template/download', [DeviceController::class, 'downloadTemplate'])->name('devices.downloadTemplate');
    Route::get('devices/pdf', [DeviceController::class, 'generatePdf'])->name('devices.pdf');
    Route::get('devices/export', [DeviceController::class, 'export'])->name('devices.export');
    Route::post('devices/import', [DeviceController::class, 'importData'])->name('devices.importData');
    Route::post('devices/sync-vehicles', [DeviceController::class, 'syncVehicles'])->name('devices.syncVehicles');
    Route::resource('devices', DeviceController::class);

    // Location Directory
    Route::get('locations/map', [LocationController::class, 'map'])->name('locations.map');
    Route::get('locations/search-places', [LocationController::class, 'searchPlaces'])->name('locations.searchPlaces');
    Route::post('locations/resolve-coordinates', [LocationController::class, 'resolveCoordinates'])->name('locations.resolveCoordinates');
    Route::get('locations/template/download', [LocationController::class, 'downloadTemplate'])->name('locations.downloadTemplate');
    Route::get('locations/export/excel', [LocationController::class, 'exportExcel'])->name('locations.exportExcel');
    Route::post('locations/import', [LocationController::class, 'importData'])->name('locations.importData');
    Route::post('locations/calculate-distance', [LocationController::class, 'calculateDistance'])->name('locations.calculateDistance');
    Route::post('locations/{location}/aliases', [LocationController::class, 'storeAlias'])->name('locations.aliases.store');
    Route::delete('locations/{location}/aliases/{alias}', [LocationController::class, 'destroyAlias'])->name('locations.aliases.destroy');
    Route::resource('locations', LocationController::class);

    // Driver Itinerary Management & Reporting
    Route::get('itineraries', [DriverItineraryController::class, 'index'])->name('itineraries.index');
    Route::get('itineraries/report', [DriverItineraryController::class, 'report'])->name('itineraries.report');
    Route::get('itineraries/export/pdf', [DriverItineraryController::class, 'exportPdf'])->name('itineraries.exportPdf');
    Route::get('itineraries/export/excel', [DriverItineraryController::class, 'exportExcel'])->name('itineraries.exportExcel');
    Route::post('itineraries/{trip}/resolve-location', [DriverItineraryController::class, 'resolveLocation'])->name('itineraries.resolveLocation')->whereNumber('trip');
    Route::get('itineraries/{trip}', [DriverItineraryController::class, 'show'])->name('itineraries.show')->whereNumber('trip');

    // Average Fuel Consumption (Dedicated List & Rate Manager)
    Route::get('average-fuel-consumption', [AverageFuelConsumptionController::class, 'index'])->name('average-fuel-consumption.index');
    Route::post('average-fuel-consumption', [AverageFuelConsumptionController::class, 'store'])->name('average-fuel-consumption.store');
    Route::put('average-fuel-consumption/{vehicle}', [AverageFuelConsumptionController::class, 'update'])->name('average-fuel-consumption.update');
    Route::delete('average-fuel-consumption/{vehicle}', [AverageFuelConsumptionController::class, 'destroy'])->name('average-fuel-consumption.destroy');

    // Full-Tank Fuel Consumption Testing
    Route::get('fuel-consumption/export/excel', [FuelConsumptionController::class, 'exportExcel'])->name('fuel-consumption.exportExcel');
    Route::get('fuel-consumption/export/pdf', [FuelConsumptionController::class, 'exportAllPdf'])->name('fuel-consumption.exportAllPdf');
    Route::get('fuel-consumption/{fuelConsumption}/pdf', [FuelConsumptionController::class, 'exportPdf'])->name('fuel-consumption.exportPdf')->whereNumber('fuelConsumption');
    Route::resource('fuel-consumption', FuelConsumptionController::class)->parameters(['fuel-consumption' => 'fuelConsumption']);

    // Excel Viewer
    Route::get('excel-viewer', [ExcelViewerController::class, 'index'])->name('excel-viewer.index');
    Route::get('excel-viewer/download/{fileId}', [ExcelViewerController::class, 'download'])->name('excel-viewer.download');
    Route::get('excel-viewer/download/{fileId}/edited', [ExcelViewerController::class, 'downloadEdited'])->name('excel-viewer.download-edited');

    // Advanced Itinerary Management (Operations)
    Route::post('advanced-itineraries/{advanced_itinerary}/recalculate', [AdvancedItineraryController::class, 'recalculate'])->name('advanced-itineraries.recalculate');
    Route::get('advanced-itineraries/{advanced_itinerary}/pdf', [AdvancedItineraryController::class, 'exportPdf'])->name('advanced-itineraries.exportPdf');
    Route::resource('advanced-itineraries', AdvancedItineraryController::class);

    // Fuel PO Checklist Workflow (Purchasing)
    Route::get('fuel-po/export/excel', [FuelPoController::class, 'exportExcel'])->name('fuel-po.export-excel');
    Route::get('fuel-po/export/pdf', [FuelPoController::class, 'exportPdf'])->name('fuel-po.export-pdf');
    Route::post('fuel-po/bulk-checklist', [FuelPoController::class, 'bulkChecklist'])->name('fuel-po.bulk-checklist');
    Route::post('fuel-po/bulk-delete', [FuelPoController::class, 'bulkDelete'])->name('fuel-po.bulk-delete');
    Route::match(['patch', 'post'], 'fuel-po/{advanced_itinerary}/toggle-checklist', [FuelPoController::class, 'toggleChecklist'])->name('fuel-po.toggle-checklist');
    // Fuel PO Import Workflow (Weekly Itinerary Report)
    Route::get('fuel-po/import/template', [FuelPoImportController::class, 'downloadTemplate'])->name('fuel-po.import.template');
    Route::post('fuel-po/import/preview', [FuelPoImportController::class, 'preview'])->name('fuel-po.import.preview');
    Route::post('fuel-po/import/confirm', [FuelPoImportController::class, 'confirm'])->name('fuel-po.import.confirm');

    Route::get('fuel-po/{advanced_itinerary}/pdf', [FuelPoController::class, 'downloadSinglePdf'])->name('fuel-po.pdf');
    Route::resource('fuel-po', FuelPoController::class)->parameters(['fuel-po' => 'advanced_itinerary']);

    // My Work — Personal Work Workflow Assistant
    Route::get('work-tasks/calendar', [WorkTaskController::class, 'calendar'])->name('work-tasks.calendar');
    Route::get('work-tasks/accomplishments', [WorkTaskController::class, 'accomplishments'])->name('work-tasks.accomplishments');
    Route::patch('work-tasks/{work_task}/complete', [WorkTaskController::class, 'complete'])->name('work-tasks.complete');
    Route::patch('work-tasks/{work_task}/reopen', [WorkTaskController::class, 'reopen'])->name('work-tasks.reopen');
    Route::resource('work-tasks', WorkTaskController::class);

    // My Work — Operational Notes & Reminders
    Route::patch('work-notes/{work_note}/toggle-pin', [WorkNoteController::class, 'togglePin'])->name('work-notes.toggle-pin');
    Route::resource('work-notes', WorkNoteController::class)->only(['index', 'store', 'update', 'destroy']);

});

require __DIR__.'/auth.php';
