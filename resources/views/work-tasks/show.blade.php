<x-app-layout>
    @section('page-title', 'Task #' . $task->id)
    @section('breadcrumb')
        <a href="{{ route('work-tasks.index') }}" class="hover:underline">My Work</a> &bull; #{{ $task->id }}
    @endsection

    <div class="space-y-5" x-data="{ showDeleteModal: false, showCompleteModal: false, resolutionNotes: '', submitting: false }">

        {{-- Top Header Action Bar (Matching Advanced Itinerary show view) --}}
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ route('work-tasks.index') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-xs">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    Back
                </a>

                <h1 class="text-xl font-bold text-slate-900">Task #{{ $task->id }}</h1>

                @if($task->isCompleted())
                    <span class="inline-flex items-center rounded-full bg-emerald-100 px-3 py-1 text-xs font-bold text-emerald-800">
                        COMPLETED
                    </span>
                @elseif($task->isInProgress())
                    <span class="inline-flex items-center rounded-full bg-blue-100 px-3 py-1 text-xs font-bold text-blue-800">
                        IN PROGRESS
                    </span>
                @elseif($task->isOnHold())
                    <span class="inline-flex items-center rounded-full bg-slate-100 px-3 py-1 text-xs font-bold text-slate-700">
                        ON HOLD
                    </span>
                @elseif($task->isCancelled())
                    <span class="inline-flex items-center rounded-full bg-red-100 px-3 py-1 text-xs font-bold text-red-800">
                        CANCELLED
                    </span>
                @else
                    <span class="inline-flex items-center rounded-full bg-amber-100 px-3 py-1 text-xs font-bold text-amber-800">
                        PENDING
                    </span>
                @endif

                @if($task->priority === 'urgent')
                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-red-100 text-red-800">
                        Urgent
                    </span>
                @elseif($task->priority === 'high')
                    <span class="inline-flex items-center rounded px-2 py-0.5 text-xs font-semibold bg-amber-100 text-amber-800">
                        High Priority
                    </span>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @can('complete', $task)
                    @if(!$task->isCompleted())
                        <button type="button" @click="showCompleteModal = true"
                                class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-emerald-700 transition shadow-xs cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                            </svg>
                            Mark Complete
                        </button>
                    @else
                        <form method="POST" action="{{ route('work-tasks.reopen', $task) }}" onsubmit="return confirm('Reopen this task as pending?');" class="inline">
                            @csrf
                            @method('PATCH')
                            <button type="submit"
                                    class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-amber-50 hover:text-amber-800 hover:border-amber-300 transition shadow-xs cursor-pointer">
                                <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 15 3 9m0 0 6-6M3 9h12a6 6 0 0 1 0 12h-3" />
                                </svg>
                                Reopen Task
                            </button>
                        </form>
                    @endif
                @endcan

                @can('update', $task)
                    <a href="{{ route('work-tasks.edit', $task) }}"
                       class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white hover:bg-blue-700 transition shadow-xs">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        Edit
                    </a>
                @endcan

                @can('delete', $task)
                    <button type="button" @click="showDeleteModal = true"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-white px-3.5 py-2 text-xs font-semibold text-red-700 hover:bg-red-50 transition shadow-xs cursor-pointer">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        Delete
                    </button>
                @endcan
            </div>
        </div>

        {{-- Completed Accomplishment Banner & Outcome Log --}}
        @if($task->isCompleted())
            <div class="rounded-xl border border-emerald-300 bg-emerald-50/70 p-5 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-3 border-b border-emerald-200/80">
                    <div class="flex items-center gap-2.5">
                        <div class="h-8 w-8 rounded-full bg-emerald-600 text-white flex items-center justify-center font-bold text-sm shadow-xs">
                            ✓
                        </div>
                        <div>
                            <h3 class="text-sm font-bold text-emerald-950">Accomplishment Record</h3>
                            <p class="text-xs text-emerald-800">
                                Completed on {{ $task->completed_at ? $task->completed_at->format('F j, Y \a\t g:i A') : 'Completed' }}
                                @if($task->completed_at) ({{ $task->completed_at->diffForHumans() }}) @endif
                            </p>
                        </div>
                    </div>

                    {{-- Timeliness Badge --}}
                    @if($task->due_date)
                        @if($task->isCompletedOnTime())
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-emerald-100 text-emerald-800 border border-emerald-300">
                                <span class="h-2 w-2 rounded-full bg-emerald-600"></span>
                                Completed On Time
                            </span>
                        @else
                            <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-xs font-bold bg-amber-100 text-amber-900 border border-amber-300">
                                <span class="h-2 w-2 rounded-full bg-amber-600"></span>
                                Completed Past Due Date
                            </span>
                        @endif
                    @endif
                </div>

                {{-- Resolution Notes Display --}}
                <div class="pt-3">
                    <span class="text-xs font-bold text-emerald-900 uppercase tracking-wider block mb-1">
                        Outcome / Resolution Remarks
                    </span>
                    <p class="text-xs text-slate-800 font-medium whitespace-pre-wrap leading-relaxed bg-white/90 p-3.5 rounded-lg border border-emerald-200">
                        {{ $task->resolution_notes ?: 'Task marked as completed with no additional resolution remarks.' }}
                    </p>
                </div>
            </div>
        @endif

        {{-- Overdue / Due Today Notice --}}
        @if($task->is_overdue)
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-red-800 text-xs font-medium flex items-center gap-2">
                <svg class="h-4 w-4 text-red-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
                <span>This task is <strong>OVERDUE</strong>. Target deadline was {{ $task->due_date->format('F j, Y') }} ({{ $task->due_date->diffForHumans() }}).</span>
            </div>
        @elseif($task->is_due_today)
            <div class="rounded-xl border border-amber-200 bg-amber-50 p-4 text-amber-800 text-xs font-medium flex items-center gap-2">
                <svg class="h-4 w-4 text-amber-600 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                </svg>
                <span>This task is <strong>DUE TODAY</strong> — {{ $task->due_date->format('F j, Y') }}.</span>
            </div>
        @endif

        {{-- Next Action Callout (If Set) --}}
        @if($task->next_action)
            <div class="bg-white rounded-xl border border-blue-200 shadow-sm p-4 bg-gradient-to-r from-blue-50/40 to-white">
                <div class="flex items-center gap-2 mb-1.5">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <span class="text-xs font-bold uppercase tracking-wider text-blue-900">Immediate Next Action</span>
                </div>
                <p class="text-sm font-semibold text-slate-800 pl-6">
                    {{ $task->next_action }}
                </p>
            </div>
        @endif

        {{-- Task Specifications Card --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5 space-y-4">
            <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider pb-2 border-b border-slate-100">
                Task Overview
            </h3>

            <div>
                <span class="text-xs text-slate-400 block mb-1 font-semibold uppercase tracking-wider">Title</span>
                <div class="text-base font-bold text-slate-900 {{ $task->isCompleted() ? 'line-through text-slate-400' : '' }}">
                    {{ $task->title }}
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t border-slate-100">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Category</span>
                    <span class="text-xs font-medium text-slate-800">{{ $task->category ?: '—' }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Priority</span>
                    <span class="text-xs font-medium text-slate-800">{{ $task->priority_label }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Target Due Date</span>
                    <span class="text-xs font-medium {{ $task->is_overdue ? 'text-red-600 font-bold' : 'text-slate-800' }}">
                        {{ $task->due_date ? $task->due_date->format('M d, Y') : '—' }}
                    </span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Status</span>
                    <span class="text-xs font-medium text-slate-800">{{ $task->status_label }}</span>
                </div>
            </div>

            <div class="grid grid-cols-2 sm:grid-cols-4 gap-4 pt-2 border-t border-slate-100 text-xs">
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Created At</span>
                    <span class="text-slate-600">{{ $task->created_at->format('M d, Y g:i A') }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Last Updated</span>
                    <span class="text-slate-600">{{ $task->updated_at->format('M d, Y g:i A') }}</span>
                </div>
                <div>
                    <span class="text-[11px] font-semibold text-slate-400 uppercase tracking-wider block mb-1">Completed At</span>
                    <span class="text-slate-600">{{ $task->completed_at ? $task->completed_at->format('M d, Y g:i A') : '—' }}</span>
                </div>
            </div>
        </div>

        {{-- Description & Notes --}}
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 pb-2 border-b border-slate-100">
                    Description / Background
                </h3>
                <div class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap min-h-[80px]">
                    {{ $task->description ?: 'No description recorded.' }}
                </div>
            </div>

            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-2 pb-2 border-b border-slate-100">
                    Operational Notes &amp; Memory Logs
                </h3>
                <div class="text-xs text-slate-700 leading-relaxed whitespace-pre-wrap min-h-[80px]">
                    {{ $task->notes ?: 'No notes recorded.' }}
                </div>
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
                        <p class="text-xs text-slate-500 truncate max-w-[200px]">{{ $task->title }}</p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mb-4">Are you sure? This task will be permanently removed.</p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                        Cancel
                    </button>
                    <form method="POST" action="{{ route('work-tasks.destroy', $task) }}" class="inline">
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

        {{-- Complete Task Modal with Outcome Remarks --}}
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
                            <h3 class="text-sm font-bold text-slate-800">Complete Task #{{ $task->id }}</h3>
                            <p class="text-xs text-slate-500 truncate max-w-[260px]">{{ $task->title }}</p>
                        </div>
                    </div>
                    <button type="button" @click="showCompleteModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" action="{{ route('work-tasks.complete', $task) }}" @submit="submitting = true">
                    @csrf
                    @method('PATCH')

                    <div class="p-5 space-y-3">
                        <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider">
                            Outcome / Resolution Remarks
                        </label>
                        <textarea name="resolution_notes" rows="3" x-model="resolutionNotes"
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
