<?php

namespace App\Http\Controllers;

use App\Models\AdvancedItinerary;
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

        // ─── Fuel PO & Consumption Trend Series (30D, 14D, 7D) ──────────────
        $daysCount = 30;
        $endDate = Carbon::today();
        $startDate = $endDate->copy()->subDays($daysCount - 1);

        $itineraries = AdvancedItinerary::whereDate('itinerary_date', '>=', $startDate)
            ->whereDate('itinerary_date', '<=', $endDate)
            ->with('vehicle')
            ->get();

        $groupedByDate = $itineraries->groupBy(fn (AdvancedItinerary $it) => Carbon::parse($it->itinerary_date)->format('Y-m-d'));

        $fullSeries = [];
        for ($i = $daysCount - 1; $i >= 0; $i--) {
            $currDate = $endDate->copy()->subDays($i);
            $key = $currDate->format('Y-m-d');
            $dayItineraries = $groupedByDate->get($key, collect());

            $fuelLiters = (float) $dayItineraries->sum(fn (AdvancedItinerary $it) => $it->fuel_liters ?? 0.0);
            $distance = (float) $dayItineraries->sum(fn (AdvancedItinerary $it) => $it->total_distance ?? 0.0);
            $count = $dayItineraries->count();

            $fullSeries[] = [
                'date' => $key,
                'short_label' => $currDate->format('M d'),
                'day_name' => $currDate->format('D'),
                'fuel_liters' => round($fuelLiters, 2),
                'distance' => round($distance, 2),
                'count' => $count,
            ];
        }

        $buildPeriodData = function (int $sliceCount) use ($fullSeries): array {
            $slice = array_slice($fullSeries, -$sliceCount);
            $totalLiters = array_sum(array_column($slice, 'fuel_liters'));
            $totalDistance = array_sum(array_column($slice, 'distance'));
            $totalCount = array_sum(array_column($slice, 'count'));
            $avgEfficiency = $totalLiters > 0 ? round($totalDistance / $totalLiters, 2) : 0.0;

            return [
                'labels' => array_column($slice, 'short_label'),
                'liters' => array_column($slice, 'fuel_liters'),
                'distance' => array_column($slice, 'distance'),
                'total_liters' => round($totalLiters, 2),
                'total_distance' => round($totalDistance, 2),
                'total_count' => $totalCount,
                'avg_efficiency' => $avgEfficiency,
                'avg_daily_liters' => round($totalLiters / max(1, $sliceCount), 1),
            ];
        };

        $fuelChartData = [
            '7d' => $buildPeriodData(7),
            '14d' => $buildPeriodData(14),
            '30d' => $buildPeriodData(30),
        ];

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
            'fuelChartData',
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
