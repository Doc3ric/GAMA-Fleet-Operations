<x-app-layout>
    @section('page-title', 'My Work')
    @section('breadcrumb', 'Fleet Management / My Work / Calendar')

    {{-- ── GAMA BRAND PALETTE: cobalt blue primary, white surface, minimal red accent ── --}}
    <div class="space-y-4" x-data="{
        showCreateModal: false,
        showTaskModal: false,
        showDayModal: false,

        createDate: '',
        createTitle: '',
        createCategory: '',
        createPriority: 'normal',
        createNextAction: '',
        createNotes: '',
        submitting: false,

        searchQuery: '',
        filterCategory: '',
        filterPriority: '',
        filterStatus: '',

        selectedDay: { date: '', day: '', formatted_date: '', is_today: false, tasks: [] },

        selectedTask: {
            id: null, title: '', category: '', status: '', status_label: '',
            status_badge: {}, priority: '', priority_label: '', priority_badge: {},
            due_date: '', due_date_formatted: '', is_overdue: false, is_due_today: false,
            is_completed: false, next_action: '', description: '', notes: '',
            show_url: '', edit_url: '', complete_url: '', reopen_url: '', destroy_url: ''
        },

        openCreateModal(dateStr, presetCategory = '') {
            this.createDate = dateStr;
            this.createTitle = '';
            this.createCategory = presetCategory;
            this.createPriority = 'normal';
            this.createNextAction = '';
            this.createNotes = '';
            this.showCreateModal = true;
        },

        openDayModal(dayData) {
            this.selectedDay = dayData;
            this.showDayModal = true;
        },

        openTaskModal(taskData) {
            this.selectedTask = taskData;
            this.showTaskModal = true;
        },

        isTaskVisible(task) {
            if (this.searchQuery && this.searchQuery.trim() !== '') {
                const q = this.searchQuery.toLowerCase().trim();
                if (!(task.title || '').toLowerCase().includes(q) &&
                    !(task.next_action || '').toLowerCase().includes(q) &&
                    !(task.category || '').toLowerCase().includes(q) &&
                    !(task.description || '').toLowerCase().includes(q)) return false;
            }
            if (this.filterCategory && (task.category || '').toLowerCase() !== this.filterCategory.toLowerCase()) return false;
            if (this.filterPriority && (task.priority || '') !== this.filterPriority) return false;
            if (this.filterStatus) {
                if (this.filterStatus === 'overdue' && !task.is_overdue) return false;
                if (this.filterStatus === 'due_today' && !task.is_due_today) return false;
                if (this.filterStatus === 'completed' && !task.is_completed) return false;
                if (this.filterStatus === 'in_progress' && task.status !== 'in_progress') return false;
                if (this.filterStatus === 'pending' && task.status !== 'pending') return false;
            }
            return true;
        },

        hasFilter() {
            return (this.searchQuery && this.searchQuery.trim() !== '') ||
                   this.filterCategory !== '' || this.filterPriority !== '' || this.filterStatus !== '';
        },

        clearFilters() {
            this.searchQuery = '';
            this.filterCategory = '';
            this.filterPriority = '';
            this.filterStatus = '';
        }
    }">

        {{-- ═══════════════════════════════════════════════════════════════════
             1.  PAGE HEADER + VIEW SWITCHER
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-black text-slate-900 tracking-tight">My Work</h2>
                    <span class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-700 text-white shadow-sm">
                        <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        Calendar View
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5 font-medium">Monthly schedule of operations deadlines, unit retests &amp; fleet tasks</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- View Switcher --}}
                <div class="inline-flex items-center p-0.5 rounded-lg bg-slate-100 border border-slate-200 text-xs font-semibold shadow-sm">
                    <a href="{{ route('work-tasks.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-slate-500 hover:text-slate-800 hover:bg-white transition-all">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />
                        </svg>
                        Tasks
                    </a>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md bg-blue-700 text-white shadow-sm font-bold">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        Calendar
                    </span>
                    <a href="{{ route('work-tasks.accomplishments') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-slate-500 hover:text-slate-800 hover:bg-white transition-all">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        Accomplishments
                    </a>
                    <a href="{{ route('work-notes.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md text-slate-500 hover:text-slate-800 hover:bg-white transition-all">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Notes
                    </a>
                </div>

                {{-- Add Task CTA --}}
                <button type="button"
                        @click="openCreateModal('{{ \Carbon\Carbon::today()->format('Y-m-d') }}')"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-700 hover:bg-blue-800 px-4 py-2 text-xs font-bold text-white shadow-sm transition-colors cursor-pointer">
                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Add Task
                </button>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             2.  OPERATIONAL KPI RIBBON — GAMA Blue dominant, red only for critical
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">

            {{-- Monthly Scheduled --}}
            <div class="bg-white rounded-xl border border-blue-100 p-4 shadow-sm flex items-center gap-4">
                <div class="h-11 w-11 rounded-xl bg-blue-700 flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest text-blue-700">Month Scheduled</p>
                    <p class="text-2xl font-black text-slate-900 leading-tight">{{ $monthScheduledCount }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 font-medium truncate">{{ $monthPendingCount }} pending · {{ $monthInProgressCount }} active</p>
                </div>
            </div>

            {{-- Overdue — RED accent (critical only) --}}
            <div class="bg-white rounded-xl border {{ $monthOverdueCount > 0 ? 'border-red-200' : 'border-slate-100' }} p-4 shadow-sm flex items-center gap-4">
                <div class="h-11 w-11 rounded-xl {{ $monthOverdueCount > 0 ? 'bg-red-600' : 'bg-slate-200' }} flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 {{ $monthOverdueCount > 0 ? 'text-white' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <div class="flex items-center gap-1.5">
                        <p class="text-[10px] font-bold uppercase tracking-widest {{ $monthOverdueCount > 0 ? 'text-red-600' : 'text-slate-500' }}">Overdue</p>
                        @if($monthOverdueCount > 0)
                            <span class="h-1.5 w-1.5 rounded-full bg-red-500 animate-pulse"></span>
                        @endif
                    </div>
                    <p class="text-2xl font-black {{ $monthOverdueCount > 0 ? 'text-red-600' : 'text-slate-400' }} leading-tight">{{ $monthOverdueCount }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 font-medium">{{ $overdueCount }} total fleet-wide</p>
                </div>
            </div>

            {{-- Due Today --}}
            <div class="bg-white rounded-xl border {{ $dueTodayCount > 0 ? 'border-blue-200' : 'border-slate-100' }} p-4 shadow-sm flex items-center gap-4">
                <div class="h-11 w-11 rounded-xl {{ $dueTodayCount > 0 ? 'bg-blue-600' : 'bg-slate-200' }} flex items-center justify-center shrink-0">
                    <svg class="h-5 w-5 {{ $dueTodayCount > 0 ? 'text-white' : 'text-slate-400' }}" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                </div>
                <div class="min-w-0">
                    <p class="text-[10px] font-bold uppercase tracking-widest {{ $dueTodayCount > 0 ? 'text-blue-700' : 'text-slate-500' }}">Due Today</p>
                    <p class="text-2xl font-black {{ $dueTodayCount > 0 ? 'text-blue-700' : 'text-slate-400' }} leading-tight">{{ $dueTodayCount }}</p>
                    <p class="text-[10px] text-slate-500 mt-0.5 font-medium">Retests &amp; operations today</p>
                </div>
            </div>

            {{-- Monthly Completion Rate --}}
            <div class="bg-white rounded-xl border border-slate-100 p-4 shadow-sm">
                <div class="flex items-center justify-between mb-2">
                    <div>
                        <p class="text-[10px] font-bold uppercase tracking-widest text-slate-500">Monthly Progress</p>
                        <p class="text-2xl font-black text-slate-900 leading-tight mt-0.5">{{ $monthCompletedCount }} <span class="text-sm font-semibold text-slate-400">/ {{ $monthScheduledCount }}</span></p>
                    </div>
                    <div class="h-11 w-11 rounded-xl bg-blue-50 border border-blue-100 flex items-center justify-center shrink-0">
                        <span class="text-sm font-black text-blue-700">{{ $monthCompletionRate }}<span class="text-[10px]">%</span></span>
                    </div>
                </div>
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden">
                    <div class="bg-blue-600 h-2 rounded-full transition-all duration-700 ease-out" style="width: {{ $monthCompletionRate }}%"></div>
                </div>
                <p class="text-[10px] text-slate-400 mt-1.5 font-medium">{{ $monthCompletionRate }}% completed for {{ $monthTitle }}</p>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             3.  NAVIGATION BAR + LIVE FILTER RIBBON
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
            {{-- Top nav strip: month name + navigation --}}
            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-3.5 border-b border-slate-100 bg-slate-50/70">
                <div class="flex flex-wrap items-center gap-3">
                    {{-- Month Title --}}
                    <h3 class="text-xl font-black text-slate-900 tracking-tight min-w-[190px]">{{ $monthTitle }}</h3>

                    {{-- Today --}}
                    <a href="{{ route('work-tasks.calendar') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold text-blue-700 bg-blue-50 border border-blue-200 hover:bg-blue-100 transition shadow-sm"
                       title="Jump to current month">
                        <span class="h-2 w-2 rounded-full bg-blue-600"></span>
                        Today
                    </a>

                    {{-- Prev / Next --}}
                    <div class="inline-flex items-center rounded-lg border border-slate-200 overflow-hidden shadow-sm">
                        <a href="{{ route('work-tasks.calendar', ['month' => $prevMonth]) }}"
                           class="h-8 w-8 flex items-center justify-center text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition border-r border-slate-200"
                           title="Previous: {{ $prevMonth }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 19.5 8.25 12l7.5-7.5" />
                            </svg>
                        </a>
                        <a href="{{ route('work-tasks.calendar', ['month' => $nextMonth]) }}"
                           class="h-8 w-8 flex items-center justify-center text-slate-500 hover:text-blue-700 hover:bg-blue-50 transition"
                           title="Next: {{ $nextMonth }}">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                            </svg>
                        </a>
                    </div>

                    {{-- Month picker jump --}}
                    <input type="month"
                           value="{{ $currentDate->format('Y-m') }}"
                           onchange="if(this.value) window.location.href='{{ route('work-tasks.calendar') }}?month='+this.value"
                           class="py-1.5 px-2.5 text-xs font-semibold text-slate-700 bg-white border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-blue-500 cursor-pointer shadow-sm"
                           title="Jump to month">
                </div>

                {{-- Status quick-filter pills --}}
                <div class="flex flex-wrap items-center gap-2 text-xs">
                    <button type="button"
                            @click="filterStatus = (filterStatus === 'overdue' ? '' : 'overdue')"
                            :class="filterStatus === 'overdue'
                                ? 'bg-red-600 text-white border-red-600 shadow-sm'
                                : 'bg-white text-red-600 border-red-200 hover:bg-red-50'"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition cursor-pointer">
                        <span class="h-1.5 w-1.5 rounded-full"
                              :class="filterStatus === 'overdue' ? 'bg-white' : 'bg-red-500 animate-pulse'"></span>
                        Overdue · {{ $overdueCount }}
                    </button>

                    <button type="button"
                            @click="filterStatus = (filterStatus === 'due_today' ? '' : 'due_today')"
                            :class="filterStatus === 'due_today'
                                ? 'bg-blue-700 text-white border-blue-700 shadow-sm'
                                : 'bg-white text-blue-700 border-blue-200 hover:bg-blue-50'"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition cursor-pointer">
                        <span class="h-1.5 w-1.5 rounded-full"
                              :class="filterStatus === 'due_today' ? 'bg-white' : 'bg-blue-500'"></span>
                        Due Today · {{ $dueTodayCount }}
                    </button>

                    <button type="button"
                            @click="filterStatus = (filterStatus === 'in_progress' ? '' : 'in_progress')"
                            :class="filterStatus === 'in_progress'
                                ? 'bg-slate-700 text-white border-slate-700 shadow-sm'
                                : 'bg-white text-slate-600 border-slate-200 hover:bg-slate-50'"
                            class="inline-flex items-center gap-1.5 rounded-lg border px-2.5 py-1 text-xs font-bold transition cursor-pointer">
                        <span class="h-1.5 w-1.5 rounded-full"
                              :class="filterStatus === 'in_progress' ? 'bg-white' : 'bg-slate-500'"></span>
                        In Progress · {{ $inProgressCount }}
                    </button>
                </div>
            </div>

            {{-- Bottom search + filters strip --}}
            <div class="flex flex-col md:flex-row items-stretch md:items-center gap-2 px-5 py-3">
                {{-- Search --}}
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                        <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>
                    <input type="text"
                           x-model="searchQuery"
                           placeholder="Search tasks by title, next action, unit, notes..."
                           class="w-full pl-9 pr-8 py-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                    <button type="button" x-show="searchQuery" @click="searchQuery = ''"
                            class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-slate-600" style="display: none;">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Category --}}
                <select x-model="filterCategory"
                        class="w-full md:w-44 py-2 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Categories</option>
                    <option value="Unit Retest">Unit Retest</option>
                    <option value="Operations Deadline">Operations Deadline</option>
                    <option value="GPS Monitoring">GPS Monitoring</option>
                    <option value="Fuel PO Checklist">Fuel PO Checklist</option>
                    <option value="Vehicle Maintenance">Vehicle Maintenance</option>
                    <option value="Route Audit">Route Audit</option>
                    @foreach($categories as $cat)
                        @if(!in_array($cat, ['Unit Retest', 'Operations Deadline', 'GPS Monitoring', 'Fuel PO Checklist', 'Vehicle Maintenance', 'Route Audit']))
                            <option value="{{ $cat }}">{{ $cat }}</option>
                        @endif
                    @endforeach
                </select>

                {{-- Priority --}}
                <select x-model="filterPriority"
                        class="w-full md:w-36 py-2 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Priorities</option>
                    <option value="urgent">Urgent</option>
                    <option value="high">High</option>
                    <option value="normal">Normal</option>
                    <option value="low">Low</option>
                </select>

                {{-- Status --}}
                <select x-model="filterStatus"
                        class="w-full md:w-36 py-2 px-2.5 bg-white border border-slate-200 rounded-lg text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <option value="">All Statuses</option>
                    <option value="overdue">Overdue</option>
                    <option value="due_today">Due Today</option>
                    <option value="in_progress">In Progress</option>
                    <option value="pending">Pending</option>
                    <option value="completed">Completed</option>
                </select>

                {{-- Reset --}}
                <div x-show="hasFilter()" class="shrink-0" style="display: none;">
                    <button type="button" @click="clearFilters()"
                            class="px-3 py-2 bg-blue-50 hover:bg-blue-100 text-blue-700 border border-blue-200 text-xs font-bold rounded-lg transition cursor-pointer whitespace-nowrap">
                        Reset Filters
                    </button>
                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             4.  MAIN CALENDAR GRID — crisp white cells, blue accents, navy borders
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <div class="overflow-x-auto">
                <div class="min-w-[900px]">

                    {{-- Week-day header strip --}}
                    <div class="grid grid-cols-7 border-b-2 border-blue-700 bg-blue-700"
                         style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr));">
                        @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $idx => $dayName)
                            <div class="py-2.5 text-center text-[11px] font-black uppercase tracking-widest text-white {{ $idx === 0 ? 'text-red-200' : '' }}">
                                {{ $dayName }}
                            </div>
                        @endforeach
                    </div>

                    {{-- Grid cells --}}
                    <div class="grid grid-cols-7"
                         style="display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); background-color: #cbd5e1; gap: 1px;">
                        @foreach($calendarDays as $day)
                            @php
                                $dayTasksData = $day['tasks']->map(function ($task) {
                                    return [
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
                                        'reopen_url' => route('work-tasks.reopen', $task),
                                        'destroy_url' => route('work-tasks.destroy', $task),
                                    ];
                                })->values();

                                $dayData = [
                                    'date' => $day['date'],
                                    'day' => $day['day'],
                                    'formatted_date' => \Carbon\Carbon::parse($day['date'])->format('l, F j, Y'),
                                    'is_today' => $day['is_today'],
                                    'is_current_month' => $day['is_current_month'],
                                    'tasks' => $dayTasksData,
                                ];

                                $dayOverdueCount = $day['tasks']->where('is_overdue', true)->count();
                                $dayOfWeek = \Carbon\Carbon::parse($day['date'])->dayOfWeek; // 0=Sun, 6=Sat
                            @endphp

                            @php
                                $cellBg = $day['is_today']
                                    ? 'bg-blue-50 ring-2 ring-inset ring-blue-600'
                                    : ($day['is_current_month']
                                        ? ($dayOfWeek === 0 ? 'bg-red-50/30' : 'bg-white hover:bg-blue-50/40')
                                        : 'bg-slate-50/80 hover:bg-slate-100/70');
                            @endphp
                            <div @click="openCreateModal('{{ $day['date'] }}')"
                                 class="group relative flex flex-col transition-colors cursor-pointer select-none {{ $cellBg }}"
                                 style="min-height: 138px; padding: 8px;">


                                {{-- Date header --}}
                                <div class="flex items-center justify-between mb-1.5 pointer-events-none">
                                    <div class="flex items-center gap-1.5">
                                        @if($day['is_today'])
                                            <span class="inline-flex h-7 w-7 items-center justify-center rounded-full bg-blue-700 text-xs font-black text-white shadow">
                                                {{ $day['day'] }}
                                            </span>
                                            <span class="text-[9px] font-black uppercase tracking-wider text-blue-700 bg-blue-100 px-1.5 py-0.5 rounded-full border border-blue-200">
                                                Today
                                            </span>
                                        @else
                                            @php
                                                $dayNumColor = !$day['is_current_month']
                                                    ? 'text-slate-300'
                                                    : ($dayOfWeek === 0 ? 'text-red-500' : 'text-slate-800');
                                            @endphp
                                            <span class="text-xs font-bold leading-none {{ $dayNumColor }}">
                                                {{ $day['day'] }}
                                            </span>
                                        @endif
                                    </div>

                                    <div class="flex items-center gap-1">
                                        {{-- Task count badge --}}
                                        @if($day['tasks']->count() > 0)
                                            @php
                                                $badgeColor = $day['is_current_month']
                                                    ? 'bg-blue-700 text-white hover:bg-blue-800'
                                                    : 'bg-slate-300 text-slate-600 hover:bg-slate-400';
                                            @endphp
                                            <button type="button"
                                                    @click.stop="openDayModal({{ json_encode($dayData) }})"
                                                    class="inline-flex items-center justify-center h-5 w-5 rounded-full text-[9px] font-black {{ $badgeColor }} transition cursor-pointer pointer-events-auto"
                                                    title="{{ $day['tasks']->count() }} task(s) · click to view">
                                                {{ $day['tasks']->count() }}
                                            </button>
                                        @endif


                                        {{-- Quick add on hover --}}
                                        <button type="button"
                                                @click.stop="openCreateModal('{{ $day['date'] }}')"
                                                class="opacity-0 group-hover:opacity-100 h-5 w-5 flex items-center justify-center text-slate-400 hover:text-blue-700 hover:bg-blue-100 rounded-full transition pointer-events-auto cursor-pointer"
                                                title="Add task on {{ $day['date'] }}">
                                            <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                            </svg>
                                        </button>
                                    </div>
                                </div>

                                {{-- Task pills --}}
                                <div class="space-y-0.5 overflow-hidden flex-1 pointer-events-auto">
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
                                                'reopen_url' => route('work-tasks.reopen', $task),
                                                'destroy_url' => route('work-tasks.destroy', $task),
                                            ];
                                        @endphp

                                        <div x-show="isTaskVisible({{ json_encode($taskJson) }})"
                                             @click.stop="openTaskModal({{ json_encode($taskJson) }})"
                                             class="group/pill relative flex items-center justify-between gap-1 pl-2 pr-1 py-0.5 rounded text-[10px] font-semibold transition cursor-pointer truncate
                                                    {{ $task->isCompleted()
                                                        ? 'bg-slate-100 text-slate-400 border border-slate-200 line-through'
                                                        : ($task->is_overdue
                                                            ? 'bg-red-600 text-white'
                                                            : ($task->priority === 'urgent'
                                                                ? 'bg-red-500 text-white'
                                                                : ($task->priority === 'high'
                                                                    ? 'bg-blue-800 text-white'
                                                                    : ($task->priority === 'low'
                                                                        ? 'bg-slate-500 text-white'
                                                                        : 'bg-blue-600 text-white')))) }}"
                                             title="{{ $task->title }}{{ $task->category ? ' [' . $task->category . ']' : '' }}{{ $task->next_action ? ' · ' . $task->next_action : '' }}">


                                            <div class="flex items-center gap-1 truncate leading-tight min-w-0">
                                                @if($task->isCompleted())
                                                    <span class="shrink-0 font-black text-slate-400">✓</span>
                                                @elseif($task->is_overdue)
                                                    <span class="h-1 w-1 rounded-full bg-white shrink-0 animate-pulse"></span>
                                                @endif
                                                <span class="truncate">{{ $task->title }}</span>
                                            </div>

                                            {{-- 1-click complete --}}
                                            @if(!$task->isCompleted())
                                                <form method="POST" action="{{ route('work-tasks.complete', $task) }}" @click.stop class="shrink-0 inline">
                                                    @csrf
                                                    @method('PATCH')
                                                    <button type="submit"
                                                            title="Mark complete"
                                                            class="opacity-0 group-hover/pill:opacity-100 p-0.5 rounded text-white/70 hover:text-white hover:bg-black/20 transition cursor-pointer">
                                                        <svg class="h-2.5 w-2.5" fill="none" viewBox="0 0 24 24" stroke-width="3.5" stroke="currentColor">
                                                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                        </svg>
                                                    </button>
                                                </form>
                                            @endif
                                        </div>
                                    @endforeach

                                    {{-- +N more --}}
                                    @if($day['tasks']->count() > 3)
                                        <button type="button"
                                                @click.stop="openDayModal({{ json_encode($dayData) }})"
                                                class="w-full text-left text-[10px] font-bold text-blue-700 hover:text-blue-900 bg-blue-50 hover:bg-blue-100 px-1.5 py-0.5 rounded transition cursor-pointer leading-tight border border-blue-100">
                                            +{{ $day['tasks']->count() - 3 }} more &rarr;
                                        </button>
                                    @endif
                                </div>

                                {{-- Overdue bottom alert bar --}}
                                @if($dayOverdueCount > 0)
                                    <div class="mt-1.5 flex items-center justify-between border-t border-red-100 pt-1">
                                        <div class="flex items-center gap-1 text-[9px] font-bold text-red-600">
                                            <span class="h-1 w-1 rounded-full bg-red-500 animate-pulse"></span>
                                            {{ $dayOverdueCount }} overdue
                                        </div>
                                        <button type="button"
                                                @click.stop="openDayModal({{ json_encode($dayData) }})"
                                                class="text-[9px] font-bold text-red-600 hover:underline pointer-events-auto">
                                            Review
                                        </button>
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>

                </div>
            </div>
        </div>

        {{-- ═══════════════════════════════════════════════════════════════════
             5.  BOTTOM LEGEND STRIP
        ═══════════════════════════════════════════════════════════════════ --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm px-5 py-3 flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4 text-xs">
                <span class="text-[10px] font-black uppercase tracking-widest text-slate-400">Legend:</span>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-red-600"></span>
                    <span class="text-slate-600 font-semibold">Overdue</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-red-500"></span>
                    <span class="text-slate-600 font-semibold">Urgent</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-blue-800"></span>
                    <span class="text-slate-600 font-semibold">High Priority</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-blue-600"></span>
                    <span class="text-slate-600 font-semibold">Normal</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-slate-500"></span>
                    <span class="text-slate-600 font-semibold">Low</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-slate-200 border border-slate-300"></span>
                    <span class="text-slate-400 font-semibold line-through">Completed</span>
                </div>
                <div class="flex items-center gap-1.5">
                    <span class="h-3 w-3 rounded bg-red-50 border border-red-200"></span>
                    <span class="text-slate-500 font-semibold">Sunday column</span>
                </div>
            </div>
            <p class="text-[11px] text-slate-400 font-medium">
                Click any cell to add a task · click a task badge to preview · click <strong class="text-blue-700">+N →</strong> for full day schedule
            </p>
        </div>


        {{-- ═══════════════════════════════════════════════════════════════════
             MODAL A — Day Schedule Drawer
        ═══════════════════════════════════════════════════════════════════ --}}
        <div x-show="showDayModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showDayModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-2xl border border-slate-200 overflow-hidden flex flex-col max-h-[88vh]" @click.stop>
                {{-- Header --}}
                <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100 bg-blue-700">
                    <div>
                        <div class="flex items-center gap-2">
                            <svg class="h-5 w-5 text-blue-200" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                            </svg>
                            <h3 class="text-base font-black text-white" x-text="selectedDay.formatted_date"></h3>
                            <template x-if="selectedDay.is_today">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-white text-blue-700">Today</span>
                            </template>
                        </div>
                        <p class="text-xs text-blue-200 mt-0.5" x-text="(selectedDay.tasks ? selectedDay.tasks.length : 0) + ' operations scheduled'"></p>
                    </div>
                    <button type="button" @click="showDayModal = false" class="text-blue-200 hover:text-white text-sm p-1.5 rounded-lg hover:bg-blue-600 cursor-pointer transition">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Task list --}}
                <div class="p-5 overflow-y-auto space-y-3 flex-1">
                    <template x-if="!selectedDay.tasks || selectedDay.tasks.length === 0">
                        <div class="text-center py-10">
                            <div class="h-14 w-14 rounded-2xl bg-blue-50 border border-blue-100 flex items-center justify-center mx-auto mb-3">
                                <svg class="h-7 w-7 text-blue-300" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                </svg>
                            </div>
                            <p class="text-sm font-semibold text-slate-500">No tasks scheduled</p>
                            <p class="text-xs text-slate-400 mt-0.5">Click below to add a task for this day</p>
                        </div>
                    </template>

                    <template x-for="task in selectedDay.tasks" :key="task.id">
                        <div class="p-4 rounded-xl border transition-all"
                             :class="task.is_overdue
                                 ? 'bg-red-50 border-red-200'
                                 : (task.is_completed
                                     ? 'bg-slate-50 border-slate-200 opacity-70'
                                     : 'bg-white border-slate-200 hover:border-blue-200 hover:shadow-sm')">

                            <div class="flex items-start justify-between gap-3">
                                <div class="space-y-1.5 flex-1 min-w-0">
                                    {{-- Badges row --}}
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                                              :class="task.priority === 'urgent' ? 'bg-red-100 text-red-700 border border-red-200' :
                                                     (task.priority === 'high' ? 'bg-blue-100 text-blue-800 border border-blue-200' :
                                                     (task.priority === 'low' ? 'bg-slate-100 text-slate-600 border border-slate-200' :
                                                     'bg-blue-50 text-blue-600 border border-blue-100'))"
                                              x-text="task.priority_label"></span>
                                        <template x-if="task.category">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200"
                                                  x-text="task.category"></span>
                                        </template>
                                        <template x-if="task.is_overdue">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-black bg-red-600 text-white">
                                                <span class="h-1 w-1 rounded-full bg-white animate-pulse"></span>
                                                OVERDUE
                                            </span>
                                        </template>
                                        <template x-if="task.is_completed">
                                            <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200 text-slate-500">✓ Done</span>
                                        </template>
                                    </div>

                                    <h4 class="text-sm font-bold text-slate-900 leading-snug"
                                        :class="task.is_completed ? 'line-through text-slate-400' : ''"
                                        x-text="task.title"></h4>

                                    {{-- Next Action box --}}
                                    <template x-if="task.next_action">
                                        <div class="flex items-start gap-2 bg-blue-50 border border-blue-100 rounded-lg p-2.5 mt-1">
                                            <svg class="h-3.5 w-3.5 text-blue-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" />
                                            </svg>
                                            <div>
                                                <span class="text-[9px] font-black uppercase tracking-widest text-blue-700 block">Next Action</span>
                                                <p class="text-xs font-semibold text-slate-800 mt-0.5" x-text="task.next_action"></p>
                                            </div>
                                        </div>
                                    </template>

                                    <template x-if="task.notes">
                                        <p class="text-xs text-slate-500 leading-relaxed" x-text="task.notes"></p>
                                    </template>
                                </div>

                                {{-- Action buttons --}}
                                <div class="flex flex-col items-end gap-1.5 shrink-0">
                                    <template x-if="!task.is_completed">
                                        <form method="POST" :action="task.complete_url" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-blue-700 hover:bg-blue-800 text-white text-[11px] font-bold rounded-lg transition cursor-pointer shadow-sm">
                                                <svg class="h-3 w-3" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                </svg>
                                                Done
                                            </button>
                                        </form>
                                    </template>
                                    <template x-if="task.is_completed">
                                        <form method="POST" :action="task.reopen_url" class="inline">
                                            @csrf
                                            @method('PATCH')
                                            <button type="submit"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1.5 bg-white border border-slate-300 text-slate-600 text-[11px] font-bold rounded-lg hover:bg-slate-50 cursor-pointer">
                                                Reopen
                                            </button>
                                        </form>
                                    </template>
                                    <div class="flex items-center gap-1">
                                        <a :href="task.edit_url"
                                           class="p-1.5 text-slate-400 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                           title="Edit">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </a>
                                        <a :href="task.show_url"
                                           class="p-1.5 text-slate-400 hover:text-blue-700 hover:bg-blue-50 rounded-lg transition"
                                           title="Full Details">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 6H5.25A2.25 2.25 0 0 0 3 8.25v10.5A2.25 2.25 0 0 0 5.25 21h10.5A2.25 2.25 0 0 0 18 18.75V10.5m-10.5 6L21 3m0 0h-5.25M21 3v5.25" />
                                            </svg>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between px-6 py-3.5 border-t border-slate-100 bg-slate-50">
                    <button type="button"
                            @click="showDayModal = false; openCreateModal(selectedDay.date)"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold rounded-lg transition cursor-pointer shadow-sm">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                        </svg>
                        Add Task for this Day
                    </button>
                    <button type="button" @click="showDayModal = false"
                            class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-semibold rounded-lg hover:bg-slate-50 cursor-pointer">
                        Close
                    </button>
                </div>
            </div>
        </div>


        {{-- ═══════════════════════════════════════════════════════════════════
             MODAL B — Create Task (Fleet Fast-Pick)
        ═══════════════════════════════════════════════════════════════════ --}}
        <div x-show="showCreateModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showCreateModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-lg border border-slate-200 overflow-hidden" @click.stop>
                {{-- Header --}}
                <div class="px-6 py-4 border-b border-slate-100 bg-blue-700">
                    <div class="flex items-center justify-between">
                        <div>
                            <h3 class="text-sm font-black text-white">Schedule Fleet Task / Retest</h3>
                            <p class="text-xs text-blue-200 mt-0.5">
                                Date: <strong class="text-white font-mono" x-text="createDate"></strong>
                            </p>
                        </div>
                        <button type="button" @click="showCreateModal = false" class="text-blue-200 hover:text-white p-1.5 rounded-lg hover:bg-blue-600 cursor-pointer transition">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                <form method="POST" action="{{ route('work-tasks.store') }}" @submit="submitting = true">
                    @csrf
                    <input type="hidden" name="status" value="pending">
                    <input type="hidden" name="sort_order" value="0">
                    <input type="hidden" name="redirect_to" value="calendar">
                    <input type="hidden" name="month" value="{{ $currentDate->format('Y-m') }}">

                    <div class="p-6 space-y-4">
                        {{-- Title --}}
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">
                                Task Title <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" x-model="createTitle" required autofocus
                                   placeholder="e.g. Unit 12 Speed Limiter Retest, CV-04 GPS Check, PO Checklist..."
                                   class="w-full px-4 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-900 font-medium placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-sm transition">
                        </div>

                        {{-- Fleet category fast-picks --}}
                        <div>
                            <div class="flex items-center justify-between mb-2">
                                <label class="text-[10px] font-black uppercase tracking-widest text-slate-500">Fleet Category</label>
                                <span class="text-[10px] text-slate-400 font-medium">tap chip or type custom</span>
                            </div>
                            <div class="flex flex-wrap gap-1.5 mb-2.5">
                                @foreach(['Unit Retest', 'Operations Deadline', 'GPS Monitoring', 'Fuel PO Checklist', 'Vehicle Maintenance', 'Route Audit'] as $presetCat)
                                    <button type="button"
                                            @click="createCategory = (createCategory === '{{ $presetCat }}' ? '' : '{{ $presetCat }}')"
                                            :class="createCategory === '{{ $presetCat }}'
                                                ? 'bg-blue-700 text-white border-blue-700 shadow-sm'
                                                : 'bg-white text-slate-600 border-slate-200 hover:border-blue-400 hover:text-blue-700'"
                                            class="px-2.5 py-1 rounded-full text-[11px] font-bold border transition cursor-pointer">
                                        {{ $presetCat }}
                                    </button>
                                @endforeach
                            </div>
                            <input type="text" name="category" x-model="createCategory"
                                   list="calendar-category-list"
                                   placeholder="Custom category..."
                                   class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-700 font-medium focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 bg-slate-50">
                            <datalist id="calendar-category-list">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}"></option>
                                @endforeach
                            </datalist>
                        </div>

                        {{-- Priority + Due Date --}}
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">
                                    Priority <span class="text-red-500">*</span>
                                </label>
                                <select name="priority" x-model="createPriority"
                                        class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm bg-white text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                                    <option value="low">Low</option>
                                    <option value="normal">Normal</option>
                                    <option value="high">High</option>
                                    <option value="urgent">Urgent</option>
                                </select>
                            </div>
                            <div>
                                <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">
                                    Due Date <span class="text-red-500">*</span>
                                </label>
                                <input type="date" name="due_date" x-model="createDate" required
                                       class="w-full px-3 py-2.5 border border-slate-200 rounded-xl text-sm text-slate-800 font-semibold focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 shadow-sm">
                            </div>
                        </div>

                        {{-- Next Action --}}
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-4">
                            <label class="block text-[10px] font-black uppercase tracking-widest text-blue-700 mb-1.5">
                                Immediate Next Action
                            </label>
                            <input type="text" name="next_action" x-model="createNextAction"
                                   placeholder="e.g. Call driver at 9 AM · Dispatch technician to garage..."
                                   class="w-full px-3 py-2 bg-white border border-blue-200 rounded-lg text-xs text-slate-800 font-medium placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-600">
                        </div>

                        {{-- Notes --}}
                        <div>
                            <label class="block text-[10px] font-black uppercase tracking-widest text-slate-500 mb-1.5">Context / Remarks</label>
                            <textarea name="notes" rows="2" x-model="createNotes"
                                      placeholder="Plate number, reference, or background context..."
                                      class="w-full px-3 py-2 border border-slate-200 rounded-xl text-xs text-slate-700 font-medium placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-600 focus:border-blue-600 resize-none bg-slate-50"></textarea>
                        </div>
                    </div>

                    <div class="flex items-center justify-between px-6 py-4 border-t border-slate-100 bg-slate-50">
                        <a :href="'{{ route('work-tasks.create') }}?due_date=' + createDate"
                           class="text-xs font-bold text-blue-700 hover:text-blue-900 hover:underline">
                            Full Form &rarr;
                        </a>
                        <div class="flex items-center gap-2">
                            <button type="button" @click="showCreateModal = false"
                                    class="px-4 py-2 bg-white border border-slate-200 text-slate-600 text-xs font-semibold rounded-xl hover:bg-slate-50 cursor-pointer shadow-sm">
                                Cancel
                            </button>
                            <button type="submit" :disabled="submitting || !createTitle.trim()"
                                    :class="(submitting || !createTitle.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-800'"
                                    class="px-5 py-2 bg-blue-700 text-white text-xs font-black rounded-xl shadow-sm cursor-pointer transition">
                                <span x-show="!submitting">Save Task</span>
                                <span x-show="submitting">Saving...</span>
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>


        {{-- ═══════════════════════════════════════════════════════════════════
             MODAL C — Task Detail Preview
        ═══════════════════════════════════════════════════════════════════ --}}
        <div x-show="showTaskModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0 scale-95"
             x-transition:enter-end="opacity-100 scale-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/50 backdrop-blur-sm" @click="showTaskModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md border border-slate-200 overflow-hidden" @click.stop>
                {{-- Header --}}
                <div class="flex items-start justify-between px-6 py-4 border-b border-slate-100"
                     :class="selectedTask.is_overdue ? 'bg-red-600' : 'bg-blue-700'">
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-[10px] font-black uppercase tracking-widest text-white/70" x-text="'#' + selectedTask.id"></span>
                            <template x-if="selectedTask.category">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-white/20 text-white" x-text="selectedTask.category"></span>
                            </template>
                        </div>
                        <h4 class="text-base font-black text-white mt-1 leading-snug max-w-xs"
                            :class="selectedTask.is_completed ? 'line-through opacity-60' : ''"
                            x-text="selectedTask.title"></h4>
                    </div>
                    <button type="button" @click="showTaskModal = false"
                            class="text-white/60 hover:text-white p-1.5 rounded-lg hover:bg-white/10 cursor-pointer transition ml-2 shrink-0">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="p-5 space-y-4">
                    {{-- Metadata grid --}}
                    <div class="grid grid-cols-2 gap-3 p-4 rounded-xl bg-slate-50 border border-slate-100 text-xs">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Status</span>
                            <span class="font-bold text-slate-800" x-text="selectedTask.status_label"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Priority</span>
                            <span class="font-bold text-slate-800" x-text="selectedTask.priority_label"></span>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Due Date</span>
                            <span class="font-bold" :class="selectedTask.is_overdue ? 'text-red-600' : 'text-slate-800'"
                                  x-text="selectedTask.due_date_formatted || 'No date'"></span>
                            <template x-if="selectedTask.is_overdue">
                                <span class="text-[9px] font-black text-red-600 block mt-0.5 uppercase tracking-wider">⚠ Overdue</span>
                            </template>
                        </div>
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-0.5">Category</span>
                            <span class="font-medium text-slate-700" x-text="selectedTask.category || '—'"></span>
                        </div>
                    </div>

                    {{-- Next Action --}}
                    <template x-if="selectedTask.next_action">
                        <div class="bg-blue-50 border border-blue-100 rounded-xl p-3.5 flex items-start gap-2.5">
                            <svg class="h-4 w-4 text-blue-700 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                            <div>
                                <span class="text-[9px] font-black uppercase tracking-widest text-blue-700 block">Immediate Next Action</span>
                                <p class="text-xs font-semibold text-slate-800 mt-0.5" x-text="selectedTask.next_action"></p>
                            </div>
                        </div>
                    </template>

                    {{-- Description --}}
                    <template x-if="selectedTask.description">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Description</span>
                            <p class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap" x-text="selectedTask.description"></p>
                        </div>
                    </template>

                    {{-- Notes --}}
                    <template x-if="selectedTask.notes">
                        <div>
                            <span class="text-[9px] font-black uppercase tracking-widest text-slate-400 block mb-1">Context Notes</span>
                            <p class="text-xs text-slate-600 leading-relaxed whitespace-pre-wrap" x-text="selectedTask.notes"></p>
                        </div>
                    </template>
                </div>

                {{-- Footer --}}
                <div class="flex items-center justify-between px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                    <a :href="selectedTask.show_url"
                       class="text-xs font-bold text-blue-700 hover:underline">
                        Full Details &rarr;
                    </a>

                    <div class="flex items-center gap-2">
                        {{-- Complete --}}
                        <template x-if="!selectedTask.is_completed">
                            <form method="POST" :action="selectedTask.complete_url" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="inline-flex items-center gap-1 px-3 py-1.5 bg-blue-700 hover:bg-blue-800 text-white text-xs font-bold rounded-xl transition shadow-sm cursor-pointer">
                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                    </svg>
                                    Done
                                </button>
                            </form>
                        </template>

                        {{-- Reopen --}}
                        <template x-if="selectedTask.is_completed">
                            <form method="POST" :action="selectedTask.reopen_url" class="inline">
                                @csrf
                                @method('PATCH')
                                <button type="submit"
                                        class="px-3 py-1.5 bg-white border border-slate-200 text-slate-600 text-xs font-bold rounded-xl hover:bg-slate-50 cursor-pointer">
                                    Reopen
                                </button>
                            </form>
                        </template>

                        {{-- Edit --}}
                        <a :href="selectedTask.edit_url"
                           class="inline-flex items-center gap-1 px-3 py-1.5 bg-white border border-slate-200 text-slate-700 text-xs font-semibold rounded-xl hover:bg-slate-50 hover:border-blue-300 hover:text-blue-700 transition shadow-sm">
                            <svg class="h-3.5 w-3.5 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                            </svg>
                            Edit
                        </a>

                        <button type="button" @click="showTaskModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-200 text-slate-600 text-xs font-semibold rounded-xl hover:bg-slate-50 cursor-pointer">
                            Close
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
