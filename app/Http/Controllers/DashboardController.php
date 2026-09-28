<?php

namespace App\Http\Controllers;

use App\Models\FuelConsumptionTest;
use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Models\WorkTask;
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

        // ─── My Work Summary ──────────────────────────────────────────────
        $baseQuery = fn () => WorkTask::where('user_id', $userId);

        $workOverdueCount = (clone $baseQuery())->overdue()->count();
        $workDueTodayCount = (clone $baseQuery())->dueToday()->count();
        $workInProgressCount = (clone $baseQuery())->inProgress()->count();
        $workUpcomingCount = (clone $baseQuery())->upcoming()->count();

        // Top 5 most urgent tasks for the dashboard preview:
        // overdue → due today → in progress → upcoming, then by priority, then due_date
        $dashboardTasks = WorkTask::where('user_id', $userId)
            ->whereNotIn('status', [WorkTask::STATUS_COMPLETED, WorkTask::STATUS_CANCELLED])
            ->orderByRaw("
                CASE
                    WHEN status NOT IN ('completed', 'cancelled')
                         AND due_date IS NOT NULL
                         AND due_date < date('now') THEN 0
                    WHEN status NOT IN ('completed', 'cancelled')
                         AND due_date IS NOT NULL
                         AND due_date = date('now') THEN 1
                    WHEN status = 'in_progress' THEN 2
                    ELSE 3
                END
            ")
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('due_date')
            ->limit(5)
            ->get();

        return view('dashboard', compact(
            'todayReport',
            'totalReports',
            'totalRecords',
            'completedToday',
            'recentReports',
            'fuelTestCount',
            'fleetAvgKmL',
            'latestFuelTest',
            'workOverdueCount',
            'workDueTodayCount',
            'workInProgressCount',
            'workUpcomingCount',
            'dashboardTasks',
        ));
    }
}
