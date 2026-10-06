@php
    /**
     * Shared form partial for create and edit.
     * Follows GAMA Fleet Operations enterprise form standards.
     *
     * Expected variables in scope:
     * @var App\Models\WorkTask|null $task
     * @var Illuminate\Support\Collection $categories
     * @var string $formAction  — the form action URL
     * @var string $method      — 'POST' or 'PATCH'
     * @var string $submitLabel — button text
     * @var string $pageTitle   — displayed as page heading
     */
    $old = fn(string $field, $default = null) => old($field, $task?->{$field} ?? $default);
    $currentStatus = $old('status', \App\Models\WorkTask::STATUS_PENDING);
    $currentPriority = $old('priority', \App\Models\WorkTask::PRIORITY_NORMAL);
    $dueDateRaw = $old('due_date', request('due_date'));
    $dueDateVal = $dueDateRaw ? \Carbon\Carbon::parse($dueDateRaw)->format('Y-m-d') : '';
@endphp

<x-app-layout>
    @section('page-title', $pageTitle)
    @section('breadcrumb', 'My Work / ' . $pageTitle)

    <div class="max-w-4xl pb-16">

        {{-- Top Sticky Action Bar --}}
        <div class="sticky top-0 z-10 bg-slate-50/95 backdrop-blur-xs py-3 mb-5 border-b border-slate-200/80 flex items-center justify-between gap-4">
            <div class="flex items-center gap-3">
                <a href="{{ isset($task) ? route('work-tasks.show', $task) : route('work-tasks.index') }}"
                   class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-200 bg-white text-xs font-semibold text-slate-700 hover:text-slate-900 hover:bg-slate-50 transition-colors shadow-2xs"
                   title="Back">
                    <svg class="h-4 w-4 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
                    </svg>
                    <span>Back</span>
                </a>
                <div>
                    <h2 class="text-lg font-bold text-slate-800">{{ $pageTitle }}</h2>
                    <p class="text-xs text-slate-500">Fleet Operations &bull; Personal Work Memory</p>
                </div>
            </div>

            <div class="flex items-center gap-2.5">
                <a href="{{ isset($task) ? route('work-tasks.show', $task) : route('work-tasks.index') }}"
                   class="inline-flex items-center gap-1.5 px-4 py-2 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50 transition-colors shadow-xs">
                    <svg class="h-3.5 w-3.5 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                    <span>Cancel</span>
                </a>
                <button type="submit" form="work-task-form"
                        class="inline-flex items-center gap-1.5 px-5 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    {{ $submitLabel }}
                </button>
            </div>
        </div>

        {{-- Validation Errors --}}
        @if($errors->any())
            <div class="mb-5 rounded-lg bg-red-50 border border-red-200 px-4 py-3 text-xs text-red-800">
                <div class="font-bold mb-1">Please correct the following errors:</div>
                <ul class="list-disc list-inside space-y-0.5">
                    @foreach($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        {{-- Form --}}
        <form id="work-task-form" method="POST" action="{{ $formAction }}" class="space-y-5">
            @csrf
            @if($method === 'PATCH')
                @method('PATCH')
            @endif

            {{-- Card 1: Task Information --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100 flex items-center justify-between">
                    <span>Task Specification</span>
                    <span class="text-[11px] font-normal text-slate-400 normal-case">Fields marked with <span class="text-red-500">*</span> are required</span>
                </h3>

                <div class="space-y-4">
                    {{-- Title --}}
                    <div>
                        <label for="title" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                            Task Title <span class="text-red-500">*</span>
                        </label>
                        <input type="text" id="title" name="title"
                               value="{{ $old('title') }}"
                               required
                               placeholder="e.g. Retest CV-05 fuel consumption, Verify GPS installation on Unit 12"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('title') border-red-400 @enderror">
                        @error('title') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- 3-Column: Category, Status, Priority --}}
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        {{-- Category --}}
                        <div>
                            <label for="category" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Category
                            </label>
                            <input type="text" id="category" name="category"
                                   value="{{ $old('category') }}"
                                   list="category-suggestions"
                                   placeholder="e.g. GPS Monitoring"
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                            <datalist id="category-suggestions">
                                @foreach($categories as $cat)
                                    <option value="{{ $cat }}"></option>
                                @endforeach
                                @foreach(['GPS Monitoring', 'Vehicle Monitoring', 'Fuel Monitoring', 'Driver Monitoring', 'Itinerary', 'Reports', 'Fleet Management', 'Administrative'] as $suggest)
                                    @if(!$categories->contains($suggest))
                                        <option value="{{ $suggest }}"></option>
                                    @endif
                                @endforeach
                            </datalist>
                            @error('category') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Status --}}
                        <div>
                            <label for="status" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Status <span class="text-red-500">*</span>
                            </label>
                            <select id="status" name="status"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('status') border-red-400 @enderror">
                                <option value="pending"     @selected($currentStatus === 'pending')>Pending</option>
                                <option value="in_progress" @selected($currentStatus === 'in_progress')>In Progress</option>
                                <option value="on_hold"     @selected($currentStatus === 'on_hold')>On Hold</option>
                                <option value="completed"   @selected($currentStatus === 'completed')>Completed</option>
                                <option value="cancelled"   @selected($currentStatus === 'cancelled')>Cancelled</option>
                            </select>
                            @error('status') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>

                        {{-- Priority --}}
                        <div>
                            <label for="priority" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                                Priority <span class="text-red-500">*</span>
                            </label>
                            <select id="priority" name="priority"
                                    class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm bg-white text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 @error('priority') border-red-400 @enderror">
                                <option value="low"    @selected($currentPriority === 'low')>Low</option>
                                <option value="normal" @selected($currentPriority === 'normal')>Normal</option>
                                <option value="high"   @selected($currentPriority === 'high')>High</option>
                                <option value="urgent" @selected($currentPriority === 'urgent')>Urgent</option>
                            </select>
                            @error('priority') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    {{-- Due Date --}}
                    <div class="sm:w-1/2 pr-0 sm:pr-2">
                        <label for="due_date" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                            Target Due Date
                        </label>
                        <input type="date" id="due_date" name="due_date"
                               value="{{ $dueDateVal }}"
                               class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        @error('due_date') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Card 2: Immediate Next Action --}}
            <div class="bg-white rounded-xl border border-blue-200 shadow-sm p-5 bg-gradient-to-r from-blue-50/30 to-white">
                <div class="flex items-center gap-2 mb-2 pb-2 border-b border-blue-100">
                    <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                    </svg>
                    <h3 class="text-xs font-bold text-blue-900 uppercase tracking-wider">Immediate Next Action</h3>
                    <span class="text-[11px] text-blue-600 font-normal">— What specific action needs to happen next?</span>
                </div>
                <input type="text" id="next_action" name="next_action"
                       value="{{ $old('next_action') }}"
                       placeholder="e.g. Call driver Alex to re-confirm odometer, check TrackSolid live signal at 2 PM"
                       class="w-full px-3 py-2.5 bg-white border border-blue-200 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                @error('next_action') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
            </div>

            {{-- Card 3: Context & Working Notes --}}
            <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-5">
                <h3 class="text-xs font-bold text-slate-700 uppercase tracking-wider mb-4 pb-2 border-b border-slate-100">
                    Context &amp; Operational Notes
                </h3>

                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    {{-- Description --}}
                    <div>
                        <label for="description" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                            Description
                        </label>
                        <textarea id="description" name="description" rows="4"
                                  placeholder="Detailed background context on this task..."
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ $old('description') }}</textarea>
                        @error('description') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>

                    {{-- Notes --}}
                    <div>
                        <label for="notes" class="block text-xs font-semibold text-slate-600 uppercase tracking-wider mb-1">
                            Working Notes / Logs
                        </label>
                        <textarea id="notes" name="notes" rows="4"
                                  placeholder="Log progress, phone calls, or remarks to remember..."
                                  class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 resize-none">{{ $old('notes') }}</textarea>
                        @error('notes') <p class="text-xs text-red-500 mt-1">{{ $message }}</p> @enderror
                    </div>
                </div>
            </div>

            {{-- Bottom Save Bar --}}
            <div class="flex items-center justify-between bg-white rounded-xl border border-slate-200 shadow-sm p-4">
                <a href="{{ isset($task) ? route('work-tasks.show', $task) : route('work-tasks.index') }}"
                   class="px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-200 transition-colors">
                    Cancel
                </a>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-6 py-2.5 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition-colors shadow-sm cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    {{ $submitLabel }}
                </button>
            </div>

        </form>
    </div>
</x-app-layout>
