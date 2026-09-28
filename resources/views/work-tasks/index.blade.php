<x-app-layout>
    @section('page-title', 'My Work')
    @section('breadcrumb', 'Fleet Management / My Work / Tasks')

    <div class="space-y-4" x-data="{
        deleteId: null,
        deleteTitle: '',
        showDeleteModal: false,
        showQuickTask: false,
        showCompleteModal: false,
        completeTask: { id: null, title: '', url: '', resolution_notes: '' },
        qtTitle: '',
        qtDue: '',
        submitting: false,
        confirmDelete(id, title) {
            this.deleteId = id;
            this.deleteTitle = title;
            this.showDeleteModal = true;
        },
        openCompleteModal(id, title, url) {
            this.completeTask = { id: id, title: title, url: url, resolution_notes: '' };
            this.showCompleteModal = true;
        }
    }">

        {{-- Top Action Header matching GAMA Vehicle Master standard --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">My Work</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $tasks->total() }} {{ Str::plural('Task', $tasks->total()) }}
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Personal operations workflow memory — follow-ups, retests, and actionable next steps</p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- View Switcher Tab (Tasks / Calendar / Accomplishments) --}}
                <div class="inline-flex items-center p-0.5 rounded-lg bg-slate-200/80 border border-slate-200 text-xs font-semibold">
                    <a href="{{ route('work-tasks.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition bg-white text-slate-900 shadow-xs">
                        <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
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

                {{-- Quick Task Button --}}
                <button type="button" @click="showQuickTask = true"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
                        title="Add quick task reminder">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Quick Task</span>
                </button>

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

        {{-- Operations Metric Summary Strip --}}
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3">
            {{-- All Tasks --}}
            <a href="{{ route('work-tasks.index') }}"
               class="bg-white rounded-xl border p-3 shadow-xs transition-colors hover:border-slate-300 {{ !request('status') ? 'border-blue-400 bg-blue-50/20' : 'border-slate-200' }}">
                <div class="text-[11px] font-semibold text-slate-500 uppercase tracking-wider">All Tasks</div>
                <div class="text-xl font-bold text-slate-800 mt-1">{{ $totalCount }}</div>
            </a>

            {{-- Overdue --}}
            <a href="{{ route('work-tasks.index', ['status' => 'overdue']) }}"
               class="bg-white rounded-xl border p-3 shadow-xs transition-colors hover:border-red-300 {{ request('status') === 'overdue' ? 'border-red-400 bg-red-50/30' : 'border-slate-200' }}">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-semibold text-red-600 uppercase tracking-wider">Overdue</span>
                    @if($overdueCount > 0)
                        <span class="h-2 w-2 rounded-full bg-red-500 animate-pulse"></span>
                    @endif
                </div>
                <div class="text-xl font-bold text-red-600 mt-1">{{ $overdueCount }}</div>
            </a>

            {{-- Due Today --}}
            <a href="{{ route('work-tasks.index', ['status' => 'due_today']) }}"
               class="bg-white rounded-xl border p-3 shadow-xs transition-colors hover:border-amber-300 {{ request('status') === 'due_today' ? 'border-amber-400 bg-amber-50/30' : 'border-slate-200' }}">
                <div class="text-[11px] font-semibold text-amber-700 uppercase tracking-wider">Due Today</div>
                <div class="text-xl font-bold text-amber-700 mt-1">{{ $dueTodayCount }}</div>
            </a>

            {{-- In Progress --}}
            <a href="{{ route('work-tasks.index', ['status' => 'in_progress']) }}"
               class="bg-white rounded-xl border p-3 shadow-xs transition-colors hover:border-blue-300 {{ request('status') === 'in_progress' ? 'border-blue-400 bg-blue-50/30' : 'border-slate-200' }}">
                <div class="text-[11px] font-semibold text-blue-700 uppercase tracking-wider">In Progress</div>
                <div class="text-xl font-bold text-blue-700 mt-1">{{ $inProgressCount }}</div>
            </a>

            {{-- Completed --}}
            <a href="{{ route('work-tasks.index', ['status' => 'completed']) }}"
               class="bg-white rounded-xl border p-3 shadow-xs transition-colors hover:border-emerald-300 {{ request('status') === 'completed' ? 'border-emerald-400 bg-emerald-50/30' : 'border-slate-200' }}">
                <div class="text-[11px] font-semibold text-emerald-700 uppercase tracking-wider">Completed</div>
                <div class="text-xl font-bold text-emerald-700 mt-1">{{ $completedCount }}</div>
            </a>
        </div>

        {{-- Compact Integrated Search & Filter Toolbar (Vehicle Master Style) --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3">
            <form method="GET" action="{{ route('work-tasks.index') }}" class="space-y-2.5">
                {{-- Row 1: Search & Submit --}}
                <div class="flex flex-col sm:flex-row gap-2">
                    <div class="relative flex-1">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search task title, next action, context notes, category..."
                               class="w-full pl-9 pr-3 py-1.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>
                    <div class="flex items-center gap-1.5 shrink-0">
                        <button type="submit"
                                class="px-3.5 py-1.5 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors cursor-pointer shadow-xs">
                            Search
                        </button>
                        @if(request()->hasAny(['search', 'status', 'priority', 'category']))
                            <a href="{{ route('work-tasks.index') }}"
                               class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors"
                               title="Clear all filters">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>

                {{-- Row 2: Compact Inline Filter Selectors --}}
                <div class="flex flex-wrap items-center gap-2 pt-2 border-t border-slate-100 text-xs">
                    <span class="text-slate-400 text-[11px] font-semibold uppercase tracking-wider mr-1">Filter by:</span>

                    {{-- Status Dropdown --}}
                    <div class="min-w-[130px] flex-1 sm:flex-initial">
                        <select name="status" onchange="this.form.submit()"
                                class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">All Statuses</option>
                            <option value="overdue"     @selected(request('status') === 'overdue')>Overdue ({{ $overdueCount }})</option>
                            <option value="due_today"   @selected(request('status') === 'due_today')>Due Today ({{ $dueTodayCount }})</option>
                            <option value="in_progress" @selected(request('status') === 'in_progress')>In Progress ({{ $inProgressCount }})</option>
                            <option value="pending"     @selected(request('status') === 'pending')>Pending</option>
                            <option value="on_hold"     @selected(request('status') === 'on_hold')>On Hold</option>
                            <option value="completed"   @selected(request('status') === 'completed')>Completed ({{ $completedCount }})</option>
                            <option value="cancelled"   @selected(request('status') === 'cancelled')>Cancelled</option>
                        </select>
                    </div>

                    {{-- Priority Dropdown --}}
                    <div class="min-w-[120px] flex-1 sm:flex-initial">
                        <select name="priority" onchange="this.form.submit()"
                                class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                            <option value="">All Priorities</option>
                            <option value="urgent" @selected(request('priority') === 'urgent')>Urgent</option>
                            <option value="high"   @selected(request('priority') === 'high')>High</option>
                            <option value="normal" @selected(request('priority') === 'normal')>Normal</option>
                            <option value="low"    @selected(request('priority') === 'low')>Low</option>
                        </select>
                    </div>

                    {{-- Category Dropdown --}}
                    @if($categories->count() > 0)
                        <div class="min-w-[130px] flex-1 sm:flex-initial">
                            <select name="category" onchange="this.form.submit()"
                                    class="w-full py-1.5 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 focus:outline-none focus:ring-1 focus:ring-blue-500">
                                <option value="">All Categories ({{ $categories->count() }})</option>
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                                @endforeach
                            </select>
                        </div>
                    @endif
                </div>
            </form>
        </div>

        {{-- Professional Data Table matching Advanced Itineraries style --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 text-slate-500 uppercase font-semibold text-[11px] tracking-wider">
                        <tr>
                            <th class="px-4 py-3">Task &amp; Context</th>
                            <th class="px-4 py-3">Category</th>
                            <th class="px-4 py-3">Next Action</th>
                            <th class="px-4 py-3 text-center">Priority</th>
                            <th class="px-4 py-3">Target Date</th>
                            <th class="px-4 py-3 text-center">Status</th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($tasks as $task)
                            @php
                                $sb = $task->status_badge;
                                $pb = $task->priority_badge;
                            @endphp
                            <tr class="hover:bg-slate-50/80 transition-colors {{ $task->is_overdue ? 'bg-red-50/20' : '' }}">
                                {{-- Task Title & Description --}}
                                <td class="px-4 py-3">
                                    <div class="flex items-center gap-2">
                                        @if($task->priority === 'urgent')
                                            <span class="h-2 w-2 rounded-full bg-red-500 shrink-0" title="Urgent"></span>
                                        @elseif($task->priority === 'high')
                                            <span class="h-2 w-2 rounded-full bg-amber-500 shrink-0" title="High Priority"></span>
                                        @endif
                                        <a href="{{ route('work-tasks.show', $task) }}"
                                           class="font-bold text-slate-900 hover:text-blue-600 hover:underline {{ $task->isCompleted() ? 'line-through text-slate-400' : '' }}">
                                            {{ $task->title }}
                                        </a>
                                    </div>
                                    @if($task->description)
                                        <div class="text-slate-500 text-[11px] truncate max-w-xs mt-0.5">
                                            {{ $task->description }}
                                        </div>
                                    @endif
                                </td>

                                {{-- Category --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($task->category)
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                            {{ $task->category }}
                                        </span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- Next Action --}}
                                <td class="px-4 py-3">
                                    @if($task->next_action)
                                        <div class="flex items-center gap-1.5 text-slate-800 text-xs max-w-xs">
                                            <svg class="h-3.5 w-3.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                            </svg>
                                            <span class="truncate font-medium">{{ $task->next_action }}</span>
                                        </div>
                                    @else
                                        <span class="text-slate-400 italic">—</span>
                                    @endif
                                </td>

                                {{-- Priority --}}
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($task->priority === 'urgent')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-red-100 text-red-800">
                                            Urgent
                                        </span>
                                    @elseif($task->priority === 'high')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-semibold bg-amber-100 text-amber-800">
                                            High
                                        </span>
                                    @elseif($task->priority === 'low')
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-500">
                                            Low
                                        </span>
                                    @else
                                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[11px] font-medium bg-slate-100 text-slate-700">
                                            Normal
                                        </span>
                                    @endif
                                </td>

                                {{-- Target Date --}}
                                <td class="px-4 py-3 whitespace-nowrap">
                                    @if($task->due_date)
                                        <div class="font-medium {{ $task->is_overdue ? 'text-red-600 font-bold' : ($task->is_due_today ? 'text-amber-700 font-bold' : 'text-slate-700') }}">
                                            {{ $task->due_date->format('M d, Y') }}
                                        </div>
                                        @if($task->is_overdue)
                                            <div class="text-[10px] font-bold text-red-600 uppercase tracking-wider">
                                                OVERDUE
                                            </div>
                                        @elseif($task->is_due_today)
                                            <div class="text-[10px] font-bold text-amber-600 uppercase tracking-wider">
                                                DUE TODAY
                                            </div>
                                        @endif
                                    @else
                                        <span class="text-slate-400 italic">—</span>
                                    @endif
                                </td>

                                {{-- Status --}}
                                <td class="px-4 py-3 text-center whitespace-nowrap">
                                    @if($task->isCompleted())
                                        <span class="inline-flex items-center rounded-full bg-emerald-100 px-2.5 py-0.5 text-[11px] font-bold text-emerald-800">
                                            COMPLETED
                                        </span>
                                    @elseif($task->isInProgress())
                                        <span class="inline-flex items-center rounded-full bg-blue-100 px-2.5 py-0.5 text-[11px] font-bold text-blue-800">
                                            IN PROGRESS
                                        </span>
                                    @elseif($task->isOnHold())
                                        <span class="inline-flex items-center rounded-full bg-slate-100 px-2.5 py-0.5 text-[11px] font-bold text-slate-700">
                                            ON HOLD
                                        </span>
                                    @elseif($task->isCancelled())
                                        <span class="inline-flex items-center rounded-full bg-red-100 px-2.5 py-0.5 text-[11px] font-bold text-red-800">
                                            CANCELLED
                                        </span>
                                    @else
                                        <span class="inline-flex items-center rounded-full bg-amber-100 px-2.5 py-0.5 text-[11px] font-bold text-amber-800">
                                            PENDING
                                        </span>
                                    @endif
                                </td>

                                {{-- Actions --}}
                                <td class="px-4 py-3 text-right whitespace-nowrap">
                                    <div class="flex items-center justify-end gap-1.5">
                                        {{-- View Details --}}
                                        <a href="{{ route('work-tasks.show', $task) }}"
                                           class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-slate-200 bg-white text-[11px] font-semibold text-slate-700 hover:bg-slate-50 hover:text-blue-700 transition shadow-2xs"
                                           title="View Details">
                                            <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>View</span>
                                        </a>

                                        {{-- Mark Complete with Resolution Modal --}}
                                        @if(!$task->isCompleted())
                                            <button type="button"
                                                    @click="openCompleteModal({{ $task->id }}, '{{ addslashes($task->title) }}', '{{ route('work-tasks.complete', $task) }}')"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md bg-emerald-600 text-[11px] font-semibold text-white hover:bg-emerald-700 transition shadow-2xs cursor-pointer"
                                                    title="Mark as Completed">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                                <span>Done</span>
                                            </button>
                                        @endif

                                        {{-- Edit --}}
                                        @can('update', $task)
                                            <a href="{{ route('work-tasks.edit', $task) }}"
                                               class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-slate-200 bg-white text-[11px] font-semibold text-blue-700 hover:bg-blue-50 hover:border-blue-300 transition shadow-2xs"
                                               title="Edit Task">
                                                <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                                <span>Edit</span>
                                            </a>
                                        @endcan

                                        {{-- Delete --}}
                                        @can('delete', $task)
                                            <button type="button"
                                                    @click="confirmDelete({{ $task->id }}, '{{ addslashes($task->title) }}')"
                                                    class="inline-flex items-center gap-1 px-2 py-1 rounded-md border border-slate-200 bg-white text-[11px] font-semibold text-slate-500 hover:text-red-600 hover:bg-red-50 hover:border-red-200 transition shadow-2xs cursor-pointer"
                                                    title="Delete Task">
                                                <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="7" class="px-4 py-12 text-center text-slate-500">
                                    <div class="max-w-xs mx-auto text-center space-y-2">
                                        <svg class="mx-auto h-8 w-8 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                        </svg>
                                        <div class="text-xs font-semibold text-slate-700">No work tasks found.</div>
                                        <p class="text-[11px] text-slate-400">
                                            @if(request()->hasAny(['search', 'status', 'priority', 'category']))
                                                Try adjusting search keywords or resetting filters.
                                            @else
                                                Click "+ Add Task" or "+ Quick Task" to create your first workflow item.
                                            @endif
                                        </p>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($tasks->hasPages())
                <div class="border-t border-slate-200 p-3.5">
                    {{ $tasks->links() }}
                </div>
            @endif
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

        {{-- Delete Confirmation Modal --}}
        <div x-show="showDeleteModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-lg p-5 w-full max-w-sm border border-slate-200">
                <div class="flex items-center gap-3 mb-3">
                    <div class="h-9 w-9 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Delete Work Task</h3>
                        <p class="text-xs text-slate-500 truncate max-w-[200px]" x-text="deleteTitle"></p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mb-4">Are you sure? This task will be removed from your workflow memory.</p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                        Cancel
                    </button>
                    <form method="POST" :action="'/work-tasks/' + deleteId" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-3.5 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 cursor-pointer shadow-xs">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

        {{-- ─── MODAL: Complete Task with Outcome Notes ───────────────────────────── --}}
        <div x-show="showCompleteModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showCompleteModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md border border-slate-200 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <div class="h-7 w-7 rounded-full bg-emerald-100 border border-emerald-300 flex items-center justify-center">
                            <svg class="h-4 w-4 text-emerald-700" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-slate-800">Complete Task</h3>
                            <p class="text-xs text-slate-500 truncate max-w-[260px]" x-text="completeTask.title"></p>
                        </div>
                    </div>
                    <button type="button" @click="showCompleteModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" :action="completeTask.url" @submit="submitting = true">
                    @csrf
                    @method('PATCH')

                    <div class="p-5 space-y-3">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            Outcome / Resolution Remarks
                        </label>
                        <textarea name="resolution_notes" rows="3" x-model="completeTask.resolution_notes"
                                  placeholder="What was done or resolved? (e.g. Inspected odometer at 145,230 km, GPS re-calibrated, verified trip logs...)"
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-emerald-500 resize-none"></textarea>
                        <p class="text-[11px] text-slate-400">
                            Optional — leave blank to quick complete. This will be stored in your Accomplishment Log.
                        </p>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                        <button type="button" @click="showCompleteModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting"
                                class="inline-flex items-center gap-1.5 px-4 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg shadow-xs hover:bg-emerald-700 cursor-pointer">
                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            <span x-show="!submitting">Mark Complete</span>
                            <span x-show="submitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</x-app-layout>
