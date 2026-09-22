<?php

namespace App\Http\Controllers;

use App\Models\FuelConsumptionTest;
use App\Models\LongIdlingRecord;
use App\Models\Report;
use Carbon\Carbon;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(): View
    {
        $userId = auth()->id();
        $today = Carbon::today();

        $todayReport = Report::where('created_by', $userId)
            ->where('report_type', 'long_idling')
            ->whereDate('report_date', $today)
            ->with('longIdlingRecords')
            ->first();

        $totalReports = Report::where('created_by', $userId)->count();
        $totalRecords = LongIdlingRecord::whereHas('report', fn ($q) => $q->where('created_by', $userId))->count();
        $completedToday = Report::where('created_by', $userId)
            ->where('status', 'completed')
            ->whereDate('report_date', $today)
            ->count();

        $recentReports = Report::where('created_by', $userId)
            ->orderByDesc('report_date')
            ->withCount('longIdlingRecords')
            ->take(7)
            ->get();

        $fuelTestCount = FuelConsumptionTest::count();
        $fleetAvgKmL = $fuelTestCount > 0 ? (float) FuelConsumptionTest::avg('average_fuel_consumption') : 0.0;
        $latestFuelTest = FuelConsumptionTest::with(['vehicle', 'driver'])->orderByDesc('test_date')->orderByDesc('id')->first();

        return view('dashboard', compact(
            'todayReport',
            'totalReports',
            'totalRecords',
            'completedToday',
            'recentReports',
            'fuelTestCount',
            'fleetAvgKmL',
            'latestFuelTest',
        ));
    }
}
