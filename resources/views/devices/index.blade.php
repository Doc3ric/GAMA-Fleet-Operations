<x-app-layout>
    @section('page-title', 'GPS Devices')
    @section('breadcrumb', 'Fleet Management / GPS Devices')

    <div class="space-y-4" x-data="{
        viewMode: localStorage.getItem('device_view_mode') || 'overview',
        setViewMode(mode) {
            this.viewMode = mode;
            localStorage.setItem('device_view_mode', mode);
        },
        deleteId: null,
        deleteName: '',
        deleteAction: '',
        confirmDelete(id, name, url) {
            this.deleteId = id;
            this.deleteName = name;
            this.deleteAction = url;
        },
        showImportModal: false,
        expandedRows: {}
    }">

        {{-- Top Action Header --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">GPS Device Monitoring</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-50 text-blue-700 border border-blue-200">
                        {{ $totalCount }} {{ Str::plural('Unit', $totalCount) }}
                    </span>
                    @if($expiringSoonCount > 0)
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-amber-100 text-amber-800 border border-amber-200 animate-pulse">
                            <span class="h-1.5 w-1.5 rounded-full bg-amber-600"></span>
                            {{ $expiringSoonCount }} Expiring in ≤ 30d
                        </span>
                    @endif
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Track all vehicle GPS trackers, SIM accounts, and monitor upcoming subscription expirations</p>
            </div>

            <div class="flex items-center gap-2 shrink-0">
                {{-- Download Template Button --}}
                <a href="{{ route('devices.downloadTemplate') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="Download Excel Template (Tracksolid format)">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Template</span>
                </a>

                {{-- Export Excel Button --}}
                <a href="{{ route('devices.export', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors"
                   title="Export devices list to Excel">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Excel</span>
                </a>

                {{-- Export PDF Report Button --}}
                <a href="{{ route('devices.pdf', request()->query()) }}"
                   class="inline-flex items-center gap-1.5 rounded-lg border border-red-200 bg-red-50/60 px-3 py-2 text-xs font-semibold text-red-700 shadow-xs hover:bg-red-100 hover:text-red-900 transition-colors"
                   title="Generate and download printable PDF report">
                    <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>PDF Report</span>
                </a>

                {{-- Import Data Button --}}
                <button type="button" @click="showImportModal = true"
                        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-300 bg-white px-3 py-2 text-xs font-semibold text-slate-700 shadow-xs hover:bg-slate-50 hover:text-slate-900 transition-colors cursor-pointer"
                        title="Import devices from Tracksolid/Excel">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 8.25H7.5a2.25 2.25 0 0 0-2.25 2.25v9a2.25 2.25 0 0 0 2.25 2.25h9a2.25 2.25 0 0 0 2.25-2.25v-9a2.25 2.25 0 0 0-2.25-2.25H15m0-3-3-3m0 0-3 3m3-3V15" />
                    </svg>
                    <span>Import Data</span>
                </button>

                {{-- Sync with Vehicles Button --}}
                <form action="{{ route('devices.syncVehicles') }}" method="POST" class="inline">
                    @csrf
                    <button type="submit"
                            class="inline-flex items-center gap-1.5 rounded-lg border border-blue-300 bg-blue-50/50 px-3 py-2 text-xs font-semibold text-blue-700 shadow-xs hover:bg-blue-100 hover:text-blue-900 transition-colors cursor-pointer"
                            title="Auto-link GPS devices with Vehicle Master List and sync status">
                        <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
                        </svg>
                        <span>Sync Vehicles</span>
                    </button>
                </form>

                {{-- Add Device Button --}}
                <a href="{{ route('devices.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Device</span>
                </a>
            </div>
        </div>

        {{-- Expiration Alert Banner (30-day budget reminder) --}}
        @if($expiringSoonCount > 0 || $expiredCount > 0)
            <div class="rounded-xl border border-red-200 bg-gradient-to-r from-red-50 via-amber-50/40 to-white p-4 shadow-xs">
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                    <div class="flex items-start sm:items-center gap-3">
                        <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-red-600 text-white shadow-sm">
                            <svg class="h-5 w-5 animate-pulse" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                            </svg>
                        </div>
                        <div>
                            <div class="flex items-center gap-2">
                                <h3 class="text-sm font-bold text-red-900">GPS Renewal / Budget Request Notice</h3>
                                <span class="rounded-full bg-red-600 px-2 py-0.5 text-[10px] font-extrabold uppercase tracking-wide text-white">Action Required</span>
                            </div>
                            <p class="text-xs text-red-700 mt-0.5">
                                @if($expiringSoonCount > 0 && $expiredCount > 0)
                                    <strong>{{ $expiringSoonCount }} device(s)</strong> are expiring within the next 30 days and <strong>{{ $expiredCount }} device(s)</strong> are already expired. Please request budget from management for renewal.
                                @elseif($expiringSoonCount > 0)
                                    <strong>{{ $expiringSoonCount }} device(s)</strong> will expire within the next 30 days. Advance budgeting for GPS subscription renewal is required.
                                @else
                                    <strong>{{ $expiredCount }} device(s)</strong> have expired subscriptions. Immediate renewal is recommended.
                                @endif
                            </p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        @if($expiringSoonCount > 0)
                            <a href="{{ route('devices.index', ['status' => 'expiring_soon']) }}"
                               class="rounded-lg bg-red-600 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-red-700 transition-colors">
                                View Expiring Units ({{ $expiringSoonCount }})
                            </a>
                        @endif
                        @if($expiredCount > 0)
                            <a href="{{ route('devices.index', ['status' => 'expired']) }}"
                               class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-bold text-white shadow-xs hover:bg-slate-900 transition-colors">
                                View Expired ({{ $expiredCount }})
                            </a>
                        @endif
                        <a href="{{ route('devices.pdf', ['status' => 'expiring_soon']) }}"
                           class="rounded-lg bg-white border border-red-300 px-3 py-1.5 text-xs font-bold text-red-700 shadow-xs hover:bg-red-50 transition-colors inline-flex items-center gap-1.5">
                            <svg class="h-3.5 w-3.5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                            </svg>
                            <span>Download Budget PDF</span>
                        </a>
                    </div>
                </div>
            </div>
        @endif

        {{-- Metric Summary Cards --}}
        <div class="grid grid-cols-2 lg:grid-cols-4 gap-3">
            {{-- Card 1: Total Devices --}}
            <a href="{{ route('devices.index') }}"
               class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs hover:border-blue-300 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Devices</span>
                    <div class="rounded-lg bg-blue-50 p-1.5 text-blue-600">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 0 1-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 0 0-3.213-9.193 2.056 2.056 0 0 0-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 0 0-10.026 0 1.106 1.106 0 0 0-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-slate-800">{{ number_format($totalCount) }}</div>
                <div class="mt-0.5 text-[11px] text-slate-500 font-medium">All GPS units installed</div>
            </a>

            {{-- Card 2: Expiring Soon (≤ 30 Days) --}}
            <a href="{{ route('devices.index', ['status' => 'expiring_soon']) }}"
               class="rounded-xl border {{ $expiringSoonCount > 0 ? 'border-amber-300 bg-amber-50/40 ring-1 ring-amber-300/60' : 'border-slate-200 bg-white' }} p-4 shadow-xs hover:border-amber-400 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-amber-700">Expires ≤ 30 Days</span>
                    <div class="rounded-lg bg-amber-100 p-1.5 text-amber-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-amber-900">{{ number_format($expiringSoonCount) }}</span>
                    @if($expiringSoonCount > 0)
                        <span class="text-[10px] font-bold uppercase text-amber-600 bg-amber-100 px-1.5 py-0.5 rounded">Budget Soon</span>
                    @endif
                </div>
                <div class="mt-0.5 text-[11px] text-amber-700 font-medium">Requires budget for renewal</div>
            </a>

            {{-- Card 3: Already Expired --}}
            <a href="{{ route('devices.index', ['status' => 'expired']) }}"
               class="rounded-xl border {{ $expiredCount > 0 ? 'border-red-300 bg-red-50/40 ring-1 ring-red-300/60' : 'border-slate-200 bg-white' }} p-4 shadow-xs hover:border-red-400 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-red-700">Already Expired</span>
                    <div class="rounded-lg bg-red-100 p-1.5 text-red-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 flex items-baseline gap-2">
                    <span class="text-2xl font-black text-red-900">{{ number_format($expiredCount) }}</span>
                    @if($expiredCount > 0)
                        <span class="text-[10px] font-bold uppercase text-red-600 bg-red-100 px-1.5 py-0.5 rounded">Urgent</span>
                    @endif
                </div>
                <div class="mt-0.5 text-[11px] text-red-700 font-medium">Subscription inactive</div>
            </a>

            {{-- Card 4: Active / Safe (> 30 Days) --}}
            <a href="{{ route('devices.index', ['status' => 'active']) }}"
               class="rounded-xl border border-slate-200 bg-white p-4 shadow-xs hover:border-emerald-300 transition-all">
                <div class="flex items-center justify-between">
                    <span class="text-[11px] font-bold uppercase tracking-wider text-emerald-700">Active (> 30 Days)</span>
                    <div class="rounded-lg bg-emerald-50 p-1.5 text-emerald-700">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </div>
                </div>
                <div class="mt-2 text-2xl font-black text-emerald-900">{{ number_format($activeCount) }}</div>
                <div class="mt-0.5 text-[11px] text-emerald-600 font-medium">No immediate action needed</div>
            </a>
        </div>

        {{-- Integrated Search & Filter Toolbar --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3">
            <form method="GET" action="{{ route('devices.index') }}" class="space-y-3">
                {{-- Quick Status Filter Pills --}}
                <div class="flex items-center gap-1.5 overflow-x-auto pb-1 text-xs border-b border-slate-100">
                    <span class="text-slate-400 font-semibold text-[10px] uppercase tracking-wider mr-1">Filter:</span>
                    <a href="{{ route('devices.index', array_merge(request()->except('status', 'page'), [])) }}"
                       class="px-2.5 py-1 rounded-lg font-semibold transition-colors {{ !request('status') ? 'bg-slate-900 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
                        All ({{ $totalCount }})
                    </a>
                    <a href="{{ route('devices.index', array_merge(request()->except('status', 'page'), ['status' => 'expiring_soon'])) }}"
                       class="px-2.5 py-1 rounded-lg font-semibold transition-colors flex items-center gap-1.5 {{ request('status') === 'expiring_soon' ? 'bg-amber-600 text-white' : 'bg-amber-50 text-amber-800 hover:bg-amber-100 border border-amber-200' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ request('status') === 'expiring_soon' ? 'bg-white' : 'bg-amber-500' }}"></span>
                        Expiring in ≤ 30 Days ({{ $expiringSoonCount }})
                    </a>
                    <a href="{{ route('devices.index', array_merge(request()->except('status', 'page'), ['status' => 'expired'])) }}"
                       class="px-2.5 py-1 rounded-lg font-semibold transition-colors flex items-center gap-1.5 {{ request('status') === 'expired' ? 'bg-red-600 text-white' : 'bg-red-50 text-red-800 hover:bg-red-100 border border-red-200' }}">
                        <span class="h-1.5 w-1.5 rounded-full {{ request('status') === 'expired' ? 'bg-white' : 'bg-red-500' }}"></span>
                        Expired ({{ $expiredCount }})
                    </a>
                    <a href="{{ route('devices.index', array_merge(request()->except('status', 'page'), ['status' => 'active'])) }}"
                       class="px-2.5 py-1 rounded-lg font-semibold transition-colors {{ request('status') === 'active' ? 'bg-emerald-600 text-white' : 'bg-emerald-50 text-emerald-800 hover:bg-emerald-100 border border-emerald-200' }}">
                        Active ({{ $activeCount }})
                    </a>
                </div>

                {{-- Search & Dropdowns --}}
                <div class="grid grid-cols-1 sm:grid-cols-12 gap-2">
                    @if(request('status'))
                        <input type="hidden" name="status" value="{{ request('status') }}">
                    @endif

                    <div class="sm:col-span-5 relative">
                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                            <svg class="h-4 w-4 text-slate-400" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                            </svg>
                        </div>
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search device name, IMEI, SIM, ICCID, IMSI, model, group..."
                               class="w-full pl-9 pr-3 py-1.5 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:border-blue-500 transition-colors">
                    </div>

                    <div class="sm:col-span-3">
                        <select name="group" class="w-full py-1.5 px-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                            <option value="">All Groups</option>
                            @foreach($groups as $grp)
                                <option value="{{ $grp }}" {{ request('group') === $grp ? 'selected' : '' }}>{{ $grp }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2">
                        <select name="model" class="w-full py-1.5 px-2 bg-slate-50 hover:bg-white focus:bg-white border border-slate-200 rounded-lg text-xs focus:outline-none focus:ring-2 focus:ring-blue-500 transition-colors">
                            <option value="">All Models</option>
                            @foreach($models as $mdl)
                                <option value="{{ $mdl }}" {{ request('model') === $mdl ? 'selected' : '' }}>{{ $mdl }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="sm:col-span-2 flex items-center gap-1.5">
                        <button type="submit"
                                class="flex-1 px-3 py-1.5 bg-slate-800 text-white text-xs font-semibold rounded-lg hover:bg-slate-900 transition-colors cursor-pointer shadow-xs text-center">
                            Filter
                        </button>
                        @if(request()->hasAny(['search', 'status', 'group', 'model']))
                            <a href="{{ route('devices.index') }}"
                               class="px-2.5 py-1.5 bg-slate-100 text-slate-600 text-xs font-medium rounded-lg hover:bg-slate-200 transition-colors"
                               title="Clear all filters">
                                Reset
                            </a>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        {{-- ===================== DATA TABLE CARD ===================== --}}
        <div class="rounded-2xl bg-white shadow-sm border border-slate-200 overflow-hidden">
            {{-- Table Header with View Switcher --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between px-6 py-4 border-b border-slate-100 gap-3">
                <div class="flex items-center gap-3">
                    <h3 class="text-sm font-bold text-slate-800">Device Inventory</h3>
                    <span class="inline-flex items-center rounded-full bg-slate-100 border border-slate-200 px-2.5 py-0.5 text-xs font-medium text-slate-500">
                        {{ $devices->total() }} {{ Str::plural('unit', $devices->total()) }}
                    </span>
                </div>

                {{-- Segmented View Switcher Pills --}}
                <div class="flex items-center gap-1 bg-slate-100 p-1 rounded-xl border border-slate-200 text-xs self-start sm:self-auto">
                    <button type="button"
                            @click="setViewMode('overview')"
                            :class="viewMode === 'overview' ? 'bg-white text-blue-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg transition-all cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                        </svg>
                        <span>Overview View</span>
                    </button>

                    <button type="button"
                            @click="setViewMode('full')"
                            :class="viewMode === 'full' ? 'bg-white text-blue-700 font-bold shadow-xs' : 'text-slate-600 hover:text-slate-900 font-medium'"
                            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg transition-all cursor-pointer">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 0 1 6 3.75h2.25A2.25 2.25 0 0 1 10.5 6v2.25a2.25 2.25 0 0 1-2.25 2.25H6a2.25 2.25 0 0 1-2.25-2.25V6ZM3.75 15.75A2.25 2.25 0 0 1 6 13.5h2.25a2.25 2.25 0 0 1 2.25 2.25V18a2.25 2.25 0 0 1-2.25 2.25H6A2.25 2.25 0 0 1 3.75 18v-2.25ZM13.5 6a2.25 2.25 0 0 1 2.25-2.25H18A2.25 2.25 0 0 1 20.25 6v2.25A2.25 2.25 0 0 1 18 10.5h-2.25a2.25 2.25 0 0 1-2.25-2.25V6ZM13.5 15.75a2.25 2.25 0 0 1 2.25-2.25H18a2.25 2.25 0 0 1 2.25 2.25V18A2.25 2.25 0 0 1 18 20.25h-2.25A2.25 2.25 0 0 1 13.5 18v-2.25Z" />
                        </svg>
                        <span>Full Sheet (11 Cols)</span>
                    </button>
                </div>
            </div>

            {{-- ===================== VIEW 1: OVERVIEW VIEW ===================== --}}
            <div x-show="viewMode === 'overview'">
                {{-- Column Headers --}}
                <div class="grid grid-cols-12 gap-3 px-6 py-2.5 bg-slate-50 border-b border-slate-100 text-[10px] font-bold uppercase tracking-widest text-slate-400">
                    <div class="col-span-12 sm:col-span-4">Vehicle & Device</div>
                    <div class="hidden sm:block sm:col-span-3">User Expiration & Budget</div>
                    <div class="hidden md:block md:col-span-2">SIM & Group</div>
                    <div class="hidden md:block md:col-span-1">Mileage</div>
                    <div class="hidden sm:block sm:col-span-2 md:col-span-2 text-right">Actions</div>
                </div>

                {{-- Rows --}}
                <div class="divide-y divide-slate-100">
                    @forelse($devices as $device)
                        @php
                            $badge = $device->expiration_badge;
                            $isExpiring = $badge['status'] === 'expiring_soon';
                            $isExpired = $badge['status'] === 'expired';
                        @endphp
                        <div class="px-6 py-4 hover:bg-slate-50/70 transition-colors {{ $isExpiring ? 'bg-amber-50/15' : ($isExpired ? 'bg-red-50/15' : '') }}">
                            <div class="grid grid-cols-12 gap-3 items-center">
                                {{-- Vehicle & Device --}}
                                <div class="col-span-12 sm:col-span-4 flex items-center gap-3">
                                    <div class="flex h-9 w-9 flex-shrink-0 items-center justify-center rounded-xl bg-blue-50 text-blue-600 border border-blue-100 shadow-xs">
                                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.348 14.652a3.75 3.75 0 0 1 0-5.304m5.304 0a3.75 3.75 0 0 1 0 5.304m-7.425 2.122a6.75 6.75 0 0 1 0-9.546m9.546 0a6.75 6.75 0 0 1 0 9.546M5.106 18.894c-3.808-3.808-3.808-9.98 0-13.789m13.788 0c3.808 3.808 3.808 9.98 0 13.788M12 12h.008v.008H12V12Zm.375 0a.375.375 0 1 1-.75 0 .375.375 0 0 1 .75 0Z" />
                                        </svg>
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 flex-wrap">
                                            <a href="{{ route('devices.show', $device) }}"
                                               class="text-sm font-bold text-slate-900 hover:text-blue-600 transition-colors truncate">
                                                {{ $device->device_name }}
                                            </a>
                                            @if($device->model)
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-bold bg-slate-100 text-slate-700 border border-slate-200">
                                                    {{ $device->model }}
                                                </span>
                                            @endif
                                            @if($device->vehicle)
                                                <a href="{{ route('vehicles.show', $device->vehicle) }}"
                                                   class="inline-flex items-center gap-1 px-1.5 py-0.5 rounded text-[10px] font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition-colors"
                                                   title="Linked Vehicle: {{ $device->vehicle->equipment_code }} (Plate: {{ $device->vehicle->plate_number ?: 'N/A' }})">
                                                    <span>🚗</span>
                                                    <span>{{ $device->vehicle->equipment_code }}</span>
                                                </a>
                                            @else
                                                <span class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium bg-slate-100 text-slate-400" title="Not linked to any vehicle record">
                                                    Unpaired
                                                </span>
                                            @endif
                                        </div>
                                        <div class="text-[11px] text-slate-400 font-mono truncate mt-0.5">
                                            IMEI: {{ $device->imei ?: '-' }}
                                        </div>
                                    </div>
                                </div>

                                {{-- Expiration & Budget Status --}}
                                <div class="col-span-12 sm:col-span-3 flex flex-col gap-1">
                                    <div class="flex items-center gap-2">
                                        <span class="text-xs font-extrabold text-slate-800">
                                            {{ $device->expiration_date ? $device->expiration_date->format('M j, Y') : ($device->raw_expiration ?: '-') }}
                                        </span>
                                    </div>
                                    <div>
                                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold border {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['border'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $badge['dot'] }} {{ $badge['pulse'] ? 'animate-ping' : '' }}"></span>
                                            {{ $badge['label'] }}
                                        </span>
                                    </div>
                                </div>

                                {{-- SIM & Group --}}
                                <div class="col-span-6 md:col-span-2">
                                    <div class="text-xs font-bold font-mono text-slate-700">{{ $device->sim ?: '-' }}</div>
                                    <div class="text-[11px] text-slate-400 truncate mt-0.5">{{ $device->group_name ?: 'No Group' }}</div>
                                </div>

                                {{-- Mileage --}}
                                <div class="col-span-6 md:col-span-1">
                                    <span class="inline-flex items-center rounded-lg bg-slate-100 border border-slate-200 px-2 py-1 text-xs font-bold font-mono text-slate-700">
                                        {{ $device->mileage !== null ? number_format($device->mileage, 1).' km' : '-' }}
                                    </span>
                                </div>

                                {{-- Actions & Expand Trigger --}}
                                <div class="col-span-12 sm:col-span-2 md:col-span-2 flex items-center justify-end gap-1.5 flex-wrap">
                                    <a href="{{ route('devices.show', $device) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2 py-1 text-xs font-bold text-blue-700 hover:bg-blue-100 transition-colors"
                                       title="View Details">
                                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                        </svg>
                                        <span>View</span>
                                    </a>

                                    <a href="{{ route('devices.edit', $device) }}"
                                       class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition-colors"
                                       title="Edit Device">
                                        <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                        </svg>
                                        <span>Edit</span>
                                    </a>

                                    <button type="button"
                                            @click="confirmDelete('{{ $device->id }}', '{{ $device->device_name }}', '{{ route('devices.destroy', $device) }}')"
                                            class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors cursor-pointer"
                                            title="Delete Device">
                                        <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                        </svg>
                                        <span>Delete</span>
                                    </button>

                                    <button type="button"
                                            @click="expandedRows['{{ $device->id }}'] = !expandedRows['{{ $device->id }}']"
                                            class="p-1 text-slate-400 hover:text-slate-600 rounded hover:bg-slate-100 transition-colors inline-flex items-center gap-0.5"
                                            title="Toggle Specs (ICCID, IMSI)">
                                        <span class="text-[10px] font-semibold" x-text="expandedRows['{{ $device->id }}'] ? 'Less' : 'Specs'">Specs</span>
                                        <svg class="h-3.5 w-3.5 transition-transform duration-200"
                                             :class="expandedRows['{{ $device->id }}'] ? 'rotate-180' : ''"
                                             fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="m19.5 8.25-7.5 7.5-7.5-7.5" />
                                        </svg>
                                    </button>
                                </div>
                            </div>

                            {{-- Expandable Drawer with Technical Specs --}}
                            <div x-show="expandedRows['{{ $device->id }}']"
                                 x-collapse
                                 x-cloak
                                 class="mt-3 pt-3 border-t border-slate-100 bg-slate-50/70 rounded-xl p-3 text-xs grid grid-cols-2 sm:grid-cols-4 gap-3">
                                <div>
                                    <span class="block text-[10px] uppercase font-bold text-slate-400">Activated Date</span>
                                    <span class="font-medium text-slate-700">{{ $device->activated_date ? $device->activated_date->format('Y-m-d') : '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] uppercase font-bold text-slate-400">Sales Time</span>
                                    <span class="font-medium text-slate-700">{{ $device->sales_time ? $device->sales_time->format('Y-m-d') : '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] uppercase font-bold text-slate-400">ICCID</span>
                                    <span class="font-mono text-slate-700 text-[11px] truncate block" title="{{ $device->iccid }}">{{ $device->iccid ?: '-' }}</span>
                                </div>
                                <div>
                                    <span class="block text-[10px] uppercase font-bold text-slate-400">IMSI</span>
                                    <span class="font-mono text-slate-700 text-[11px] truncate block" title="{{ $device->imsi }}">{{ $device->imsi ?: '-' }}</span>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="px-6 py-12 text-center text-slate-400">
                            No GPS devices found.
                        </div>
                    @endforelse
                </div>
            </div>

            {{-- ===================== VIEW 2: FULL SHEET VIEW (11 COLS) ===================== --}}
            <div x-show="viewMode === 'full'" class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                    <thead class="bg-slate-50/90 text-slate-500 font-bold uppercase tracking-wider text-[10px] border-b border-slate-200">
                        <tr>
                            {{-- Sticky Left Column --}}
                            <th class="sticky left-0 z-20 bg-slate-50 px-4 py-3 min-w-[180px] shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)] border-r border-slate-100">
                                Device Name
                            </th>
                            <th class="px-4 py-3 min-w-[170px] whitespace-nowrap">IMEI</th>
                            <th class="px-3 py-3 min-w-[90px] whitespace-nowrap">Model</th>
                            <th class="px-3 py-3 min-w-[120px] whitespace-nowrap">Activated Date</th>
                            <th class="px-3 py-3 min-w-[120px] whitespace-nowrap">Sales Time</th>
                            <th class="px-3 py-3 min-w-[130px] whitespace-nowrap">SIM</th>
                            <th class="px-4 py-3 min-w-[210px] whitespace-nowrap">User Expiration Date</th>
                            <th class="px-4 py-3 min-w-[160px] whitespace-nowrap">Group</th>
                            <th class="px-4 py-3 min-w-[200px] whitespace-nowrap">ICCID</th>
                            <th class="px-4 py-3 min-w-[160px] whitespace-nowrap">IMSI</th>
                            <th class="px-4 py-3 min-w-[120px] text-right whitespace-nowrap">Mileage</th>
                            {{-- Sticky Right Column --}}
                            <th class="sticky right-0 z-20 bg-slate-50 px-4 py-3 min-w-[210px] text-right shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.06)] border-l border-slate-100">
                                Actions
                            </th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse($devices as $device)
                            @php
                                $badge = $device->expiration_badge;
                                $isExpiring = $badge['status'] === 'expiring_soon';
                                $isExpired = $badge['status'] === 'expired';
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors {{ $isExpiring ? 'bg-amber-50/20' : ($isExpired ? 'bg-red-50/20' : '') }}">
                                {{-- Sticky Device Name --}}
                                <td class="sticky left-0 z-10 {{ $isExpiring ? 'bg-amber-50/40' : ($isExpired ? 'bg-red-50/40' : 'bg-white') }} px-4 py-3.5 font-bold text-slate-900 shadow-[2px_0_5px_-2px_rgba(0,0,0,0.06)] border-r border-slate-100 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <div class="h-7 w-7 rounded-lg bg-blue-50 text-blue-700 flex items-center justify-center font-bold text-xs shrink-0">
                                            {{ strtoupper(substr($device->device_name, 0, 2)) }}
                                        </div>
                                        <div>
                                            <a href="{{ route('devices.show', $device) }}" class="hover:text-blue-600 hover:underline block font-bold">
                                                {{ $device->device_name }}
                                            </a>
                                            @if($device->vehicle)
                                                <a href="{{ route('vehicles.show', $device->vehicle) }}"
                                                   class="inline-flex items-center gap-1 text-[10px] font-bold text-emerald-700 hover:underline mt-0.5"
                                                   title="Linked Vehicle: {{ $device->vehicle->equipment_code }} (Plate: {{ $device->vehicle->plate_number ?: 'N/A' }})">
                                                    <span>🚗</span>
                                                    <span>{{ $device->vehicle->equipment_code }}</span>
                                                </a>
                                            @else
                                                <span class="text-[10px] text-slate-400 font-normal">Unpaired</span>
                                            @endif
                                        </div>
                                    </div>
                                </td>

                                {{-- IMEI --}}
                                <td class="px-4 py-3.5 font-mono text-slate-700 text-xs whitespace-nowrap">
                                    {{ $device->imei ?: '-' }}
                                </td>

                                {{-- Model --}}
                                <td class="px-3 py-3.5 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded bg-slate-100 text-slate-700 text-[10px] font-bold">
                                        {{ $device->model ?: '-' }}
                                    </span>
                                </td>

                                {{-- Activated Date --}}
                                <td class="px-3 py-3.5 text-slate-600 whitespace-nowrap">
                                    {{ $device->activated_date ? $device->activated_date->format('Y-m-d') : '-' }}
                                </td>

                                {{-- Sales Time --}}
                                <td class="px-3 py-3.5 text-slate-600 whitespace-nowrap">
                                    {{ $device->sales_time ? $device->sales_time->format('Y-m-d') : '-' }}
                                </td>

                                {{-- SIM --}}
                                <td class="px-3 py-3.5 font-mono text-slate-700 whitespace-nowrap font-medium">
                                    {{ $device->sim ?: '-' }}
                                </td>

                                {{-- User Expiration Date --}}
                                <td class="px-4 py-3.5 whitespace-nowrap">
                                    <div class="flex items-center gap-2">
                                        <span class="font-mono text-xs font-bold text-slate-800">
                                            {{ $device->expiration_date ? $device->expiration_date->format('Y-m-d') : ($device->raw_expiration ?: '-') }}
                                        </span>
                                        <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold border {{ $badge['bg'] }} {{ $badge['text'] }} {{ $badge['border'] }}">
                                            <span class="h-1.5 w-1.5 rounded-full {{ $badge['dot'] }} {{ $badge['pulse'] ? 'animate-ping' : '' }}"></span>
                                            {{ $badge['label'] }}
                                        </span>
                                    </div>
                                </td>

                                {{-- Group --}}
                                <td class="px-4 py-3.5 text-slate-700 whitespace-nowrap">
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md bg-slate-50 border border-slate-200 text-slate-600 text-[11px]">
                                        {{ $device->group_name ?: 'Default' }}
                                    </span>
                                </td>

                                {{-- ICCID --}}
                                <td class="px-4 py-3.5 font-mono text-slate-500 text-[11px] whitespace-nowrap" title="{{ $device->iccid }}">
                                    {{ $device->iccid ?: '-' }}
                                </td>

                                {{-- IMSI --}}
                                <td class="px-4 py-3.5 font-mono text-slate-500 text-[11px] whitespace-nowrap" title="{{ $device->imsi }}">
                                    {{ $device->imsi ?: '-' }}
                                </td>

                                {{-- Mileage --}}
                                <td class="px-4 py-3.5 text-right font-mono font-bold text-slate-800 whitespace-nowrap">
                                    {{ $device->mileage !== null ? number_format($device->mileage, 2).' km' : '-' }}
                                </td>

                                {{-- Sticky Actions with Text & Icons --}}
                                <td class="sticky right-0 z-10 {{ $isExpiring ? 'bg-amber-50/40' : ($isExpired ? 'bg-red-50/40' : 'bg-white') }} px-4 py-3.5 text-right whitespace-nowrap shadow-[-2px_0_5px_-2px_rgba(0,0,0,0.06)] border-l border-slate-100">
                                    <div class="flex items-center justify-end gap-1.5">
                                        <a href="{{ route('devices.show', $device) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-blue-50 px-2.5 py-1 text-xs font-semibold text-blue-700 hover:bg-blue-100 transition-colors"
                                           title="View Details">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                            <span>View</span>
                                        </a>
                                        <a href="{{ route('devices.edit', $device) }}"
                                           class="inline-flex items-center gap-1 rounded-lg bg-slate-100 px-2.5 py-1 text-xs font-semibold text-slate-700 hover:bg-slate-200 transition-colors"
                                           title="Edit Device">
                                            <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                            <span>Edit</span>
                                        </a>
                                        <button type="button"
                                                @click="confirmDelete('{{ $device->id }}', '{{ $device->device_name }}', '{{ route('devices.destroy', $device) }}')"
                                                class="inline-flex items-center gap-1 rounded-lg bg-red-50 px-2.5 py-1 text-xs font-semibold text-red-700 hover:bg-red-100 transition-colors cursor-pointer"
                                                title="Delete Device">
                                            <svg class="h-3.5 w-3.5 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                            </svg>
                                            <span>Delete</span>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="12" class="px-6 py-12 text-center text-slate-400">
                                    No GPS devices found.
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            {{-- Table Footer with Sync Status & Pagination --}}
            <div class="flex flex-col sm:flex-row sm:items-center justify-between px-6 py-3 border-t border-slate-100 bg-slate-50/50 gap-2">
                <p class="text-xs text-slate-400">Showing {{ $devices->count() }} of {{ $devices->total() }} devices</p>
                <div class="flex items-center gap-1.5 text-xs font-semibold text-emerald-600">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                    Live GPS Telemetry Sync
                </div>
            </div>

            @if($devices->hasPages())
                <div class="px-6 py-3 border-t border-slate-200 bg-white">
                    {{ $devices->links() }}
                </div>
            @endif
        </div>

        {{-- ===================== IMPORT MODAL ===================== --}}
        <div x-show="showImportModal"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
             @keydown.escape.window="showImportModal = false">
            <div class="w-full max-w-md bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden"
                 @click.outside="showImportModal = false">
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <div class="h-8 w-8 rounded-lg bg-blue-50 text-blue-600 flex items-center justify-center">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </div>
                        <h3 class="text-base font-bold text-slate-900">Import GPS Devices</h3>
                    </div>
                    <button @click="showImportModal = false" class="text-slate-400 hover:text-slate-600 text-lg cursor-pointer">&times;</button>
                </div>

                <form method="POST" action="{{ route('devices.importData') }}" enctype="multipart/form-data" class="p-6 space-y-4">
                    @csrf

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Upload File (.xlsx, .xls, .csv)
                        </label>
                        <input type="file" name="file" required accept=".xlsx,.xls,.csv,.txt"
                               class="block w-full text-xs text-slate-600 file:mr-3 file:py-2 file:px-3 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-blue-50 file:text-blue-700 hover:file:bg-blue-100 border border-slate-300 rounded-xl p-1 bg-slate-50 cursor-pointer">
                        <p class="text-[11px] text-slate-400 mt-1">
                            Compatible with Google Sheets & Tracksolid Pro exports.
                        </p>
                    </div>

                    <div>
                        <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">
                            Import Mode
                        </label>
                        <div class="space-y-2 text-xs">
                            <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-slate-200 bg-slate-50/60 cursor-pointer hover:bg-slate-50">
                                <input type="radio" name="mode" value="update" checked class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="font-bold text-slate-800">Update Existing & Add New (Recommended)</div>
                                    <div class="text-slate-500 text-[11px]">Matches by IMEI or Device Name and updates expiration, mileage, etc.</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-slate-200 bg-slate-50/60 cursor-pointer hover:bg-slate-50">
                                <input type="radio" name="mode" value="append" class="mt-0.5 text-blue-600 focus:ring-blue-500">
                                <div>
                                    <div class="font-bold text-slate-800">Append Only</div>
                                    <div class="text-slate-500 text-[11px]">Skips devices that are already registered.</div>
                                </div>
                            </label>

                            <label class="flex items-start gap-2.5 p-2.5 rounded-lg border border-red-200 bg-red-50/30 cursor-pointer hover:bg-red-50/50">
                                <input type="radio" name="mode" value="replace" class="mt-0.5 text-red-600 focus:ring-red-500">
                                <div>
                                    <div class="font-bold text-red-800">Replace All</div>
                                    <div class="text-red-600 text-[11px]">Wipes all existing devices and imports fresh list.</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <div class="pt-2 flex items-center justify-end gap-2 border-t border-slate-100">
                        <button type="button" @click="showImportModal = false"
                                class="px-3.5 py-2 text-xs font-semibold text-slate-600 hover:bg-slate-100 rounded-lg transition-colors cursor-pointer">
                            Cancel
                        </button>
                        <button type="submit"
                                class="px-4 py-2 text-xs font-bold text-white bg-blue-600 hover:bg-blue-700 rounded-lg shadow-sm transition-colors cursor-pointer">
                            Start Import
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ===================== DELETE CONFIRMATION MODAL ===================== --}}
        <div x-show="deleteId !== null"
             x-cloak
             class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 backdrop-blur-xs p-4"
             @keydown.escape.window="deleteId = null">
            <div class="w-full max-w-sm bg-white rounded-2xl shadow-xl border border-slate-200 overflow-hidden"
                 @click.outside="deleteId = null">
                <div class="p-5 text-center">
                    <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-red-100 text-red-600 mb-3">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </div>
                    <h3 class="text-base font-bold text-slate-900">Delete Device</h3>
                    <p class="text-xs text-slate-500 mt-1">
                        Are you sure you want to delete <strong class="text-slate-800" x-text="deleteName"></strong>? This action cannot be undone.
                    </p>
                </div>
                <div class="px-5 py-3 bg-slate-50 border-t border-slate-100 flex items-center justify-end gap-2">
                    <button type="button" @click="deleteId = null"
                            class="px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer">
                        Cancel
                    </button>
                    <form :action="deleteAction" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-3.5 py-1.5 text-xs font-bold text-white bg-red-600 hover:bg-red-700 rounded-lg shadow-xs transition-colors cursor-pointer">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
