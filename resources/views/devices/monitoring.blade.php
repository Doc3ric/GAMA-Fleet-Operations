<x-app-layout>
    @section('page-title', 'Daily GPS Monitoring')
    @section('breadcrumb', 'Fleet Management / GPS Devices / Daily Monitoring')

    <div class="space-y-5">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div>
                <h1 class="text-xl font-bold text-slate-900">Daily GPS Monitoring</h1>
                <p class="mt-1 text-sm text-slate-500">Review every device, record each check, and follow your daily progress. You can undo a check today if it was marked by mistake.</p>
            </div>
            <a href="{{ route('devices.index') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-50">Back to GPS devices</a>
        </div>

        <div class="grid gap-5 lg:grid-cols-[320px_minmax(0,1fr)]">
            <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm" aria-label="Monitoring calendar">
                <div class="flex items-center justify-between gap-2">
                    <a href="{{ route('devices.monitoring.index', ['date' => $previousMonth]) }}" aria-label="Previous month" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50">‹</a>
                    <h2 class="text-sm font-bold text-slate-900">{{ $selectedDay->format('F Y') }}</h2>
                    <a href="{{ route('devices.monitoring.index', ['date' => $nextMonth]) }}" aria-label="Next month" class="rounded-lg border border-slate-200 px-2.5 py-1.5 text-slate-600 hover:bg-slate-50">›</a>
                </div>

                <div class="mt-4 grid grid-cols-7 gap-1 text-center text-[11px] font-bold uppercase text-slate-500">
                    @foreach(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'] as $weekday)
                        <span>{{ $weekday }}</span>
                    @endforeach
                </div>
                <div class="mt-2 grid grid-cols-7 gap-1">
                    @foreach($calendarDays as $day)
                        <a href="{{ route('devices.monitoring.index', ['date' => $day['date']]) }}"
                           aria-label="View monitoring for {{ $day['date'] }}"
                           @if($day['date'] === $selectedDate) aria-current="date" @endif
                           class="flex min-h-11 flex-col items-center justify-center rounded-lg border text-xs transition-colors {{ $day['date'] === $selectedDate ? 'border-blue-600 bg-blue-600 font-bold text-white' : ($day['in_month'] ? 'border-transparent text-slate-700 hover:border-blue-200 hover:bg-blue-50' : 'border-transparent text-slate-400 hover:bg-slate-50') }}">
                            <span>{{ $day['day'] }}</span>
                            @if($day['checked_count'] > 0)
                                <span class="mt-0.5 h-1.5 w-1.5 rounded-full {{ $day['date'] === $selectedDate ? 'bg-white' : 'bg-emerald-500' }}"></span>
                            @endif
                        </a>
                    @endforeach
                </div>
                <form method="GET" action="{{ route('devices.monitoring.index') }}" class="mt-5 flex gap-2 border-t border-slate-100 pt-4">
                    <label for="monitoring-date" class="sr-only">Select monitoring date</label>
                    <input id="monitoring-date" type="date" name="date" value="{{ $selectedDate }}" class="min-w-0 flex-1 rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                    <button type="submit" class="rounded-lg bg-slate-800 px-3 py-2 text-sm font-semibold text-white hover:bg-slate-900">Go</button>
                </form>
                <a href="{{ route('devices.monitoring.index', ['date' => $today]) }}" class="mt-3 inline-block text-xs font-semibold text-blue-700 hover:underline">Jump to today</a>
            </section>

            <section class="space-y-4" aria-label="Monitoring overview">
                <div class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-2">
                        <div>
                            <h2 class="text-base font-bold text-slate-900">{{ $selectedDay->format('l, F j, Y') }}</h2>
                            <p class="text-xs text-slate-500">{{ $timezone }} time · {{ $selectedDate === $today ? 'Today’s checklist' : ($selectedDate < $today ? 'Historical record' : 'Upcoming checklist') }}</p>
                        </div>
                        <span class="rounded-full bg-blue-50 px-3 py-1 text-xs font-semibold text-blue-700">{{ $totalCount }} scheduled</span>
                    </div>
                    <div class="mt-4 grid grid-cols-1 gap-3 sm:grid-cols-3">
                        <a href="{{ route('devices.monitoring.index', ['date' => $selectedDate, 'status' => 'all']) }}" aria-label="Show all scheduled devices"
                           class="rounded-xl border border-blue-200 bg-blue-50 p-4 transition-colors hover:bg-blue-100 {{ $statusFilter === 'all' ? 'ring-2 ring-blue-300' : '' }}">
                            <div class="text-xs font-semibold text-blue-700">Scheduled</div>
                            <div class="mt-1 text-2xl font-bold text-blue-900">{{ $totalCount }}</div>
                        </a>
                        <a href="{{ route('devices.monitoring.index', ['date' => $selectedDate, 'status' => 'completed']) }}" aria-label="Show completed devices"
                           class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 transition-colors hover:bg-emerald-100 {{ $statusFilter === 'completed' ? 'ring-2 ring-emerald-300' : '' }}">
                            <div class="text-xs font-semibold text-emerald-700">Completed</div>
                            <div class="mt-1 text-2xl font-bold text-emerald-900">{{ $completedCount }}</div>
                        </a>
                        <a href="{{ route('devices.monitoring.index', ['date' => $selectedDate, 'status' => 'pending']) }}" aria-label="Show pending devices"
                           class="rounded-xl border border-amber-200 bg-amber-50 p-4 transition-colors hover:bg-amber-100 {{ $statusFilter === 'pending' ? 'ring-2 ring-amber-300' : '' }}">
                            <div class="text-xs font-semibold text-amber-700">{{ $selectedDate < $today ? 'Missed' : 'Remaining' }}</div>
                            <div class="mt-1 text-2xl font-bold text-amber-900">{{ $pendingCount }}</div>
                        </a>
                    </div>
                    <div class="mt-4 flex items-center justify-between gap-3 text-xs font-semibold text-slate-600">
                        <span>Daily progress</span>
                        <span>{{ $completionPercent }}%</span>
                    </div>
                    <div class="mt-2 h-2.5 overflow-hidden rounded-full bg-slate-100" role="progressbar" aria-label="Daily monitoring progress" aria-valuenow="{{ $completedCount }}" aria-valuemin="0" aria-valuemax="{{ $totalCount }}">
                        <div class="h-full rounded-full bg-emerald-500 transition-all" style="width: {{ $completionPercent }}%"></div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                    <div class="border-b border-slate-100 px-5 py-4">
                        <h3 class="text-sm font-bold text-slate-900">Devices scheduled for monitoring</h3>
                        <p class="mt-0.5 text-xs text-slate-500">Select Completed to undo today's check. Every action is saved. Past dates are read only; “Missed” means no completed check was saved.</p>
                    </div>
                    <form method="GET" action="{{ route('devices.monitoring.index') }}" class="grid gap-2 border-b border-slate-100 bg-slate-50/70 px-5 py-3 sm:grid-cols-[minmax(0,1fr)_160px_auto]">
                        <input type="hidden" name="date" value="{{ $selectedDate }}">
                        <label for="device-monitoring-search" class="sr-only">Search devices</label>
                        <input id="device-monitoring-search" type="search" name="search" value="{{ $search }}" placeholder="Search vehicle, device, or IMEI" class="w-full rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                        <label for="device-monitoring-status" class="sr-only">Filter by status</label>
                        <select id="device-monitoring-status" name="status" class="rounded-lg border-slate-300 text-sm focus:border-blue-500 focus:ring-blue-500">
                            <option value="all" @selected($statusFilter === 'all')>All statuses</option>
                            <option value="pending" @selected($statusFilter === 'pending')>Pending only</option>
                            <option value="completed" @selected($statusFilter === 'completed')>Completed only</option>
                        </select>
                        <button type="submit" class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Filter</button>
                    </form>
                    <div class="divide-y divide-slate-100">
                        @forelse($displayDevices as $device)
                            @php($check = $checks->get($device->id))
                            <div class="flex flex-wrap items-center justify-between gap-3 px-5 py-4">
                                <div class="min-w-0">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span class="font-semibold text-slate-900">{{ $check?->device_name ?? $device->device_name }}</span>
                                        @if($device->trashed())
                                            <span class="rounded bg-slate-100 px-1.5 py-0.5 text-[10px] font-semibold text-slate-500">Removed from inventory</span>
                                        @endif
                                    </div>
                                    <div class="mt-0.5 text-xs text-slate-500">{{ $check?->vehicle_label ?? $device->vehicle?->equipment_code ?? 'Unpaired vehicle' }} · IMEI {{ $check?->imei ?? $device->imei ?? '—' }}</div>
                                </div>
                                <div class="flex items-center gap-3">
                                    @if($check?->is_completed)
                                        <div class="text-right">
                                            @if($selectedDate === $today && ! $device->trashed())
                                                @include('devices._monitoring-checkbox', ['returnTo' => 'monitoring'])
                                            @else
                                                <span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-bold text-emerald-800">Completed</span>
                                            @endif
                                            <div class="mt-1 text-xs text-slate-500">{{ $check->checked_at->copy()->timezone($timezone)->format('g:i A') }} · {{ $check->checker?->name ?? 'Former user' }}</div>
                                        </div>
                                    @elseif($selectedDate === $today && ! $device->trashed())
                                        <div class="text-right">
                                            @include('devices._monitoring-checkbox', ['returnTo' => 'monitoring'])
                                            @if($check?->reopened_at)
                                                <div class="mt-1 text-xs text-slate-500">Returned to pending at {{ $check->reopened_at->copy()->timezone($timezone)->format('g:i A') }}</div>
                                            @endif
                                        </div>
                                    @else
                                        <span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-bold text-amber-800">{{ $selectedDate < $today ? 'Missed' : 'Pending' }}</span>
                                    @endif
                                </div>
                            </div>
                        @empty
                            <p class="px-5 py-10 text-center text-sm text-slate-500">{{ $totalCount === 0 ? 'No GPS devices were in this monitoring roster.' : 'No devices match these filters.' }}</p>
                        @endforelse
                    </div>
                </div>
            </section>
        </div>
    </div>
</x-app-layout>
