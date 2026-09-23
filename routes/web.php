<?php

use App\Http\Controllers\AdvancedItineraryController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DeviceController;
use App\Http\Controllers\DriverItineraryController;
use App\Http\Controllers\ExcelViewerController;
use App\Http\Controllers\FuelConsumptionController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\LongIdlingRecordController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\VehicleController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect()->route('dashboard'));

Route::middleware(['auth', 'verified'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('reports/template/download', [ReportController::class, 'downloadTemplate'])->name('reports.downloadTemplate');
    Route::resource('reports', ReportController::class);
    Route::patch('reports/{report}/complete', [ReportController::class, 'markComplete'])->name('reports.markComplete');
    Route::patch('reports/{report}/draft', [ReportController::class, 'markDraft'])->name('reports.markDraft');
    Route::get('reports/{report}/pdf', [ReportController::class, 'generatePdf'])->name('reports.generatePdf');
    Route::get('reports/{report}/excel', [ReportController::class, 'exportExcel'])->name('reports.exportExcel');
    Route::post('reports/{report}/import', [ReportController::class, 'importData'])->name('reports.importData');

    Route::post('reports/{report}/screenshots', [LongIdlingRecordController::class, 'uploadScreenshot'])->name('reports.uploadScreenshot');
    Route::post('long-idling-records/{record}/image', [LongIdlingRecordController::class, 'uploadImage'])->name('records.uploadImage');
    Route::delete('long-idling-records/{record}', [LongIdlingRecordController::class, 'destroy'])->name('records.destroy');

    // Vehicle Master List
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

    // Average Fuel Consumption
    Route::get('fuel-consumption/export/excel', [FuelConsumptionController::class, 'exportExcel'])->name('fuel-consumption.exportExcel');
    Route::get('fuel-consumption/export/pdf', [FuelConsumptionController::class, 'exportAllPdf'])->name('fuel-consumption.exportAllPdf');
    Route::get('fuel-consumption/{fuelConsumption}/pdf', [FuelConsumptionController::class, 'exportPdf'])->name('fuel-consumption.exportPdf')->whereNumber('fuelConsumption');
    Route::resource('fuel-consumption', FuelConsumptionController::class)->parameters(['fuel-consumption' => 'fuelConsumption']);

    // Excel Viewer
    Route::get('excel-viewer', [ExcelViewerController::class, 'index'])->name('excel-viewer.index');
    Route::get('excel-viewer/download/{fileId}', [ExcelViewerController::class, 'download'])->name('excel-viewer.download');

    // Advanced Itinerary
    Route::post('advanced-itineraries/{advanced_itinerary}/recalculate', [AdvancedItineraryController::class, 'recalculate'])->name('advanced-itineraries.recalculate');
    Route::get('advanced-itineraries/{advanced_itinerary}/pdf', [AdvancedItineraryController::class, 'exportPdf'])->name('advanced-itineraries.exportPdf');
    Route::resource('advanced-itineraries', AdvancedItineraryController::class);

});

require __DIR__.'/auth.php';
