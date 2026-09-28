<x-app-layout>
    @section('page-title', 'My Work')
    @section('breadcrumb', 'Fleet Management / My Work / Calendar')

    <div class="space-y-4" x-data="{
        showCreateModal: false,
        showTaskModal: false,
        showDeleteModal: false,
        createDate: '',
        createTitle: '',
        createCategory: '',
        createPriority: 'normal',
        createNextAction: '',
        createNotes: '',
        submitting: false,

        selectedTask: {
            id: null,
            title: '',
            category: '',
            status: '',
            status_label: '',
            status_badge: '',
            priority: '',
            priority_label: '',
            priority_badge: '',
            due_date: '',
            due_date_formatted: '',
            is_overdue: false,
            is_due_today: false,
            is_completed: false,
            next_action: '',
            description: '',
            notes: '',
            show_url: '',
            edit_url: '',
            complete_url: '',
            destroy_url: ''
        },

        openCreateModal(dateStr) {
            this.createDate = dateStr;
            this.createTitle = '';
            this.createCategory = '';
            this.createPriority = 'normal';
            this.createNextAction = '';
            this.createNotes = '';
            this.showCreateModal = true;
        },

        openTaskModal(taskData) {
            this.selectedTask = taskData;
            this.showTaskModal = true;
        }
    }">

        {{-- Top Action Header with List / Calendar Switcher --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">My Work</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        Calendar View
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Monthly schedule of operations deadlines, unit retests, and scheduled fleet tasks</p>
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
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition bg-white text-slate-900 shadow-xs">
                        <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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

        {{-- Calendar Navigation Toolbar (Matches Reference Design) --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-4">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                {{-- Month Title & Navigation Controls --}}
                <div class="flex items-center gap-3">
                    <h3 class="text-2xl font-bold text-slate-900 tracking-tight min-w-[200px]">
                        {{ $monthTitle }}
                    </h3>

                    {{-- Today Button Pill --}}
                    <a href="{{ route('work-tasks.calendar') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 transition shadow-2xs"
                       title="Go to current month">
                        <span class="h-1.5 w-1.5 rounded-full bg-blue-600"></span>
                        <span>Today</span>
                    </a>

                    {{-- Prev / Next Month Circular Buttons --}}
                    <div class="inline-flex items-center gap-1">
                        <a href="{{ route('work-tasks.calendar', ['month' => $prevMonth]) }}"
                           class="h-8 w-8 rounded-full border border-slate-200 bg-white flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition shadow-2xs"
                           title="Previous Month">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <a href="{{ route('work-tasks.calendar', ['month' => $nextMonth]) }}"
                           class="h-8 w-8 rounded-full border border-slate-200 bg-white flex items-center justify-center text-slate-600 hover:text-slate-900 hover:bg-slate-50 transition shadow-2xs"
                           title="Next Month">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>
                </div>

                {{-- Status Legend & Counters --}}
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <span class="text-slate-400 text-[11px] font-bold uppercase tracking-wider mr-1">Status:</span>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-rose-50 border border-rose-200 px-2.5 py-0.5 text-xs font-semibold text-rose-700">
                        <span class="h-2 w-2 rounded-full bg-rose-600"></span>
                        Overdue: {{ $overdueCount }}
                    </span>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-50 border border-amber-200 px-2.5 py-0.5 text-xs font-semibold text-amber-700">
                        <span class="h-2 w-2 rounded-full bg-amber-500"></span>
                        Due Today: {{ $dueTodayCount }}
                    </span>

                    <span class="inline-flex items-center gap-1.5 rounded-full bg-blue-50 border border-blue-200 px-2.5 py-0.5 text-xs font-semibold text-blue-700">
                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                        In Progress: {{ $inProgressCount }}
                    </span>
                </div>
            </div>
        </div>

        {{-- Main 7-Day Monthly Calendar Grid --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-xs">
            <div class="overflow-x-auto">
                <div class="min-w-[820px]">

                    {{-- Days of Week Header (Sun to Sat) --}}
                    <div class="grid grid-cols-7 border-b border-slate-200 bg-slate-50/90 text-xs font-bold text-slate-500 uppercase tracking-wider py-3"
                         style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">
                        <div class="text-center text-rose-600 font-extrabold">Sun</div>
                        <div class="text-center">Mon</div>
                        <div class="text-center">Tue</div>
                        <div class="text-center">Wed</div>
                        <div class="text-center">Thu</div>
                        <div class="text-center">Fri</div>
                        <div class="text-center">Sat</div>
                    </div>

                    {{-- Calendar Grid Cells with 1px border grid trick --}}
                    <div class="grid grid-cols-7 border-t border-slate-200"
                         style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background-color: #e2e8f0; gap: 1px;">
                        @foreach($calendarDays as $day)
                            <div @click="openCreateModal('{{ $day['date'] }}')"
                                 class="group relative p-2.5 flex flex-col justify-between transition-colors cursor-pointer select-none
                                        {{ $day['is_current_month'] ? 'bg-white hover:bg-slate-50/80' : 'bg-slate-50/70 hover:bg-slate-100/70' }}
                                        {{ $day['is_today'] ? 'bg-blue-50/25 ring-1 ring-inset ring-blue-500/30' : '' }}"
                                 style="min-height: 125px;">

                                {{-- Date Header inside Cell --}}
                                <div class="flex items-center justify-between mb-1.5 pointer-events-none">
                                    @if($day['is_today'])
                                        <span class="inline-flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-xs font-bold text-white shadow-xs">
                                            {{ $day['day'] }}
                                        </span>
                                    @else
                                        <span class="text-xs font-bold {{ $day['is_current_month'] ? 'text-slate-800' : 'text-slate-300' }}">
                                            {{ $day['day'] }}
                                        </span>
                                    @endif

                                    {{-- Quick Add '+' icon on hover --}}
                                    <button type="button"
                                            @click.stop="openCreateModal('{{ $day['date'] }}')"
                                            class="opacity-0 group-hover:opacity-100 p-0.5 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded transition pointer-events-auto cursor-pointer"
                                            title="Add task on {{ $day['date'] }}">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                        </svg>
                                    </button>
                                </div>

                                {{-- Task Pills inside Date Cell (Vibrant badges matching Reference Design) --}}
                                <div class="space-y-1 overflow-hidden flex-1 pointer-events-auto">
                                    @foreach($day['tasks']->take(3) as $task)
                                        @php
                                            $taskJson = [
                                                'id' => $task->id,
                                                'title' => $task->title,
                                                'category' => $task->category,
                                                'status' => $task->status,
                                                'status_label' => $task->status_label,
                                                'status_badge' => $task->status_badge,
                                                'priority' => $task->priority,
                                                'priority_label' => $task->priority_label,
                                                'priority_badge' => $task->priority_badge,
                                                'due_date' => $task->due_date ? $task->due_date->format('Y-m-d') : null,
                                                'due_date_formatted' => $task->due_date ? $task->due_date->format('M d, Y') : null,
                                                'is_overdue' => $task->is_overdue,
                                                'is_due_today' => $task->is_due_today,
                                                'is_completed' => $task->isCompleted(),
                                                'next_action' => $task->next_action,
                                                'description' => $task->description,
                                                'notes' => $task->notes,
                                                'show_url' => route('work-tasks.show', $task),
                                                'edit_url' => route('work-tasks.edit', $task),
                                                'complete_url' => route('work-tasks.complete', $task),
                                                'destroy_url' => route('work-tasks.destroy', $task),
                                            ];
                                        @endphp
                                        <div @click.stop="openTaskModal({{ json_encode($taskJson) }})"
                                             class="flex items-center gap-1.5 px-2 py-1 rounded-md text-xs font-semibold transition cursor-pointer truncate shadow-2xs hover:brightness-105
                                                    {{ $task->isCompleted() ? 'bg-slate-200 text-slate-500 line-through border border-slate-300' :
                                                       ($task->is_overdue ? 'bg-rose-600 text-white' :
                                                       ($task->priority === 'urgent' ? 'bg-red-500 text-white' :
                                                       ($task->priority === 'high' ? 'bg-amber-500 text-white' :
                                                       ($task->priority === 'low' ? 'bg-emerald-600 text-white' :
                                                       'bg-blue-600 text-white')))) }}"
                                             title="{{ $task->title }} @if($task->next_action) — Next: {{ $task->next_action }} @endif">

                                            @if($task->isCompleted())
                                                <span class="text-emerald-700 font-bold text-[10px] shrink-0">✓</span>
                                            @elseif($task->is_overdue)
                                                <span class="h-1.5 w-1.5 rounded-full bg-white shrink-0 animate-pulse" title="Overdue"></span>
                                            @else
                                                <span class="h-1.5 w-1.5 rounded-full bg-white/80 shrink-0"></span>
                                            @endif

                                            <span class="truncate">{{ $task->title }}</span>
                                        </div>
                                    @endforeach

                                    {{-- '+N more' overflow badge --}}
                                    @if($day['tasks']->count() > 3)
                                        <div class="text-[10px] text-slate-500 font-semibold pl-1">
                                            +{{ $day['tasks']->count() - 3 }} more
                                        </div>
                                    @endif
                                </div>

                                {{-- Bottom Overdue Badge for this Date --}}
                                @php
                                    $dayOverdue = $day['tasks']->where('is_overdue', true)->count();
                                @endphp
                                @if($dayOverdue > 0)
                                    <div class="mt-1 flex items-center gap-1 text-[10px] font-bold text-rose-600">
                                        <span class="h-1 w-1 rounded-full bg-rose-500"></span>
                                        <span>{{ $dayOverdue }} overdue</span>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>

        {{-- ─── MODAL 1: Create Task on Selected Date ───────────────────────────── --}}
        <div x-show="showCreateModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showCreateModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg border border-slate-200 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Add Task to Calendar</h3>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Target Date: <strong class="text-blue-600 font-mono" x-text="createDate"></strong>
                        </p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" action="{{ route('work-tasks.store') }}" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="status" value="pending">
                    <input type="hidden" name="sort_order" value="0">
                    <input type="hidden" name="redirect_to" value="calendar">
                    <input type="hidden" name="month" value="{{ $currentDate->format('Y-m') }}">

                    <div class="p-5 space-y-3.5">
                        {{-- Task Title --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Task Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" x-model="createTitle" required
                                   placeholder="e.g. CV-05 Odometer verification, Unit 12 GPS check..."
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- 2-Column: Category & Priority --}}
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                    Category
                                </label>
                                <input type="text" name="category" x-model="createCategory"
                                       list="calendar-category-list"
                                       placeholder="e.g. GPS Monitoring"
                                       class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                <datalist id="calendar-category-list">
                                    @foreach($categories as $cat)
                                        <option value="{{ $cat }}"></option>
                                    @endforeach
                                    @foreach(['GPS Monitoring', 'Vehicle Monitoring', 'Fuel Monitoring', 'Reports', 'Administrative'] as $s)
                                        <option value="{{ $s }}"></option>
                                    @endforeach
                                </datalist>
                            </div>

                            <div>
                                <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                    Priority <span class="text-red-500">*</span>
                                </label>
                                <select name="priority" x-model="createPriority"
                                        class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                        </div>

                        {{-- Due Date --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Target Due Date <span class="text-red-500">*</span>
                            </label>
                            <input type="date" name="due_date" x-model="createDate" required
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Immediate Next Action --}}
                        <div class="bg-blue-50/50 border border-blue-200 rounded-lg p-3">
                            <label class="block text-xs font-bold text-blue-900 uppercase tracking-wider mb-1">
                                Immediate Next Action
                            </label>
                            <input type="text" name="next_action" x-model="createNextAction"
                                   placeholder="e.g. Call driver at 9 AM, Inspect vehicle at shop"
                                   class="w-full px-3 py-1.5 bg-white border border-blue-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Context / Notes
                            </label>
                            <textarea name="notes" rows="2" x-model="createNotes"
                                      placeholder="Any remarks or background..."
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                        <a :href="'{{ route('work-tasks.create') }}?due_date=' + createDate"
                           class="text-xs font-semibold text-blue-600 hover:underline">
                            Full Create Form &rarr;
                        </a>

                        <div class="flex items-center gap-2">
                            <button type="button" @click="showCreateModal = false"
                                    class="px-3.5 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                                Cancel
                            </button>
                            <button type="submit" :disabled="submitting || !createTitle.trim()"
                                    :class="(submitting || !createTitle.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                    class="px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg shadow-xs cursor-pointer">
                                <span x-show="!submitting">Save Task</span>
                                <span x-show="submitting">Saving...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        {{-- ─── MODAL 2: Task Detail & Actions Preview ──────────────────────────── --}}
        <div x-show="showTaskModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showTaskModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-md border border-slate-200 overflow-hidden" @click.stop>
                {{-- Modal Header --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div class="flex items-center gap-2">
                        <span class="text-xs font-bold text-slate-500 uppercase tracking-wider" x-text="'Task #' + selectedTask.id"></span>
                        <template x-if="selectedTask.category">
                            <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-700" x-text="selectedTask.category"></span>
                        </template>
                    </div>
                    <button type="button" @click="showTaskModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                {{-- Modal Body --}}
                <div class="p-5 space-y-4">
                    {{-- Title --}}
                    <div>
                        <h4 class="text-base font-bold text-slate-900 leading-snug"
                            :class="selectedTask.is_completed ? 'line-through text-slate-400' : ''"
                            x-text="selectedTask.title"></h4>
                    </div>

                    {{-- Status / Priority / Date Grid --}}
                    <div class="grid grid-cols-2 gap-3 p-3 rounded-lg bg-slate-50 border border-slate-200 text-xs">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Status</span>
                            <span class="font-semibold text-slate-800" x-text="selectedTask.status_label"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Priority</span>
                            <span class="font-semibold text-slate-800" x-text="selectedTask.priority_label"></span>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Due Date</span>
                            <span class="font-semibold"
                                  :class="selectedTask.is_overdue ? 'text-red-600' : 'text-slate-800'"
                                  x-text="selectedTask.due_date_formatted || 'None'"></span>
                            <template x-if="selectedTask.is_overdue">
                                <span class="text-[10px] font-bold text-red-600 block">OVERDUE</span>
                            </template>
                        </div>
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-0.5">Category</span>
                            <span class="font-medium text-slate-700" x-text="selectedTask.category || '—'"></span>
                        </div>
                    </div>

                    {{-- Next Action (If set) --}}
                    <template x-if="selectedTask.next_action">
                        <div class="bg-blue-50 border border-blue-200 rounded-lg p-3">
                            <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900 uppercase tracking-wider mb-1">
                                <svg class="h-3.5 w-3.5 text-blue-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                                </svg>
                                <span>Immediate Next Action</span>
                            </div>
                            <p class="text-xs font-semibold text-slate-800 pl-5" x-text="selectedTask.next_action"></p>
                        </div>
                    </template>

                    {{-- Description --}}
                    <template x-if="selectedTask.description">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Description</span>
                            <p class="text-xs text-slate-700 whitespace-pre-wrap leading-relaxed" x-text="selectedTask.description"></p>
                        </div>
                    </template>

                    {{-- Notes --}}
                    <template x-if="selectedTask.notes">
                        <div>
                            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block mb-1">Notes / Logs</span>
                            <p class="text-xs text-slate-600 whitespace-pre-wrap leading-relaxed" x-text="selectedTask.notes"></p>
                        </div>
                    </template>
                </div>

                {{-- Modal Footer Actions --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                    <a :href="selectedTask.show_url"
                       class="text-xs font-semibold text-blue-600 hover:underline">
                        Full Details &rarr;
                    </a>

                    <div class="flex items-center gap-2">
                        {{-- Mark Complete --}}
                        <template x-if="!selectedTask.is_completed">
                            <form method="POST" :action="selectedTask.complete_url" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-600 text-white text-xs font-semibold rounded-lg hover:bg-emerald-700 transition shadow-xs cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    <span>Done</span>
                                </button>
                            </form>
                        </template>

                        {{-- Edit --}}
                        <a :href="selectedTask.edit_url"
                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition shadow-xs">
                            <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            <span>Edit</span>
                        </a>

                        {{-- Close --}}
                        <button type="button" @click="showTaskModal = false"
                                class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
