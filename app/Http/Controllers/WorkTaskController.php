<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkTaskRequest;
use App\Http\Requests\UpdateWorkTaskRequest;
use App\Models\WorkTask;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkTaskController extends Controller
{
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkTask::class);

        $userId = auth()->id();
        $query = WorkTask::where('user_id', $userId);

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('next_action', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('notes', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        // Status filter (supports status values or pseudo-status scopes)
        if ($status = $request->input('status')) {
            if ($status === 'overdue') {
                $query->overdue();
            } elseif ($status === 'due_today') {
                $query->dueToday();
            } elseif ($status === 'upcoming') {
                $query->upcoming();
            } else {
                $query->where('status', $status);
            }
        }

        // Priority filter
        if ($priority = $request->input('priority')) {
            $query->where('priority', $priority);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Default order: overdue first, then due today, in_progress, upcoming, priority, due date
        $query->orderByRaw("
            CASE
                WHEN status NOT IN ('completed', 'cancelled')
                     AND due_date IS NOT NULL
                     AND due_date < date('now') THEN 0
                WHEN status NOT IN ('completed', 'cancelled')
                     AND due_date IS NOT NULL
                     AND due_date = date('now') THEN 1
                WHEN status = 'in_progress' THEN 2
                WHEN status NOT IN ('completed', 'cancelled')
                     AND (due_date IS NULL OR due_date > date('now')) THEN 3
                ELSE 4
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
            ->orderBy('id');

        $tasks = $query->paginate(20)->withQueryString();

        // Metric counts for KPI filter cards
        $baseMetrics = fn () => WorkTask::where('user_id', $userId);
        $totalCount = (clone $baseMetrics())->count();
        $overdueCount = (clone $baseMetrics())->overdue()->count();
        $dueTodayCount = (clone $baseMetrics())->dueToday()->count();
        $inProgressCount = (clone $baseMetrics())->inProgress()->count();
        $completedCount = (clone $baseMetrics())->completed()->count();

        // Available user categories with task counts
        $categories = WorkTask::where('user_id', $userId)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('work-tasks.index', compact(
            'tasks',
            'categories',
            'totalCount',
            'overdueCount',
            'dueTodayCount',
            'inProgressCount',
            'completedCount'
        ));
    }

    /**
     * Display a calendar view of the user's work tasks.
     */
    public function calendar(Request $request): View
    {
        Gate::authorize('viewAny', WorkTask::class);

        $userId = auth()->id();

        // Determine target month (defaults to current month)
        $monthParam = $request->input('month');
        try {
            $currentDate = $monthParam
                ? Carbon::createFromFormat('Y-m', $monthParam)->startOfMonth()
                : Carbon::now()->startOfMonth();
        } catch (\Exception $e) {
            $currentDate = Carbon::now()->startOfMonth();
        }

        $startOfMonth = $currentDate->copy()->startOfMonth();
        $endOfMonth = $currentDate->copy()->endOfMonth();

        // 7-day grid bounds: start on Sunday, end on Saturday
        $startOfGrid = $startOfMonth->copy()->startOfWeek(Carbon::SUNDAY);
        $endOfGrid = $endOfMonth->copy()->endOfWeek(Carbon::SATURDAY);

        // Navigation parameters
        $prevMonth = $currentDate->copy()->subMonth()->format('Y-m');
        $nextMonth = $currentDate->copy()->addMonth()->format('Y-m');
        $todayMonth = Carbon::now()->format('Y-m');
        $monthTitle = $currentDate->format('F Y');

        // Only query tasks belonging to authenticated user with due_date within grid range
        $tasks = WorkTask::where('user_id', $userId)
            ->whereNotNull('due_date')
            ->whereBetween('due_date', [
                $startOfGrid->format('Y-m-d'),
                $endOfGrid->format('Y-m-d'),
            ])
            ->orderBy('due_date')
            ->orderByRaw("
                CASE priority
                    WHEN 'urgent' THEN 0
                    WHEN 'high' THEN 1
                    WHEN 'normal' THEN 2
                    WHEN 'low' THEN 3
                    ELSE 4
                END
            ")
            ->get()
            ->groupBy(fn ($task) => $task->due_date->format('Y-m-d'));

        // Build the grid days array
        $calendarDays = [];
        $iter = $startOfGrid->copy();

        while ($iter->lte($endOfGrid)) {
            $dateStr = $iter->format('Y-m-d');
            $calendarDays[] = [
                'date' => $dateStr,
                'day' => $iter->day,
                'is_current_month' => $iter->month === $currentDate->month,
                'is_today' => $iter->isToday(),
                'is_past' => $iter->lt(Carbon::today()),
                'tasks' => $tasks->get($dateStr, collect()),
            ];
            $iter->addDay();
        }

        // Metric counts for quick badges
        $baseMetrics = fn () => WorkTask::where('user_id', $userId);
        $totalCount = (clone $baseMetrics())->count();
        $overdueCount = (clone $baseMetrics())->overdue()->count();
        $dueTodayCount = (clone $baseMetrics())->dueToday()->count();
        $inProgressCount = (clone $baseMetrics())->inProgress()->count();
        $completedCount = (clone $baseMetrics())->completed()->count();

        // Distinct categories for quick create modal
        $categories = WorkTask::where('user_id', $userId)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('work-tasks.calendar', compact(
            'currentDate',
            'monthTitle',
            'prevMonth',
            'nextMonth',
            'todayMonth',
            'calendarDays',
            'totalCount',
            'overdueCount',
            'dueTodayCount',
            'inProgressCount',
            'completedCount',
            'categories'
        ));
    }

    public function create(): View
    {
        Gate::authorize('create', WorkTask::class);

        $categories = WorkTask::where('user_id', auth()->id())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('work-tasks.create', compact('categories'));
    }

    public function store(StoreWorkTaskRequest $request): RedirectResponse
    {
        $data = $request->validated();

        WorkTask::create([
            ...$data,
            'user_id' => auth()->id(),
            'sort_order' => $data['sort_order'] ?? 0,
        ]);

        if ($request->input('redirect_to') === 'calendar') {
            $month = $request->input('month');

            return redirect()
                ->route('work-tasks.calendar', $month ? ['month' => $month] : [])
                ->with('success', 'Task created successfully.');
        }

        return redirect()
            ->route('work-tasks.index')
            ->with('success', 'Task created successfully.');
    }

    public function show(WorkTask $workTask): View
    {
        Gate::authorize('view', $workTask);

        return view('work-tasks.show', ['task' => $workTask]);
    }

    public function edit(WorkTask $workTask): View
    {
        Gate::authorize('update', $workTask);

        $categories = WorkTask::where('user_id', auth()->id())
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('work-tasks.edit', ['task' => $workTask, 'categories' => $categories]);
    }

    public function update(UpdateWorkTaskRequest $request, WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('update', $workTask);

        $workTask->update($request->validated());

        return redirect()
            ->route('work-tasks.show', $workTask)
            ->with('success', 'Task updated successfully.');
    }

    public function destroy(WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('delete', $workTask);

        $workTask->delete();

        return redirect()
            ->route('work-tasks.index')
            ->with('success', 'Task deleted.');
    }

    /**
     * Display a full accomplishment history of the user's completed work.
     */
    public function accomplishments(Request $request): View
    {
        Gate::authorize('viewAny', WorkTask::class);

        $userId = auth()->id();

        $query = WorkTask::where('user_id', $userId)
            ->where('status', WorkTask::STATUS_COMPLETED);

        // Date range filter: all, today, this_week, this_month
        $timeframe = $request->input('timeframe', 'all');
        if ($timeframe === 'today') {
            $query->whereDate('completed_at', Carbon::today());
        } elseif ($timeframe === 'this_week') {
            $query->whereBetween('completed_at', [
                Carbon::now()->startOfWeek(),
                Carbon::now()->endOfWeek(),
            ]);
        } elseif ($timeframe === 'this_month') {
            $query->whereBetween('completed_at', [
                Carbon::now()->startOfMonth(),
                Carbon::now()->endOfMonth(),
            ]);
        }

        // Category filter
        if ($category = $request->input('category')) {
            $query->where('category', $category);
        }

        // Search filter
        if ($search = $request->input('search')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")
                    ->orWhere('resolution_notes', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%");
            });
        }

        $completedTasks = $query->orderByDesc('completed_at')->paginate(20)->withQueryString();

        // Metrics for accomplishment KPI cards
        $base = fn () => WorkTask::where('user_id', $userId)->where('status', WorkTask::STATUS_COMPLETED);
        $totalCompleted = (clone $base())->count();
        $completedToday = (clone $base())->completedToday()->count();
        $completedThisWeek = (clone $base())->completedThisWeek()->count();
        $completedThisMonth = (clone $base())->completedThisMonth()->count();

        // Calculate On-Time Rate (%)
        $allWithDue = (clone $base())->whereNotNull('due_date')->get();
        if ($allWithDue->count() > 0) {
            $onTimeCount = $allWithDue->filter(fn ($t) => $t->isCompletedOnTime())->count();
            $onTimeRate = round(($onTimeCount / $allWithDue->count()) * 100);
        } else {
            $onTimeRate = 100;
        }

        // Categories available
        $categories = WorkTask::where('user_id', $userId)
            ->whereNotNull('category')
            ->distinct()
            ->orderBy('category')
            ->pluck('category');

        return view('work-tasks.accomplishments', compact(
            'completedTasks',
            'timeframe',
            'categories',
            'totalCompleted',
            'completedToday',
            'completedThisWeek',
            'completedThisMonth',
            'onTimeRate'
        ));
    }

    /**
     * Mark a task as completed.
     */
    public function complete(Request $request, WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('complete', $workTask);

        $validated = $request->validate([
            'resolution_notes' => 'nullable|string|max:3000',
        ]);

        $workTask->update([
            'status' => WorkTask::STATUS_COMPLETED,
            'completed_at' => Carbon::now(),
            'resolution_notes' => $validated['resolution_notes'] ?? $workTask->resolution_notes,
        ]);

        return redirect()->back()->with('success', "Task \"{$workTask->title}\" marked as complete.");
    }

    /**
     * Reopen a completed task.
     */
    public function reopen(WorkTask $workTask): RedirectResponse
    {
        Gate::authorize('update', $workTask);

        $workTask->update([
            'status' => WorkTask::STATUS_PENDING,
            'completed_at' => null,
        ]);

        return redirect()->back()->with('success', "Task \"{$workTask->title}\" reopened.");
    }
}
