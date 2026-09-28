<x-app-layout>
    @section('page-title', 'My Work')
    @section('breadcrumb', 'Fleet Management / My Work / Accomplishments')

    <div class="space-y-5" x-data="{
        showEditModal: false,
        editTask: { id: null, title: '', url: '', resolution_notes: '' },
        submitting: false,

        openEditModal(id, title, url, notes) {
            this.editTask = { id: id, title: title, url: url, resolution_notes: notes || '' };
            this.showEditModal = true;
        }
    }">

        {{-- Top Action Header with 3-tab Switcher --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">My Work</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200">
                        Accomplishment Log ({{ $totalCompleted }})
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Comprehensive audit trail of completed operations, resolutions, and fleet monitoring actions</p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- View Switcher Tab (Tasks / Calendar / Accomplishments) --}}
                <div class="inline-flex items-center p-0.5 rounded-lg bg-slate-200/80 border border-slate-200 text-xs font-semibold">
                    <a href="{{ route('work-tasks.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />
                        </svg>
                        <span>Tasks</span>
                    </a>
                    <a href="{{ route('work-tasks.calendar') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span>Calendar</span>
                    </a>
                    <a href="{{ route('work-tasks.accomplishments') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition bg-white text-slate-900 shadow-xs">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Accomplishments</span>
                    </a>
                    <a href="{{ route('work-notes.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        <span>Notes</span>
                    </a>
                </div>

                {{-- Add Task Button --}}
                <a href="{{ route('work-tasks.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Task</span>
                </a>
            </div>
        </div>

        {{-- Accomplishments Metric Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3.5">
            {{-- Completed Today --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">Completed Today</span>
                    <span class="h-2 w-2 rounded-full bg-emerald-500"></span>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-1.5">{{ $completedToday }}</div>
                <p class="text-[11px] text-slate-400 mt-0.5">Tasks signed off today</p>
            </div>

            {{-- Completed This Week --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">This Week</span>
                    <svg class="h-4 w-4 text-blue-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-1.5">{{ $completedThisWeek }}</div>
                <p class="text-[11px] text-slate-400 mt-0.5">Monday to Sunday total</p>
            </div>

            {{-- Completed This Month --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">This Month</span>
                    <svg class="h-4 w-4 text-indigo-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M2.25 18 9 11.25l4.306 4.306a11.95 11.95 0 0 1 5.814-5.518l2.74-1.22m0 0-5.94-2.281m5.94 2.28-2.28 5.941" />
                    </svg>
                </div>
                <div class="text-2xl font-black text-slate-900 mt-1.5">{{ $completedThisMonth }}</div>
                <p class="text-[11px] text-slate-400 mt-0.5">{{ now()->format('F Y') }} tally</p>
            </div>

            {{-- On-Time Completion Rate --}}
            <div class="bg-white rounded-xl border border-slate-200 p-4 shadow-xs">
                <div class="flex items-center justify-between text-slate-400">
                    <span class="text-[11px] font-bold text-slate-500 uppercase tracking-wider">On-Time Rate</span>
                    <svg class="h-4 w-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="text-2xl font-black {{ $onTimeRate >= 85 ? 'text-emerald-600' : ($onTimeRate >= 65 ? 'text-amber-600' : 'text-red-600') }} mt-1.5">
                    {{ $onTimeRate }}%
                </div>
                <p class="text-[11px] text-slate-400 mt-0.5">Finished before/on due date</p>
            </div>
        </div>

        {{-- Filter & Search Toolbar --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
            <form method="GET" action="{{ route('work-tasks.accomplishments') }}" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                {{-- Timeframe Pills --}}
                <div class="inline-flex items-center gap-1.5 flex-wrap">
                    <a href="{{ route('work-tasks.accomplishments', array_merge(request()->except('timeframe', 'page'), ['timeframe' => 'all'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $timeframe === 'all' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All Time
                    </a>
                    <a href="{{ route('work-tasks.accomplishments', array_merge(request()->except('timeframe', 'page'), ['timeframe' => 'today'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $timeframe === 'today' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        Today
                    </a>
                    <a href="{{ route('work-tasks.accomplishments', array_merge(request()->except('timeframe', 'page'), ['timeframe' => 'this_week'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $timeframe === 'this_week' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        This Week
                    </a>
                    <a href="{{ route('work-tasks.accomplishments', array_merge(request()->except('timeframe', 'page'), ['timeframe' => 'this_month'])) }}"
                       class="px-3 py-1.5 rounded-lg text-xs font-semibold transition {{ $timeframe === 'this_month' ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        This Month
                    </a>
                </div>

                {{-- Category & Search Filter --}}
                <div class="flex items-center gap-2 flex-wrap sm:flex-nowrap">
                    @if($categories->count() > 0)
                        <select name="category" onchange="this.form.submit()"
                                class="rounded-lg border border-slate-300 bg-white text-xs px-2.5 py-1.5 text-slate-700 focus:ring-2 focus:ring-blue-500">
                            <option value="">All Categories</option>
                            @foreach($categories as $cat)
                                <option value="{{ $cat }}" {{ request('category') === $cat ? 'selected' : '' }}>
                                    {{ $cat }}
                                </option>
                            @endforeach
                        </select>
                    @endif

                    <div class="relative min-w-[200px] flex-1">
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search accomplishments..."
                               class="w-full rounded-lg border border-slate-300 text-xs px-3 py-1.5 pl-8 text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
                        <svg class="h-4 w-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    @if(request('search') || request('category') || request('timeframe') !== 'all')
                        <a href="{{ route('work-tasks.accomplishments') }}"
                           class="text-xs text-slate-500 hover:text-slate-800 underline px-1 shrink-0">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Accomplishments Feed Grouped Chronologically --}}
        @if($completedTasks->count() > 0)
            @php
                $groupedTasks = $completedTasks->groupBy(function ($task) {
                    return $task->completed_at ? $task->completed_at->format('Y-m-d') : 'undated';
                });
            @endphp

            <div class="space-y-6">
                @foreach($groupedTasks as $dateKey => $tasksOnDate)
                    @php
                        $parsedDate = $dateKey !== 'undated' ? \Carbon\Carbon::parse($dateKey) : null;
                        if ($parsedDate) {
                            if ($parsedDate->isToday()) {
                                $dateHeading = 'Today — ' . $parsedDate->format('l, F j, Y');
                            } elseif ($parsedDate->isYesterday()) {
                                $dateHeading = 'Yesterday — ' . $parsedDate->format('l, F j, Y');
                            } else {
                                $dateHeading = $parsedDate->format('l, F j, Y');
                            }
                        } else {
                            $dateHeading = 'Earlier Completed Tasks';
                        }
                    @endphp

                    <div>
                        {{-- Date Section Divider --}}
                        <div class="flex items-center gap-3 mb-3">
                            <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider">
                                {{ $dateHeading }}
                            </h3>
                            <span class="text-xs text-slate-400 font-semibold">
                                ({{ $tasksOnDate->count() }} {{ Str::plural('accomplishment', $tasksOnDate->count()) }})
                            </span>
                            <div class="flex-1 border-b border-slate-200"></div>
                        </div>

                        {{-- Task Cards for This Date --}}
                        <div class="space-y-3">
                            @foreach($tasksOnDate as $task)
                                <div class="bg-white rounded-xl border border-slate-200 shadow-xs hover:border-slate-300 transition-all p-4.5">
                                    {{-- Card Top Row: Checkmark, Title, Badges, Timeliness, Time Completed --}}
                                    <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-3">
                                        <div class="flex items-start gap-3 flex-1">
                                            <div class="h-7 w-7 rounded-full bg-emerald-100 border border-emerald-300 flex items-center justify-center shrink-0 mt-0.5">
                                                <svg class="h-4 w-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                            </div>

                                            <div class="space-y-1 min-w-0 flex-1">
                                                <div class="flex flex-wrap items-center gap-2">
                                                    <h4 class="text-sm font-bold text-slate-900 leading-snug">
                                                        {{ $task->title }}
                                                    </h4>

                                                    @if($task->category)
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700 border border-slate-200">
                                                            {{ $task->category }}
                                                        </span>
                                                    @endif

                                                    @if($task->priority === 'urgent')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-red-100 text-red-800">
                                                            Urgent
                                                        </span>
                                                    @elseif($task->priority === 'high')
                                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-amber-100 text-amber-800">
                                                            High Priority
                                                        </span>
                                                    @endif

                                                    {{-- Timeliness Indicator --}}
                                                    @if($task->due_date)
                                                        @if($task->isCompletedOnTime())
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                                                On Time
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold bg-amber-50 text-amber-800 border border-amber-200">
                                                                <span class="h-1.5 w-1.5 rounded-full bg-amber-500"></span>
                                                                Completed Late
                                                            </span>
                                                        @endif
                                                    @endif
                                                </div>

                                                <div class="flex items-center gap-3 text-xs text-slate-500 pt-0.5">
                                                    <span>Completed at <strong>{{ $task->completed_at ? $task->completed_at->format('g:i A') : '—' }}</strong></span>
                                                    @if($task->due_date)
                                                        <span class="text-slate-300">&bull;</span>
                                                        <span>Target was: <strong>{{ $task->due_date->format('M d, Y') }}</strong></span>
                                                    @endif
                                                </div>
                                            </div>
                                        </div>

                                        {{-- Actions on the Right --}}
                                        <div class="flex items-center gap-2 shrink-0 sm:self-center">
                                            <a href="{{ route('work-tasks.show', $task) }}"
                                               class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:bg-slate-50 hover:text-blue-700 transition shadow-2xs">
                                                <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                                </svg>
                                                <span>View</span>
                                            </a>

                                            <button type="button"
                                                    @click="openEditModal({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ route('work-tasks.complete', $task) }}', '{{ addslashes($task->resolution_notes ?? '') }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-emerald-700 hover:bg-emerald-50 hover:border-emerald-300 transition shadow-2xs">
                                                <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                                <span>Edit Note</span>
                                            </button>

                                            <form method="POST" action="{{ route('work-tasks.reopen', $task) }}" onsubmit="return confirm('Reopen this task as pending?');" class="inline">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 px-2.5 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-600 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition shadow-2xs"
                                                        title="Mark back to Pending if completed by mistake">
                                                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                                    </svg>
                                                    <span>Reopen</span>
                                                </button>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- Highlighted Outcome / Resolution Log Box --}}
                                    <div class="mt-3.5 rounded-xl border border-emerald-200/90 bg-emerald-50/40 p-3 sm:p-3.5">
                                        <div class="flex items-center justify-between gap-2 mb-1.5">
                                            <div class="flex items-center gap-1.5 text-xs font-bold text-emerald-900 uppercase tracking-wider">
                                                <svg class="h-4 w-4 text-emerald-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                                </svg>
                                                <span>Outcome / Resolution Log</span>
                                            </div>

                                            @if(!$task->resolution_notes)
                                                <button type="button"
                                                        @click="openEditModal({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ route('work-tasks.complete', $task) }}', '')"
                                                        class="text-[11px] font-bold text-emerald-700 hover:text-emerald-900 underline">
                                                    + Add resolution remarks
                                                </button>
                                            @endif
                                        </div>

                                        @if($task->resolution_notes)
                                            <p class="text-xs text-slate-800 font-medium whitespace-pre-wrap leading-relaxed pl-5.5">
                                                {{ $task->resolution_notes }}
                                            </p>
                                        @else
                                            <p class="text-xs text-slate-500 italic pl-5.5">
                                                Completed with no additional resolution remarks.
                                            </p>
                                        @endif
                                    </div>

                                    {{-- Additional Context: Description or Next Action if present --}}
                                    @if($task->description || $task->next_action)
                                        <div class="mt-3 pt-2.5 border-t border-slate-100 flex flex-wrap items-center gap-x-6 gap-y-1 text-xs text-slate-500">
                                            @if($task->next_action)
                                                <div>
                                                    <span class="font-bold text-slate-700">Next Action was:</span>
                                                    <span>{{ $task->next_action }}</span>
                                                </div>
                                            @endif
                                            @if($task->description)
                                                <div class="truncate max-w-md">
                                                    <span class="font-bold text-slate-700">Context:</span>
                                                    <span>{{ $task->description }}</span>
                                                </div>
                                            @endif
                                        </div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $completedTasks->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-xs">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-50 text-emerald-600 mb-3 border border-emerald-200">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">No accomplishments recorded yet</h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                    When you mark your daily monitoring tasks as complete, they will appear here as a full chronological accomplishment log.
                </p>
                <div class="mt-4">
                    <a href="{{ route('work-tasks.index') }}"
                       class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition shadow-xs">
                        View Active Tasks &rarr;
                    </a>
                </div>
            </div>
        @endif

        {{-- ─── MODAL: Edit Resolution Notes ───────────────────────────── --}}
        <div x-show="showEditModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showEditModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md border border-slate-200 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Resolution &amp; Outcome Notes</h3>
                        <p class="text-xs text-slate-400 mt-0.5 truncate max-w-[280px]" x-text="editTask.title"></p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" :action="editTask.url" @submit="submitting = true">
                    @csrf
                    @method('PATCH')

                    <div class="p-5 space-y-3">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            What was done / Outcome Remarks
                        </label>
                        <textarea name="resolution_notes" rows="4" x-model="editTask.resolution_notes"
                                  placeholder="Record your findings, action taken, or result (e.g. CV-05 odometer verified, GPS tracker recalibrated, discrepancies resolved...)"
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                        <p class="text-[11px] text-slate-400 leading-normal">
                            This will be permanently logged in your Accomplishment Log.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                        <button type="button" @click="showEditModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="px-4 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg shadow-xs hover:bg-emerald-700 cursor-pointer">
                            <span x-show="!submitting">Save Remarks</span>
                            <span x-show="submitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
