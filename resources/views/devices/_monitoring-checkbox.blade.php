@if($check)
    <form method="POST" action="{{ route('devices.monitoring.reopen', $device) }}">
        @csrf
        @method('PATCH')
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <button type="submit" aria-label="Mark {{ $device->device_name }} pending again" title="Checked {{ $check->checked_at->copy()->timezone($timezone)->format('M j, Y g:i A') }}. Click to undo today's check."
                class="inline-flex items-center gap-2 rounded-lg border border-emerald-300 bg-emerald-50 px-3 py-1.5 text-xs font-bold text-emerald-800 transition-colors hover:border-amber-300 hover:bg-amber-50 hover:text-amber-900 focus:outline-none focus:ring-2 focus:ring-emerald-500">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="m5 12 4 4L19 6" /></svg>
            Completed <span class="font-medium opacity-70">↺</span>
        </button>
    </form>
@else
    <form method="POST" action="{{ route('devices.monitoring.store', $device) }}">
        @csrf
        <input type="hidden" name="return_to" value="{{ $returnTo }}">
        <label class="inline-flex cursor-pointer items-center gap-2 text-xs font-semibold text-slate-700 hover:text-blue-700">
            <input type="checkbox" onchange="this.form.submit()" aria-label="Mark {{ $device->device_name }} monitored today" class="rounded border-slate-400 text-blue-600 focus:ring-blue-500">
            <span>Check today</span>
        </label>
    </form>
@endif
