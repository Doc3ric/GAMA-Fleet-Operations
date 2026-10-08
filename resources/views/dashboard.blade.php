<x-app-layout>
    @section('page-title', 'Dashboard')
    @section('page-subtitle', 'Operational Overview')

    <div class="space-y-5">

        {{-- KPI Cards Grid --}}
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">

            {{-- Today Report --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Today's Report</p>
                    <div class="rounded-xl bg-red-50 p-2">
                        <svg class="h-5 w-5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                </div>
                @if($todayReport)
                    <p class="text-2xl font-bold text-slate-900">{{ $todayReport->long_idling_records_count ?? $todayReport->longIdlingRecords->count() }} Records</p>
                    <div>
                        @if($todayReport->isCompleted())
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 text-xs font-semibold text-emerald-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>Completed
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                                <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>Draft
                            </span>
                        @endif
                    </div>
                @else
                    <div class="flex items-center gap-3">
                        <div class="flex h-10 w-10 items-center justify-center rounded-xl bg-amber-50 text-amber-500 font-bold text-lg border border-amber-100 select-none">?</div>
                        <div>
                            <p class="text-sm font-semibold text-slate-700">No report yet</p>
                            <p class="text-xs text-slate-400">Pending daily generation</p>
                        </div>
                    </div>
                @endif
            </div>

            {{-- Total Reports --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Reports</p>
                    <div class="rounded-xl bg-blue-50 p-2">
                        <svg class="h-5 w-5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 12h16.5m-16.5 3.75h16.5M3.75 19.5h16.5M5.625 4.5h12.75a1.875 1.875 0 0 1 0 3.75H5.625a1.875 1.875 0 0 1 0-3.75Z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ number_format($totalReports) }}</p>
                <p class="text-xs text-slate-400 font-medium">Long Idling Reports</p>
            </div>

            {{-- Total Records --}}
            <div class="rounded-2xl bg-white shadow-sm border border-slate-200 p-5 flex flex-col gap-3">
                <div class="flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-slate-400">Total Records</p>
                    <div class="rounded-xl bg-emerald-50 p-2">
                        <svg class="h-5 w-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-slate-900">{{ number_format($totalRecords) }}</p>
                <p class="text-xs text-slate-400 font-medium">Idling Events Logged</p>
            </div>

            {{-- Quick Action --}}
            <div class="rounded-2xl bg-[#1e3a5f] shadow-sm p-5 flex flex-col justify-between relative overflow-hidden">
                <div class="absolute -top-4 -right-4 h-24 w-24 rounded-full bg-blue-500/10 pointer-events-none"></div>
                <div class="absolute -bottom-6 -left-2 h-20 w-20 rounded-full bg-blue-600/10 pointer-events-none"></div>
                <div class="relative flex items-center justify-between">
                    <p class="text-xs font-semibold uppercase tracking-widest text-blue-300">Quick Action</p>
                    @if(!$todayReport)
                        <span class="rounded-full bg-red-500 px-2 py-0.5 text-[10px] font-bold text-white tracking-wide">REQUIRED</span>
                    @endif
                </div>
                @if($todayReport)
                    <div class="relative mt-2">
                        <p class="text-sm font-semibold text-white">Today's report is ready</p>
                        <a href="{{ route('reports.show', $todayReport) }}"
                           class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#1e3a5f] hover:bg-blue-50 transition-colors shadow-sm">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" /></svg>
                            Open Report
                        </a>
                    </div>
                @else
                    <div class="relative mt-2">
                        <p class="text-sm font-semibold text-slate-200">No report for today yet</p>
                        <a href="{{ route('reports.create') }}"
                           class="mt-3 inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2.5 text-sm font-bold text-[#1e3a5f] hover:bg-blue-50 transition-colors shadow-sm w-full justify-center">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                            + Create Today's Report
                        </a>
                    </div>
                @endif
            </div>

        </div>

        {{-- ===== MY WORK ===== --}}
        <div class="rounded-xl bg-white shadow-sm border border-slate-200 overflow-hidden"
             x-data="{
                showQuickTask: false,
                qtTitle: '',
                qtDue: '',
                submitting: false
             }">

            {{-- Section Header --}}
            <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100">
                <div class="flex items-center gap-2.5">
                    <div class="h-7 w-7 rounded-lg bg-blue-50 border border-blue-100 flex items-center justify-center text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                        </svg>
                    </div>
                    <h2 class="text-sm font-bold text-slate-800">My Work</h2>
                </div>
                <div class="flex items-center gap-2">
                    <button type="button" @click="showQuickTask = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-2.5 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-xs cursor-pointer">
                        <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Quick Task
                    </button>
                    <a href="{{ route('work-tasks.calendar') }}" class="text-xs font-semibold text-slate-600 hover:text-slate-900 transition-colors">Calendar</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('work-tasks.accomplishments') }}" class="text-xs font-semibold text-emerald-600 hover:text-emerald-700 transition-colors">Accomplishments</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('work-notes.index') }}" class="text-xs font-semibold text-amber-600 hover:text-amber-700 transition-colors">Notes</a>
                    <span class="text-slate-300">|</span>
                    <a href="{{ route('work-tasks.index') }}" class="text-xs font-semibold text-blue-600 hover:text-blue-700 transition-colors">View all &rarr;</a>
                </div>
            </div>

            {{-- Summary Badges --}}
            <div class="flex flex-wrap gap-2.5 px-5 py-2.5 border-b border-slate-100 bg-slate-50/50">
                <a href="{{ route('work-tasks.index', ['status' => 'overdue']) }}" class="flex items-center gap-1.5 hover:opacity-80">
                    <span class="inline-flex items-center gap-1 rounded-full bg-red-50 border border-red-200 px-2.5 py-0.5 text-xs font-semibold text-red-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-red-500"></span>
                        Overdue: {{ $workOverdueCount }}
                    </span>
                </a>
                <a href="{{ route('work-tasks.index', ['status' => 'due_today']) }}" class="flex items-center gap-1.5 hover:opacity-80">
                    <span class="inline-flex items-center gap-1 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                        Due Today: {{ $workDueTodayCount }}
                    </span>
                </a>
                <a href="{{ route('work-tasks.index', ['status' => 'in_progress']) }}" class="flex items-center gap-1.5 hover:opacity-80">
                    <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-500"></span>
                        In Progress: {{ $workInProgressCount }}
                    </span>
                </a>
                <a href="{{ route('work-tasks.index') }}" class="flex items-center gap-1.5 hover:opacity-80">
                    <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-semibold text-slate-600">
                        <span class="h-1.5 w-1.5 rounded-full bg-slate-400"></span>
                        Upcoming: {{ $workUpcomingCount }}
                    </span>
                </a>
            </div>

            {{-- Task List --}}
            <div class="divide-y divide-slate-100">
                @forelse($dashboardTasks as $dashTask)
                    <div class="flex items-start justify-between gap-4 px-5 py-3 hover:bg-slate-50/70 transition-colors {{ $dashTask->is_overdue ? 'bg-red-50/15' : '' }}">
                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2 flex-wrap">
                                <a href="{{ route('work-tasks.show', $dashTask) }}" class="text-xs font-bold text-slate-800 hover:text-blue-600">
                                    {{ $dashTask->title }}
                                </a>
                                @if($dashTask->is_overdue)
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-red-100 text-red-800">
                                        OVERDUE
                                    </span>
                                @elseif($dashTask->is_due_today)
                                    <span class="inline-flex items-center px-1.5 py-0.2 rounded text-[10px] font-bold bg-amber-100 text-amber-800">
                                        DUE TODAY
                                    </span>
                                @endif
                                @if($dashTask->category)
                                    <span class="text-[10px] text-slate-400">
                                        &bull; {{ $dashTask->category }}
                                    </span>
                                @endif
                            </div>
                            <div class="mt-1 flex flex-wrap items-center gap-3 text-xs text-slate-500">
                                @if($dashTask->due_date)
                                    <span class="{{ $dashTask->is_overdue ? 'text-red-600 font-semibold' : '' }}">
                                        Due: {{ $dashTask->due_date->format('M d, Y') }}
                                    </span>
                                @endif
                                @if($dashTask->next_action)
                                    <span class="flex items-center gap-1 text-slate-700">
                                        <svg class="h-3 w-3 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                        </svg>
                                        <span class="font-medium truncate max-w-xs">{{ $dashTask->next_action }}</span>
                                    </span>
                                @endif
                            </div>
                        </div>
                        <div class="flex items-center gap-1.5 shrink-0">
                            <a href="{{ route('work-tasks.show', $dashTask) }}"
                               class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                                <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                </svg>
                                <span>Open</span>
                            </a>
                            @if(!$dashTask->isCompleted())
                                <form method="POST" action="{{ route('work-tasks.complete', $dashTask) }}">
                                    @csrf
                                    @method('PATCH')
                                    <button type="submit"
                                            class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-emerald-600 text-xs font-semibold text-white hover:bg-emerald-700 transition-colors shadow-2xs cursor-pointer">
                                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                        </svg>
                                        <span>Done</span>
                                    </button>
                                </form>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="px-5 py-8 text-center">
                        <p class="text-xs font-semibold text-slate-600">All caught up!</p>
                        <p class="text-[11px] text-slate-400 mt-0.5">No pending work tasks at this time.</p>
                        <a href="{{ route('work-tasks.create') }}"
                           class="mt-2.5 inline-flex items-center gap-1 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-semibold text-white hover:bg-blue-700 transition-colors shadow-xs">
                            + Add Task
                        </a>
                    </div>
                @endforelse
            </div>

            {{-- Quick Task Modal --}}
            <div x-show="showQuickTask"
                 x-transition:enter="ease-out duration-150"
                 x-transition:enter-start="opacity-0"
                 x-transition:enter-end="opacity-100"
                 x-transition:leave="ease-in duration-100"
                 x-transition:leave-start="opacity-100"
                 x-transition:leave-end="opacity-0"
                 class="fixed inset-0 z-50 flex items-center justify-center px-4"
                 style="display: none;">
                <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showQuickTask = false"></div>
                <div class="relative bg-white rounded-xl shadow-lg w-full max-w-sm border border-slate-200" @click.stop>
                    <div class="flex items-center justify-between px-4 py-3 border-b border-slate-100">
                        <h3 class="text-xs font-bold text-slate-800 uppercase tracking-wider">Quick Task</h3>
                        <button type="button" @click="showQuickTask = false" class="text-slate-400 hover:text-slate-600 text-sm">✕</button>
                    </div>
                    <form method="POST" action="{{ route('work-tasks.store') }}" @submit="submitting = true">
                        @csrf
                        <input type="hidden" name="status" value="pending">
                        <input type="hidden" name="priority" value="normal">
                        <input type="hidden" name="sort_order" value="0">
                        <div class="p-4 space-y-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                    Task Title <span class="text-red-500">*</span>
                                </label>
                                <input type="text" name="title" x-model="qtTitle" required
                                       placeholder="e.g. Check CV-05 GPS signal..."
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                    Due Date <span class="text-slate-400 font-normal normal-case">(optional)</span>
                                </label>
                                <input type="date" name="due_date" x-model="qtDue"
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            </div>
                        </div>
                        <div class="flex items-center justify-end gap-2 px-4 py-3 border-t border-slate-100 bg-slate-50">
                            <button type="button" @click="showQuickTask = false; qtTitle = ''; qtDue = ''"
                                    class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="submitting || !qtTitle.trim()"
                                    :class="(submitting || !qtTitle.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                    class="px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg shadow-xs cursor-pointer">
                                <span x-show="!submitting">Save</span>
                                <span x-show="submitting">Saving...</span>
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
        {{-- ===== END MY WORK ===== --}}

        {{-- Fuel Consumption Benchmark Banner --}}
        <div class="rounded-2xl bg-white border border-slate-200 p-5 shadow-xs flex flex-col md:flex-row items-start md:items-center justify-between gap-4">
            <div class="flex items-center gap-3.5">
                <div class="h-11 w-11 rounded-xl bg-emerald-50 text-emerald-600 flex items-center justify-center shrink-0 border border-emerald-100">
                    <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.362 5.214A8.252 8.252 0 0 1 12 21 8.25 8.25 0 0 1 6.038 7.047 8.287 8.287 0 0 0 9 9.601a8.983 8.983 0 0 1 3.361-6.867 8.21 8.21 0 0 0 3 2.48Z" />
                    </svg>
                </div>
                <div>
                    <div class="flex items-center gap-2">
                        <h3 class="text-sm font-bold text-slate-800">Average Fuel Consumption</h3>
                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                            Full-Tank Benchmark
                        </span>
                    </div>
                    <p class="text-xs text-slate-500 mt-0.5">
                        @if($fuelTestCount > 0)
                            Fleet Average: <strong class="text-emerald-700 font-mono">{{ number_format($fleetAvgKmL, 2) }} km/L</strong> across {{ $fuelTestCount }} test(s).
                            @if($latestFuelTest)
                                Latest: {{ $latestFuelTest->vehicle?->equipment_code ?? 'Vehicle' }} at <strong class="text-slate-800 font-mono">{{ number_format($latestFuelTest->average_fuel_consumption, 2) }} km/L</strong>.
                            @endif
                        @else
                            No fuel consumption tests recorded yet. Run a test to establish baseline vehicle fuel economy.
                        @endif
                    </p>
                </div>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                <a href="{{ route('fuel-consumption.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-bold text-slate-700 hover:bg-slate-50 transition-colors shadow-2xs">
                    View Fuel Tests &rarr;
                </a>
                <a href="{{ route('fuel-consumption.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white px-3.5 py-2 text-xs font-bold transition-colors shadow-2xs">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" /></svg>
                    + Record Fuel Test
                </a>
            </div>
        </div>

        {{-- Fuel PO & Consumption Overview Graph Chart --}}
        <div class="rounded-2xl bg-white dark:bg-slate-900 shadow-sm border border-slate-200 dark:border-slate-800 overflow-hidden p-5 sm:p-6 space-y-5"
             x-data="fuelPoDashboardChart(@js($fuelChartData))"
             x-init="initChart()">

            {{-- Card Header --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
                <div class="flex items-center gap-3">
                    <div class="h-10 w-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0 border border-blue-100 dark:border-blue-900">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.75" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 0 1 3 19.875v-6.75ZM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V8.625ZM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 0 1-1.125-1.125V4.125Z" />
                        </svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <h2 class="text-sm sm:text-base font-bold text-slate-900 dark:text-white">Fuel PO & Consumption Overview</h2>
                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                                Operations Trend
                            </span>
                        </div>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                            Daily fuel allocation (Liters) vs total logged road distance (KM)
                        </p>
                    </div>
                </div>

                {{-- Range Toggles & Quick Actions --}}
                <div class="flex items-center gap-2.5 flex-wrap">
                    {{-- 7D / 14D / 30D Filter Pill --}}
                    <div class="inline-flex rounded-xl p-1 bg-slate-100 dark:bg-slate-800 border border-slate-200 dark:border-slate-700">
                        <button type="button"
                                @click="setPeriod('7d')"
                                :class="period === '7d' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs transition cursor-pointer">
                            7 Days
                        </button>
                        <button type="button"
                                @click="setPeriod('14d')"
                                :class="period === '14d' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs transition cursor-pointer">
                            14 Days
                        </button>
                        <button type="button"
                                @click="setPeriod('30d')"
                                :class="period === '30d' ? 'bg-white dark:bg-slate-900 text-blue-600 dark:text-blue-400 shadow-2xs font-bold' : 'text-slate-600 dark:text-slate-400 font-medium hover:text-slate-900 dark:hover:text-white'"
                                class="px-3 py-1 rounded-lg text-xs transition cursor-pointer">
                            30 Days
                        </button>
                    </div>

                    <a href="{{ route('fuel-po.index') }}"
                       class="inline-flex items-center gap-1 rounded-xl border border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200 hover:bg-slate-50 dark:hover:bg-slate-700/60 transition shadow-2xs">
                        <span>Checklist</span>
                        <svg class="h-3 w-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>

            {{-- Period Mini KPI Summary Cards --}}
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
                <div class="rounded-xl border border-blue-100 dark:border-blue-900/60 bg-blue-50/40 dark:bg-blue-950/20 p-3.5 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-blue-700 dark:text-blue-300">Fuel Required</span>
                    <div class="mt-1 flex items-baseline gap-1">
                        <span class="text-xl sm:text-2xl font-black font-mono text-blue-900 dark:text-blue-100" x-text="formatNum(activeData.total_liters)"></span>
                        <span class="text-xs font-semibold text-blue-600 dark:text-blue-400">Liters</span>
                    </div>
                    <span class="text-[10px] text-blue-600/80 dark:text-blue-400/80 font-medium" x-text="'Avg ' + activeData.avg_daily_liters + ' L/day'"></span>
                </div>

                <div class="rounded-xl border border-emerald-100 dark:border-emerald-900/60 bg-emerald-50/40 dark:bg-emerald-950/20 p-3.5 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700 dark:text-emerald-300">Total Distance</span>
                    <div class="mt-1 flex items-baseline gap-1">
                        <span class="text-xl sm:text-2xl font-black font-mono text-emerald-900 dark:text-emerald-100" x-text="formatNum(activeData.total_distance)"></span>
                        <span class="text-xs font-semibold text-emerald-600 dark:text-emerald-400">KM</span>
                    </div>
                    <span class="text-[10px] text-emerald-600/80 dark:text-emerald-400/80 font-medium">Logged road distance</span>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 p-3.5 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Period Efficiency</span>
                    <div class="mt-1 flex items-baseline gap-1">
                        <span class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-slate-100" x-text="activeData.avg_efficiency"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">km/L</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium">Distance &divide; Liters</span>
                </div>

                <div class="rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-50/60 dark:bg-slate-800/40 p-3.5 flex flex-col justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400">Fuel PO Trips</span>
                    <div class="mt-1 flex items-baseline gap-1">
                        <span class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-slate-100" x-text="activeData.total_count"></span>
                        <span class="text-xs font-semibold text-slate-500 dark:text-slate-400">records</span>
                    </div>
                    <span class="text-[10px] text-slate-400 dark:text-slate-500 font-medium" x-text="'Across ' + labelText"></span>
                </div>
            </div>

            {{-- Chart Canvas --}}
            <div class="relative w-full pt-2">
                <div class="h-72 w-full">
                    <canvas id="fuelPoTrendChart"></canvas>
                </div>

                {{-- Empty Indicator if all zeros --}}
                <div x-show="activeData.total_count === 0" x-cloak
                     class="absolute inset-0 bg-white/80 dark:bg-slate-900/80 backdrop-blur-2xs flex flex-col items-center justify-center text-center p-4 rounded-xl">
                    <div class="h-10 w-10 rounded-full bg-slate-100 dark:bg-slate-800 flex items-center justify-center text-slate-400 mb-2">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <p class="text-xs font-bold text-slate-700 dark:text-slate-200">No Fuel PO Trips In Selected Period</p>
                    <p class="text-[11px] text-slate-400 mt-0.5">Create itineraries in Fuel PO Checklist to view live fuel consumption trends.</p>
                    <a href="{{ route('fuel-po.create') }}"
                       class="mt-3 inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3 py-1.5 text-xs font-bold text-white hover:bg-blue-700 transition">
                        + Create First Fuel PO
                    </a>
                </div>
            </div>

            {{-- Legend Footer --}}
            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-100 dark:border-slate-800 text-xs text-slate-500 dark:text-slate-400">
                <div class="flex items-center gap-4">
                    <div class="flex items-center gap-1.5">
                        <span class="h-3 w-3 rounded-sm bg-blue-600"></span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">Fuel PO Required (Liters)</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <span class="h-1 w-3 rounded-full bg-emerald-500"></span>
                        <span class="font-medium text-slate-700 dark:text-slate-300">Total Distance (KM)</span>
                    </div>
                </div>

                <a href="{{ route('fuel-po.index') }}"
                   class="inline-flex items-center gap-1 font-semibold text-blue-600 dark:text-blue-400 hover:underline">
                    <span>Manage all Fuel PO entries</span>
                    <span aria-hidden="true">&rarr;</span>
                </a>
            </div>

        </div>

    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.7/dist/chart.umd.min.js"></script>
    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('fuelPoDashboardChart', (chartData) => ({
                period: '14d',
                data: chartData,
                chartInstance: null,

                get activeData() {
                    return this.data[this.period] || this.data['14d'];
                },

                get labelText() {
                    return this.period === '7d' ? 'last 7 days' : (this.period === '14d' ? 'last 14 days' : 'last 30 days');
                },

                formatNum(val) {
                    if (!val) return '0';
                    return Number(val).toLocaleString(undefined, { minimumFractionDigits: 0, maximumFractionDigits: 1 });
                },

                setPeriod(newPeriod) {
                    this.period = newPeriod;
                    this.updateChart();
                },

                isDarkMode() {
                    return document.documentElement.classList.contains('dark');
                },

                initChart() {
                    this.$nextTick(() => {
                        const ctx = document.getElementById('fuelPoTrendChart');
                        if (!ctx || typeof Chart === 'undefined') return;

                        const dark = this.isDarkMode();
                        const gridColor = dark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)';
                        const textColor = dark ? '#94a3b8' : '#64748b';

                        const current = this.activeData;

                        this.chartInstance = new Chart(ctx, {
                            type: 'bar',
                            data: {
                                labels: current.labels,
                                datasets: [
                                    {
                                        type: 'bar',
                                        label: 'Fuel Required',
                                        data: current.liters,
                                        backgroundColor: dark ? 'rgba(59, 130, 246, 0.85)' : 'rgba(37, 99, 235, 0.85)',
                                        hoverBackgroundColor: dark ? '#60a5fa' : '#1d4ed8',
                                        borderRadius: 6,
                                        yAxisID: 'yLiters',
                                        order: 2,
                                        maxBarThickness: 32,
                                    },
                                    {
                                        type: 'line',
                                        label: 'Total Distance',
                                        data: current.distance,
                                        borderColor: dark ? '#34d399' : '#059669',
                                        backgroundColor: dark ? 'rgba(16, 185, 129, 0.12)' : 'rgba(16, 185, 129, 0.08)',
                                        borderWidth: 2.5,
                                        tension: 0.35,
                                        pointRadius: current.labels.length > 14 ? 2 : 4,
                                        pointHoverRadius: 6,
                                        pointBackgroundColor: dark ? '#34d399' : '#059669',
                                        fill: true,
                                        yAxisID: 'yDistance',
                                        order: 1,
                                    }
                                ]
                            },
                            options: {
                                responsive: true,
                                maintainAspectRatio: false,
                                interaction: {
                                    mode: 'index',
                                    intersect: false,
                                },
                                plugins: {
                                    legend: {
                                        display: false,
                                    },
                                    tooltip: {
                                        backgroundColor: dark ? '#0f172a' : '#1e293b',
                                        titleColor: '#ffffff',
                                        bodyColor: '#e2e8f0',
                                        borderColor: dark ? '#334155' : '#475569',
                                        borderWidth: 1,
                                        padding: 10,
                                        boxPadding: 4,
                                        usePointStyle: true,
                                        callbacks: {
                                            label: function(context) {
                                                const label = context.dataset.label || '';
                                                const val = Number(context.parsed.y).toLocaleString(undefined, { maximumFractionDigits: 1 });
                                                if (context.datasetIndex === 0) {
                                                    return ` ⛽ ${label}: ${val} L`;
                                                }
                                                return ` 🛣️ ${label}: ${val} km`;
                                            }
                                        }
                                    }
                                },
                                scales: {
                                    x: {
                                        grid: {
                                            display: false,
                                        },
                                        ticks: {
                                            color: textColor,
                                            font: {
                                                size: 11,
                                                weight: '500',
                                            },
                                            maxRotation: 0,
                                        }
                                    },
                                    yLiters: {
                                        type: 'linear',
                                        display: true,
                                        position: 'left',
                                        beginAtZero: true,
                                        grid: {
                                            color: gridColor,
                                            drawBorder: false,
                                        },
                                        ticks: {
                                            color: textColor,
                                            font: {
                                                size: 10,
                                            },
                                            callback: (v) => v + ' L',
                                        },
                                        title: {
                                            display: true,
                                            text: 'Liters (L)',
                                            color: dark ? '#60a5fa' : '#2563eb',
                                            font: { size: 10, weight: 'bold' },
                                        }
                                    },
                                    yDistance: {
                                        type: 'linear',
                                        display: true,
                                        position: 'right',
                                        beginAtZero: true,
                                        grid: {
                                            display: false,
                                        },
                                        ticks: {
                                            color: textColor,
                                            font: {
                                                size: 10,
                                            },
                                            callback: (v) => v + ' km',
                                        },
                                        title: {
                                            display: true,
                                            text: 'Distance (KM)',
                                            color: dark ? '#34d399' : '#059669',
                                            font: { size: 10, weight: 'bold' },
                                        }
                                    }
                                }
                            }
                        });

                        // Theme change listener
                        const observer = new MutationObserver(() => {
                            this.updateTheme();
                        });
                        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class'] });
                    });
                },

                updateChart() {
                    if (!this.chartInstance) return;
                    const current = this.activeData;
                    this.chartInstance.data.labels = current.labels;
                    this.chartInstance.data.datasets[0].data = current.liters;
                    this.chartInstance.data.datasets[1].data = current.distance;
                    this.chartInstance.data.datasets[1].pointRadius = current.labels.length > 14 ? 2 : 4;
                    this.chartInstance.update();
                },

                updateTheme() {
                    if (!this.chartInstance) return;
                    const dark = this.isDarkMode();
                    const gridColor = dark ? 'rgba(51, 65, 85, 0.4)' : 'rgba(226, 232, 240, 0.8)';
                    const textColor = dark ? '#94a3b8' : '#64748b';

                    this.chartInstance.options.scales.x.ticks.color = textColor;
                    this.chartInstance.options.scales.yLiters.ticks.color = textColor;
                    this.chartInstance.options.scales.yLiters.grid.color = gridColor;
                    this.chartInstance.options.scales.yDistance.ticks.color = textColor;
                    this.chartInstance.update();
                }
            }));
        });
    </script>
    @endpush

</x-app-layout>