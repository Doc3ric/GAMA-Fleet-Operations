<x-app-layout>
    @section('page-title', 'Fuel PO Checklist')
    @section('breadcrumb')
        <span class="text-slate-800 font-semibold">Fuel PO Checklist</span>
    @endsection

    <div class="space-y-6" x-data="{
        toggleLoading: {},
        selectedIds: [],
        allIds: {{ json_encode($records->pluck('id')->values()->all()) }},
        checkedIds: {{ json_encode($records->where('po_checked', true)->pluck('id')->values()->all()) }},
        uncheckedIds: {{ json_encode($records->where('po_checked', false)->pluck('id')->values()->all()) }},
        isBulkLoading: false,
        showDeleteModal: false,
        deleteId: null,
        deleteInfo: '',
        deleteActionUrl: '',
        showImportModal: false,
        isImportParsing: false,
        isImportSaving: false,
        importError: null,
        importPreview: null,
        importFile: null,

        openImportModal() {
            this.importError = null;
            this.importPreview = null;
            this.importFile = null;
            this.showImportModal = true;
        },

        async handleFileUpload(event) {
            const file = event.target.files[0];
            if (!file) return;
            this.importFile = file;
            this.importError = null;
            this.importPreview = null;
            this.isImportParsing = true;

            const formData = new FormData();
            formData.append('file', file);

            try {
                const response = await fetch('{{ route('fuel-po.import.preview') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json'
                    },
                    body: formData
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    this.importPreview = data;
                } else {
                    this.importError = data.message || 'Failed to process spreadsheet file.';
                }
            } catch (err) {
                this.importError = 'Network error while processing spreadsheet.';
            } finally {
                this.isImportParsing = false;
                event.target.value = '';
            }
        },

        async commitImport() {
            if (!this.importPreview || !this.importPreview.import_token) return;
            this.isImportSaving = true;
            this.importError = null;

            try {
                const response = await fetch('{{ route('fuel-po.import.confirm') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        import_token: this.importPreview.import_token,
                        extension: this.importPreview.extension,
                        vehicle_id: this.importPreview.vehicle_match.vehicle_id,
                        driver_name: this.importPreview.header.driver_name,
                        custom_equipment_code: this.importPreview.vehicle_match.equipment_code,
                        custom_plate_number: this.importPreview.vehicle_match.plate_number,
                        custom_average_consumption: this.importPreview.vehicle_match.avg_consumption
                    })
                });

                const data = await response.json();
                if (response.ok && data.success) {
                    window.location.reload();
                } else {
                    this.importError = data.message || 'Failed to commit imported itinerary records.';
                }
            } catch (err) {
                this.importError = 'Network error while committing import.';
            } finally {
                this.isImportSaving = false;
            }
        },

        confirmDelete(id, info, url) {
            this.deleteId = id;
            this.deleteInfo = info;
            this.deleteActionUrl = url;
            this.showDeleteModal = true;
        },

        get allSelected() {
            return this.allIds.length > 0 && this.allIds.every(id => this.selectedIds.includes(id));
        },

        toggleSelectAll() {
            if (this.allSelected) {
                this.selectedIds = [];
            } else {
                this.selectedIds = [...this.allIds];
            }
        },

        selectCheckedOnly() {
            this.selectedIds = [...this.checkedIds];
        },

        selectUncheckedOnly() {
            this.selectedIds = [...this.uncheckedIds];
        },

        get exportExcelUrl() {
            const url = new URL('{{ route('fuel-po.export-excel') }}', window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            if (this.selectedIds.length > 0) {
                params.set('ids', this.selectedIds.join(','));
            } else {
                params.delete('ids');
            }
            url.search = params.toString();
            return url.toString();
        },

        get exportPdfUrl() {
            const url = new URL('{{ route('fuel-po.export-pdf') }}', window.location.origin);
            const params = new URLSearchParams(window.location.search);
            params.delete('page');
            if (this.selectedIds.length > 0) {
                params.set('ids', this.selectedIds.join(','));
            } else {
                params.delete('ids');
            }
            url.search = params.toString();
            return url.toString();
        },

        async performBulkAction(action, targetIds = null) {
            const idsToProcess = targetIds || (this.selectedIds.length > 0 ? this.selectedIds : this.allIds);
            if (!idsToProcess || idsToProcess.length === 0) {
                alert('No records available to ' + action + '.');
                return;
            }

            const confirmMsg = action === 'check'
                ? 'Mark ' + idsToProcess.length + ' Fuel PO record(s) as CHECKED (☑)?'
                : 'Mark ' + idsToProcess.length + ' Fuel PO record(s) as UNCHECKED (☐)?';

            if (!confirm(confirmMsg)) return;

            this.isBulkLoading = true;
            try {
                const response = await fetch('{{ route('fuel-po.bulk-checklist') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids: idsToProcess,
                        action: action
                    })
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Bulk checklist action failed.');
                }
            } catch (err) {
                alert('Network error while processing bulk checklist.');
            } finally {
                this.isBulkLoading = false;
            }
        },

        async performBulkDelete() {
            if (this.selectedIds.length === 0) {
                alert('No records selected to delete.');
                return;
            }

            if (!confirm('Are you sure you want to permanently delete ' + this.selectedIds.length + ' Fuel PO record(s)? This action cannot be undone.')) {
                return;
            }

            this.isBulkLoading = true;
            try {
                const response = await fetch('{{ route('fuel-po.bulk-delete') }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        ids: this.selectedIds
                    })
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert(data.message || 'Bulk delete failed.');
                }
            } catch (err) {
                alert('Network error while processing bulk delete.');
            } finally {
                this.isBulkLoading = false;
            }
        },

        async toggleChecklist(url, id, currentChecked) {
            this.toggleLoading[id] = true;
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        'X-HTTP-Method-Override': 'PATCH',
                        'Accept': 'application/json',
                        'Content-Type': 'application/json'
                    }
                });
                const data = await response.json();
                if (data.success) {
                    window.location.reload();
                } else {
                    alert('Could not update checklist state.');
                }
            } catch (err) {
                // Fallback to normal form submit
                document.getElementById('form-toggle-' + id)?.submit();
            } finally {
                this.toggleLoading[id] = false;
            }
        }
    }">
        {{-- Flash Messages --}}
        @if (session('success'))
            <div class="rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-emerald-800 text-xs font-semibold flex items-center justify-between shadow-2xs">
                <div class="flex items-center gap-2">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                    </svg>
                    <span>{{ session('success') }}</span>
                </div>
            </div>
        @endif

        {{-- Header & Actions --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
            <div>
                <h1 class="text-xl font-extrabold text-slate-900 tracking-tight flex items-center gap-2.5">
                    <span class="flex h-9 w-9 items-center justify-center rounded-xl bg-amber-500 text-white shadow-xs">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                    </span>
                    Fuel Purchase Order (PO) Checklist
                </h1>
                <p class="text-xs text-slate-500 mt-1">
                    Process fuel purchase orders from vehicle itineraries. Checklists (☐ / ☑) persist independently for each itinerary.
                </p>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                {{-- Quick Check All / Uncheck All Buttons --}}
                <button type="button"
                        @click="performBulkAction('check', allIds)"
                        :disabled="isBulkLoading || allIds.length === 0"
                        title="Mark all records on this page as checked for PO"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 shadow-2xs transition cursor-pointer disabled:opacity-50">
                    <span class="font-bold text-sm leading-none">☑</span>
                    <span>Check All</span>
                </button>

                <button type="button"
                        @click="performBulkAction('uncheck', allIds)"
                        :disabled="isBulkLoading || allIds.length === 0"
                        title="Mark all records on this page as unchecked"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-slate-300 bg-white px-3.5 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 shadow-2xs transition cursor-pointer disabled:opacity-50">
                    <span class="text-slate-400 font-bold text-sm leading-none">☐</span>
                    <span>Uncheck All</span>
                </button>

                <a :href="exportExcelUrl"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-300 bg-emerald-50 px-3.5 py-2 text-xs font-bold text-emerald-800 hover:bg-emerald-100 shadow-2xs transition"
                   :title="selectedIds.length > 0 ? 'Export ' + selectedIds.length + ' selected records to Excel' : 'Export current filtered records to Excel'">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span x-text="selectedIds.length > 0 ? 'Export Excel (' + selectedIds.length + ' Selected)' : 'Export Excel'">Export Excel</span>
                </a>

                <a :href="exportPdfUrl" target="_blank"
                   class="inline-flex items-center gap-1.5 rounded-xl border border-red-300 bg-red-50 px-3.5 py-2 text-xs font-bold text-red-800 hover:bg-red-100 shadow-2xs transition"
                   :title="selectedIds.length > 0 ? 'Download PDF for ' + selectedIds.length + ' selected records' : 'Download PDF for current filtered records'">
                    <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span x-text="selectedIds.length > 0 ? 'Download PDF (' + selectedIds.length + ' Selected)' : 'Download PDF'">Download PDF</span>
                </a>

                {{-- Import Itinerary Button --}}
                <button type="button"
                        @click="openImportModal()"
                        title="Import driver Weekly Itinerary Report (.xlsx, .xlsb, .xls)"
                        class="inline-flex items-center gap-1.5 rounded-xl border border-indigo-300 bg-indigo-50 px-3.5 py-2 text-xs font-bold text-indigo-800 hover:bg-indigo-100 shadow-2xs transition cursor-pointer">
                    <svg class="h-4 w-4 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                    </svg>
                    <span>Import Itinerary</span>
                </button>

                <a href="{{ route('fuel-po.create') }}"
                   class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-xs transition">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    Create Fuel PO
                </a>
            </div>
        </div>

        {{-- Metric KPI Cards --}}
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
            <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-slate-400">Total Records</span>
                <span class="mt-1 block text-2xl font-extrabold text-slate-900">{{ number_format($metrics['total_count']) }}</span>
                <span class="text-[10px] text-slate-400">Recorded Fuel POs</span>
            </div>

            <div class="rounded-2xl border border-emerald-200 bg-emerald-50/40 p-4 shadow-2xs">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-emerald-700 flex items-center gap-1">
                    <span>☑ Checked for PO</span>
                </span>
                <span class="mt-1 block text-2xl font-extrabold text-emerald-800">{{ number_format($metrics['checked_count']) }}</span>
                <span class="text-[10px] text-emerald-600 font-medium">Processed POs</span>
            </div>

            <div class="rounded-2xl border border-amber-200 bg-amber-50/40 p-4 shadow-2xs">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-amber-700 flex items-center gap-1">
                    <span>☐ Unchecked (Pending)</span>
                </span>
                <span class="mt-1 block text-2xl font-extrabold text-amber-800">{{ number_format($metrics['unchecked_count']) }}</span>
                <span class="text-[10px] text-amber-600 font-medium">Awaiting PO Check</span>
            </div>

            <div class="rounded-2xl border border-blue-200 bg-blue-50/40 p-4 shadow-2xs">
                <span class="block text-[11px] font-bold uppercase tracking-wider text-blue-700">Total PO Fuel Liters</span>
                <span class="mt-1 block text-2xl font-mono font-extrabold text-blue-900">
                    {{ (float) $metrics['total_fuel_liters'] == round($metrics['total_fuel_liters']) ? number_format($metrics['total_fuel_liters'], 0) : number_format($metrics['total_fuel_liters'], 2) }} <span class="text-xs font-sans font-normal text-blue-600">L</span>
                </span>
                <span class="text-[10px] text-blue-500 font-medium font-mono">{{ number_format($metrics['total_distance'], 2) }} km road total</span>
            </div>
        </div>

        {{-- Filters Card --}}
        <div class="rounded-2xl border border-slate-200 bg-white p-4 shadow-2xs">
            <form method="GET" action="{{ route('fuel-po.index') }}" class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-5 gap-3">
                <div class="md:col-span-2">
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Search Details</label>
                    <input type="text" name="search" value="{{ request('search') }}"
                           placeholder="Search EQPT, Plate, Driver, User, Destination..."
                           class="w-full rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Vehicle</label>
                    <select name="vehicle_id" class="w-full rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">All Vehicles</option>
                        @foreach($vehicles as $v)
                            <option value="{{ $v->id }}" {{ request('vehicle_id') == $v->id ? 'selected' : '' }}>
                                {{ $v->equipment_code }} ({{ $v->plate_number ?: 'No Plate' }})
                            </option>
                        @endforeach
                    </select>
                </div>

                <div>
                    <label class="block text-[11px] font-bold uppercase text-slate-500 mb-1">Checklist State</label>
                    <select name="po_status" class="w-full rounded-xl border border-slate-200 px-3 py-1.5 text-xs text-slate-800 focus:border-blue-500 focus:ring-1 focus:ring-blue-500 outline-none">
                        <option value="">All Statuses</option>
                        <option value="unchecked" {{ request('po_status') === 'unchecked' ? 'selected' : '' }}>☐ UNCHECKED Only</option>
                        <option value="checked" {{ request('po_status') === 'checked' ? 'selected' : '' }}>☑ CHECKED Only</option>
                    </select>
                </div>

                <div class="flex items-end gap-2">
                    <button type="submit"
                            class="flex-1 rounded-xl bg-slate-900 px-3 py-1.5 text-xs font-bold text-white hover:bg-slate-800 transition">
                        Filter
                    </button>
                    @if(request()->hasAny(['search', 'vehicle_id', 'po_status', 'date_from', 'date_to']))
                        <a href="{{ route('fuel-po.index') }}"
                           class="rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-600 hover:bg-slate-50 transition">
                            Reset
                        </a>
                    @endif
                </div>
            </form>
        </div>

        {{-- Floating/Sticky Selected Action Banner --}}
        <div x-show="selectedIds.length > 0" x-cloak
             class="rounded-2xl border border-blue-200 bg-blue-50/95 p-4 flex flex-wrap items-center justify-between gap-3 text-xs shadow-sm transition">
            <div class="flex items-center gap-2.5 text-blue-900 font-bold">
                <span class="flex h-6 w-6 items-center justify-center rounded-lg bg-blue-600 text-white text-xs font-black shadow-2xs" x-text="selectedIds.length"></span>
                <span class="text-sm font-extrabold">Fuel PO record(s) selected</span>
            </div>

            <div class="flex items-center gap-2 flex-wrap">
                <button type="button"
                        @click="performBulkAction('check', selectedIds)"
                        :disabled="isBulkLoading"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs shadow-xs transition cursor-pointer">
                    <span>☑ Mark Checked</span>
                </button>
                <button type="button"
                        @click="performBulkAction('uncheck', selectedIds)"
                        :disabled="isBulkLoading"
                        class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-slate-300 bg-white hover:bg-slate-50 text-slate-700 font-bold text-xs shadow-xs transition cursor-pointer">
                    <span>☐ Mark Unchecked</span>
                </button>

                <a :href="exportExcelUrl"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-emerald-300 bg-white hover:bg-emerald-50 text-emerald-800 font-bold text-xs shadow-2xs transition">
                    <svg class="h-4 w-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                    </svg>
                    <span>Export Excel (<span x-text="selectedIds.length"></span>)</span>
                </a>

                <a :href="exportPdfUrl" target="_blank"
                   class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-red-300 bg-white hover:bg-red-50 text-red-800 font-bold text-xs shadow-2xs transition">
                    <svg class="h-4 w-4 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                    </svg>
                    <span>Download PDF (<span x-text="selectedIds.length"></span>)</span>
                </a>

                @can('delete', \App\Models\AdvancedItinerary::class)
                    <button type="button"
                            @click="performBulkDelete()"
                            :disabled="isBulkLoading"
                            class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl border border-rose-300 bg-white hover:bg-rose-50 text-rose-700 font-bold text-xs shadow-2xs transition cursor-pointer">
                        <svg class="h-4 w-4 text-rose-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                        <span>Delete Selected</span>
                    </button>
                @endcan

                <button type="button"
                        @click="selectedIds = []"
                        class="text-xs text-slate-500 hover:text-slate-800 hover:underline px-2 py-1">
                    Clear Selection
                </button>
            </div>
        </div>

        {{-- Quick Selection Helper & Stats Bar --}}
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 text-xs text-slate-500 px-1">
            <div class="flex items-center gap-1.5 flex-wrap">
                <span class="font-bold text-slate-700">Quick Select:</span>
                <button type="button" @click="toggleSelectAll()" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-[11px] font-semibold text-slate-700 shadow-2xs transition cursor-pointer">
                    All (<span x-text="allIds.length"></span>)
                </button>
                <button type="button" @click="selectCheckedOnly()" class="px-2.5 py-1 rounded-lg border border-emerald-300 bg-emerald-50 hover:bg-emerald-100 text-[11px] font-bold text-emerald-800 shadow-2xs transition cursor-pointer">
                    ☑ Checked (<span x-text="checkedIds.length"></span>)
                </button>
                <button type="button" @click="selectUncheckedOnly()" class="px-2.5 py-1 rounded-lg border border-amber-300 bg-amber-50 hover:bg-amber-100 text-[11px] font-bold text-amber-800 shadow-2xs transition cursor-pointer">
                    ☐ Unchecked (<span x-text="uncheckedIds.length"></span>)
                </button>
                <button type="button" x-show="selectedIds.length > 0" @click="selectedIds = []" class="px-2.5 py-1 rounded-lg border border-slate-200 bg-white hover:bg-slate-100 text-[11px] text-slate-600 shadow-2xs transition cursor-pointer">
                    Clear Selection
                </button>
            </div>
            <div class="text-[11px] text-slate-400">
                Showing {{ $records->firstItem() ?? 0 }} to {{ $records->lastItem() ?? 0 }} of {{ $records->total() }} records
            </div>
        </div>

        {{-- Fuel PO Checklist Table --}}
        <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-2xs">
            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200 text-left text-xs">
                    <thead class="bg-slate-50 font-bold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-3 py-3 w-10 text-center">
                                <input type="checkbox"
                                       :checked="allSelected"
                                       @change="toggleSelectAll()"
                                       title="Select all on this page"
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                            </th>
                            <th class="px-3 py-3">EQPT</th>
                            <th class="px-3 py-3">Plate</th>
                            <th class="px-3 py-3">Driver</th>
                            <th class="px-3 py-3">User</th>
                            <th class="px-3 py-3">Destination</th>
                            <th class="px-3 py-3 text-right">Distance</th>
                            <th class="px-3 py-3 text-right">Avg. Consumption</th>
                            <th class="px-3 py-3 text-right">Liter for PO</th>
                            <th class="px-4 py-3 text-center">
                                <div class="flex items-center justify-center gap-2">
                                    <span>Checklist</span>
                                    <div class="inline-flex rounded-lg shadow-2xs border border-slate-200 overflow-hidden bg-white">
                                        <button type="button"
                                                @click="performBulkAction('check', allIds)"
                                                :disabled="isBulkLoading || allIds.length === 0"
                                                title="Check All records on this page"
                                                class="px-2 py-1 text-[10px] font-bold bg-emerald-600 hover:bg-emerald-700 text-white transition cursor-pointer disabled:opacity-50">
                                            ☑ All
                                        </button>
                                        <button type="button"
                                                @click="performBulkAction('uncheck', allIds)"
                                                :disabled="isBulkLoading || allIds.length === 0"
                                                title="Uncheck All records on this page"
                                                class="px-2 py-1 text-[10px] font-bold bg-white hover:bg-slate-100 text-slate-600 border-l border-slate-200 transition cursor-pointer disabled:opacity-50">
                                            ☐ None
                                        </button>
                                    </div>
                                </div>
                            </th>
                            <th class="px-4 py-3 text-right">Actions</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 text-slate-700">
                        @forelse($records as $po)
                            @php
                                $v = $po->vehicle;
                                $avgConsumption = $v?->average_consumption;
                                $fuelLiters = $po->fuel_liters;
                            @endphp
                            <tr class="hover:bg-slate-50/70 transition-colors {{ $po->po_checked ? 'bg-emerald-50/20' : '' }}">
                                {{-- 0. Row Selection Checkbox --}}
                                <td class="px-3 py-3 text-center">
                                    <input type="checkbox"
                                           :value="{{ $po->id }}"
                                           x-model.number="selectedIds"
                                           class="rounded border-slate-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
                                </td>

                                {{-- 1. EQPT --}}
                                <td class="px-3 py-3 font-mono font-bold text-slate-900">
                                    <a href="{{ route('fuel-po.show', $po) }}" class="hover:text-blue-600 hover:underline">
                                        {{ $v?->equipment_code ?? '—' }}
                                    </a>
                                </td>

                                {{-- 2. Plate --}}
                                <td class="px-3 py-3 font-mono text-slate-800">
                                    {{ $v?->plate_number ?? '—' }}
                                </td>

                                {{-- 3. Driver --}}
                                <td class="px-3 py-3 font-medium text-slate-900">
                                    {{ $po->driver_name }}
                                </td>

                                {{-- 4. User --}}
                                <td class="px-3 py-3 text-slate-600">
                                    {{ $v?->user ?? '—' }}
                                </td>

                                {{-- 5. Destination --}}
                                <td class="px-3 py-3">
                                    <span class="font-medium text-slate-900">{{ $po->destination_name }}</span>
                                    @if($po->title)
                                        <span class="block text-[10px] text-slate-400 truncate max-w-xs">{{ $po->title }}</span>
                                    @endif
                                </td>

                                {{-- 6. Distance --}}
                                <td class="px-3 py-3 text-right font-mono font-semibold text-slate-900">
                                    {{ number_format($po->total_distance, 2) }} KM
                                </td>

                                {{-- 7. Avg. Consumption --}}
                                <td class="px-3 py-3 text-right font-mono text-slate-700">
                                    @if($avgConsumption)
                                        <span class="font-semibold">{{ number_format($avgConsumption, 2) }}</span> <span class="text-[10px] text-slate-400">KM/L</span>
                                    @else
                                        <span class="text-slate-400">—</span>
                                    @endif
                                </td>

                                {{-- 8. Liter for PO --}}
                                <td class="px-3 py-3 text-right">
                                    @if($fuelLiters !== null)
                                        <span class="font-mono font-bold text-blue-700 text-sm">{{ (float) $fuelLiters == round($fuelLiters) ? number_format($fuelLiters, 0) : number_format($fuelLiters, 2) }}</span>
                                        <span class="text-[11px] font-semibold text-slate-500">L</span>
                                    @else
                                        <span class="text-slate-400 text-xs italic">N/A</span>
                                    @endif
                                </td>

                                {{-- 9. Checklist (Interactive Toggle: ☐ → ☑) --}}
                                <td class="px-4 py-3 text-center">
                                    <form id="form-toggle-{{ $po->id }}" method="POST" action="{{ route('fuel-po.toggle-checklist', $po) }}" class="inline-block">
                                        @csrf
                                        @method('PATCH')
                                        <button type="button"
                                                @click="toggleChecklist('{{ route('fuel-po.toggle-checklist', $po) }}', {{ $po->id }}, {{ $po->po_checked ? 'true' : 'false' }})"
                                                class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg text-xs font-bold transition cursor-pointer border shadow-2xs {{ $po->po_checked ? 'bg-emerald-50 border-emerald-300 text-emerald-800 hover:bg-emerald-100' : 'bg-white border-slate-300 text-slate-600 hover:border-blue-400 hover:text-blue-600' }}"
                                                title="{{ $po->po_checked ? 'Checked by ' . ($po->poChecker?->name ?? 'User') . ' at ' . ($po->po_checked_at?->format('M d, Y h:i A') ?? '') : 'Click to mark as checked for Fuel PO' }}">
                                            <span class="font-bold text-sm leading-none {{ $po->po_checked ? 'text-emerald-600' : 'text-slate-400' }}">
                                                {!! $po->po_checked ? '&#9745;' : '&#9744;' !!}
                                            </span>
                                            <span>{{ $po->po_checked ? 'CHECKED' : 'UNCHECKED' }}</span>
                                        </button>
                                    </form>
                                </td>

                                {{-- 10. Actions --}}
                                <td class="px-4 py-3 text-right">
                                    <div class="flex items-center justify-end gap-1">
                                        <a href="{{ route('fuel-po.pdf', $po) }}" target="_blank"
                                           class="rounded-lg p-1.5 text-red-600 hover:bg-red-50 transition"
                                           title="Download PDF (Will NOT check checklist)">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('fuel-po.show', $po) }}"
                                           class="rounded-lg p-1.5 text-slate-600 hover:bg-slate-100 transition"
                                           title="View Details">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                                            </svg>
                                        </a>
                                        <a href="{{ route('fuel-po.edit', $po) }}"
                                           class="rounded-lg p-1.5 text-blue-600 hover:bg-blue-50 transition"
                                           title="Edit Fuel PO">
                                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                            </svg>
                                        </a>
                                        @can('delete', $po)
                                            <button type="button"
                                                    @click="confirmDelete({{ $po->id }}, 'Fuel PO #{{ $po->id }} &bull; {{ addslashes($v?->equipment_code ?? 'Vehicle') }} ({{ addslashes($po->destination_name) }})', '{{ route('fuel-po.destroy', $po) }}')"
                                                    class="rounded-lg p-1.5 text-rose-600 hover:bg-rose-50 transition cursor-pointer"
                                                    title="Delete Fuel PO #{{ $po->id }}">
                                                <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                            </button>
                                        @endcan
                                    </div>
                                </td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="11" class="px-4 py-12 text-center text-slate-500">
                                    <div class="max-w-sm mx-auto space-y-2">
                                        <div class="h-12 w-12 rounded-full bg-slate-100 flex items-center justify-center mx-auto text-slate-400">
                                            <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h3.75M9 15h3.75M9 18h3.75m3 .75H18a2.25 2.25 0 0 0 2.25-2.25V6.108c0-1.135-.845-2.098-1.976-2.192a48.424 48.424 0 0 0-1.123-.08m-5.801 0c-.065.21-.1.433-.1.664 0 .414.336.75.75.75h4.5a.75.75 0 0 0 .75-.75 2.25 2.25 0 0 0-.1-.664m-5.8 0A2.251 2.251 0 0 1 13.5 2.25H15c1.012 0 1.867.668 2.15 1.586m-5.8 0c-.376.023-.75.05-1.124.08C9.095 4.01 8.25 4.973 8.25 6.108V8.25m0 0H4.875c-.621 0-1.125.504-1.125 1.125v11.25c0 .621.504 1.125 1.125 1.125h9.75c.621 0 1.125-.504 1.125-1.125V9.375c0-.621-.504-1.125-1.125-1.125H8.25ZM6.75 12h.008v.008H6.75V12Zm0 3h.008v.008H6.75V15Zm0 3h.008v.008H6.75V18Z" />
                                            </svg>
                                        </div>
                                        <div class="font-bold text-slate-800 text-sm">No Fuel PO records found</div>
                                        <p class="text-xs text-slate-400">Create an itinerary with vehicle assignment to begin generating Fuel PO checklists.</p>
                                        <div class="pt-2">
                                            <a href="{{ route('fuel-po.create') }}"
                                               class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-xs font-bold text-white hover:bg-blue-700 shadow-xs transition">
                                                Create First Fuel PO
                                            </a>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if($records->hasPages())
                <div class="border-t border-slate-200 px-4 py-3 bg-slate-50/50">
                    {{ $records->links() }}
                </div>
            @endif
        </div>

        {{-- Delete Confirmation Modal --}}
        <div x-show="showDeleteModal"
             x-cloak
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             @keydown.escape.window="showDeleteModal = false"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="fixed inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-2xl shadow-xl p-6 w-full max-w-md border border-slate-200 z-10 space-y-4" @click.stop>
                <div class="flex items-start gap-3.5">
                    <div class="h-10 w-10 rounded-xl bg-rose-100 border border-rose-200 flex items-center justify-center shrink-0 text-rose-600">
                        <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                        </svg>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h3 class="text-sm font-bold text-slate-900">Delete Fuel Purchase Order (PO)</h3>
                        <p class="text-xs font-semibold text-slate-600 mt-1 truncate" x-html="deleteInfo"></p>
                    </div>
                    <button type="button" @click="showDeleteModal = false" class="text-slate-400 hover:text-slate-600 p-1 rounded-lg">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                        </svg>
                    </button>
                </div>

                <div class="rounded-xl bg-rose-50/70 border border-rose-100 p-3 text-xs text-rose-800 space-y-1">
                    <p class="font-bold">Are you sure you want to delete this record?</p>
                    <p class="text-[11px] text-rose-700">This will permanently delete this Fuel PO, its destinations, distance breakdown, and calculated liters. This action cannot be undone.</p>
                </div>

                <div class="flex items-center justify-end gap-2.5 pt-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition cursor-pointer">
                        Cancel
                    </button>
                    <form method="POST" :action="deleteActionUrl" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="inline-flex items-center gap-1.5 rounded-xl bg-rose-600 px-4 py-2 text-xs font-bold text-white hover:bg-rose-700 shadow-sm transition cursor-pointer">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                            </svg>
                            <span>Delete Record</span>
                        </button>
                    </form>
                </div>
            </div>
        </div>
        {{-- IMPORT WEEKLY ITINERARY REPORT MODAL --}}
        <div x-show="showImportModal"
             x-cloak
             @keydown.escape.window="if (!isImportSaving && !isImportParsing) showImportModal = false"
             class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6"
             style="display: none;">
            <div class="fixed inset-0 bg-slate-900/50 backdrop-blur-2xs transition-opacity"
                 @click="if (!isImportSaving && !isImportParsing) showImportModal = false"></div>

            <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-3xl border border-slate-200 z-10 overflow-hidden flex flex-col max-h-[90vh]"
                 @click.stop>
                {{-- Modal Header --}}
                <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between bg-slate-50/60">
                    <div class="flex items-center gap-3">
                        <span class="flex h-10 w-10 items-center justify-center rounded-2xl bg-indigo-600 text-white shadow-xs">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5m-13.5-9L12 3m0 0 4.5 4.5M12 3v13.5" />
                            </svg>
                        </span>
                        <div>
                            <h3 class="text-sm font-bold text-slate-900">Import Weekly Itinerary Report</h3>
                            <p class="text-xs text-slate-500">Auto-generate Fuel PO checklists from driver's Excel template (.xlsx, .xlsb, .xls)</p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('fuel-po.import.template') }}"
                           class="inline-flex items-center gap-1.5 rounded-xl border border-slate-200 bg-white px-3 py-1.5 text-xs font-semibold text-slate-700 hover:bg-slate-50 transition shadow-2xs">
                            <svg class="h-3.5 w-3.5 text-indigo-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
                            </svg>
                            <span>Download Template (.xlsx)</span>
                        </a>
                        <button type="button"
                                @click="if (!isImportSaving && !isImportParsing) showImportModal = false"
                                class="text-slate-400 hover:text-slate-600 p-1.5 rounded-xl hover:bg-slate-100 transition">
                            <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>

                {{-- Modal Body (Scrollable) --}}
                <div class="p-6 overflow-y-auto space-y-5">
                    {{-- Error Notice --}}
                    <div x-show="importError" x-cloak
                         class="rounded-2xl border border-rose-200 bg-rose-50 p-4 text-xs text-rose-800 flex items-start gap-3 shadow-2xs">
                        <svg class="h-5 w-5 text-rose-600 shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                        </svg>
                        <div class="flex-1">
                            <h4 class="font-bold text-rose-900">Upload Issue</h4>
                            <p class="mt-0.5" x-text="importError"></p>
                        </div>
                    </div>

                    {{-- Step 1: File Dropzone (Visible if no preview loaded) --}}
                    <div x-show="!importPreview" class="space-y-4">
                        <label class="relative flex flex-col items-center justify-center rounded-3xl border-2 border-dashed border-indigo-200 hover:border-indigo-400 bg-indigo-50/30 hover:bg-indigo-50/60 p-8 text-center transition cursor-pointer group">
                            <input type="file"
                                   accept=".xlsx,.xlsb,.xls"
                                   @change="handleFileUpload($event)"
                                   :disabled="isImportParsing"
                                   class="sr-only">

                            <div x-show="!isImportParsing" class="space-y-3">
                                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-100 text-indigo-600 group-hover:scale-105 transition">
                                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m6.75 12-3-3m0 0-3 3m3-3v6m-1.5-15H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                                    </svg>
                                </div>
                                <div>
                                    <span class="text-sm font-bold text-slate-900 block">Click to upload or drag & drop</span>
                                    <span class="text-xs text-slate-500 mt-0.5 block">Supports Weekly Itinerary Reports in <strong>.xlsx</strong>, <strong>.xlsb</strong>, and <strong>.xls</strong> up to 20MB</span>
                                </div>
                            </div>

                            <div x-show="isImportParsing" x-cloak class="space-y-3">
                                <svg class="animate-spin h-8 w-8 text-indigo-600 mx-auto" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <p class="text-xs font-bold text-indigo-900">Analyzing workbook structure, matching vehicle &amp; computing fuel liters...</p>
                            </div>
                        </label>
                    </div>

                    {{-- Step 2: Interactive Preview Card (Visible when file is parsed) --}}
                    <div x-show="importPreview" x-cloak class="space-y-5">
                        {{-- Top Match & Status Ribbon --}}
                        <div class="rounded-2xl border p-4 bg-gradient-to-br transition"
                             :class="importPreview?.vehicle_match?.is_matched ? 'border-emerald-200 from-emerald-50/50 via-white to-emerald-50/20' : 'border-amber-200 from-amber-50/50 via-white to-amber-50/20'">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <template x-if="importPreview?.vehicle_match?.is_matched">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-emerald-100 text-emerald-800">
                                                ✓ Vehicle Matched
                                            </span>
                                        </template>
                                        <template x-if="!importPreview?.vehicle_match?.is_matched">
                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-[10px] font-bold bg-amber-100 text-amber-800">
                                                ⚠ Custom / Unregistered Vehicle
                                            </span>
                                        </template>
                                        <span class="text-xs text-slate-500 font-mono" x-text="importPreview?.file_name"></span>
                                    </div>
                                    <h4 class="text-sm font-bold text-slate-900 mt-1">
                                        <span x-text="importPreview?.vehicle_match?.equipment_code || 'No EQT Code'"></span>
                                        <span class="text-slate-400 font-normal" x-text="'• ' + (importPreview?.vehicle_match?.plate_number || 'No Plate')"></span>
                                        <span class="text-slate-400 font-normal" x-show="importPreview?.vehicle_match?.model" x-text="'(' + importPreview?.vehicle_match?.model + ')'"></span>
                                    </h4>
                                </div>

                                <div class="flex items-center gap-4 text-xs">
                                    <div class="text-right">
                                        <span class="block text-[10px] uppercase font-bold text-slate-400">Driver</span>
                                        <input type="text"
                                               x-model="importPreview.header.driver_name"
                                               placeholder="Driver name"
                                               class="text-xs font-semibold text-slate-900 border border-slate-300 rounded-lg px-2 py-0.5 text-right focus:border-indigo-500 outline-none">
                                    </div>
                                    <div class="text-right">
                                        <span class="block text-[10px] uppercase font-bold text-slate-400">Avg. Consump</span>
                                        <span class="font-mono font-bold text-blue-700 block mt-0.5"
                                              x-text="importPreview?.vehicle_match?.avg_consumption ? (importPreview.vehicle_match.avg_consumption + ' KM/L') : 'Not set'"></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        {{-- KPI Cards --}}
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-xs">
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Week Period</span>
                                <span class="font-semibold text-slate-800 block mt-0.5 truncate" x-text="importPreview?.header?.week_label || '—'"></span>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">PO Records (Days)</span>
                                <span class="font-extrabold text-slate-900 block mt-0.5 text-base" x-text="(importPreview?.summary?.total_dates || 0) + ' PO Record(s)'"></span>
                            </div>
                            <div class="p-3 bg-slate-50 rounded-xl border border-slate-200">
                                <span class="block text-[10px] uppercase font-bold text-slate-400">Total Distance</span>
                                <span class="font-mono font-extrabold text-slate-900 block mt-0.5 text-base" x-text="(importPreview?.summary?.total_distance || 0) + ' KM'"></span>
                            </div>
                            <div class="p-3 bg-indigo-50 rounded-xl border border-indigo-200">
                                <span class="block text-[10px] uppercase font-bold text-indigo-700">Total Liter for PO</span>
                                <span class="font-mono font-extrabold text-indigo-900 block mt-0.5 text-base" x-text="importPreview?.summary?.overall_fuel_liters ? (importPreview.summary.overall_fuel_liters + ' L') : '—'"></span>
                            </div>
                        </div>

                        {{-- Date-by-Date PO Breakdown --}}
                        <div class="space-y-3">
                            <h4 class="text-xs font-bold text-slate-800 uppercase tracking-wider flex items-center justify-between">
                                <span>PO Records to be Created (<span x-text="importPreview?.date_groups?.length || 0"></span>)</span>
                                <span class="text-[11px] text-slate-400 font-normal lowercase">1 Fuel PO per date</span>
                            </h4>

                            <div class="divide-y divide-slate-100 rounded-2xl border border-slate-200 bg-white overflow-hidden shadow-2xs">
                                <template x-for="(group, gIdx) in importPreview?.date_groups" :key="gIdx">
                                    <div class="p-4 hover:bg-slate-50/50 transition">
                                        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2">
                                            <div>
                                                <div class="flex items-center gap-2">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-bold bg-blue-100 text-blue-800 font-mono" x-text="group.date"></span>
                                                    <h5 class="text-xs font-bold text-slate-900" x-text="group.formatted_date"></h5>
                                                </div>
                                                <p class="text-[11px] text-slate-500 mt-1">
                                                    <span class="font-medium text-slate-700" x-text="group.legs.length + ' stop(s): '"></span>
                                                    <span x-text="group.legs.map(l => l.destination).join(' &rarr; ')"></span>
                                                </p>
                                            </div>

                                            <div class="flex items-center gap-4 text-xs font-mono shrink-0">
                                                <div>
                                                    <span class="block text-[9px] uppercase font-bold text-slate-400 font-sans">Distance</span>
                                                    <span class="font-bold text-slate-800" x-text="group.total_distance + ' KM'"></span>
                                                </div>
                                                <div>
                                                    <span class="block text-[9px] uppercase font-bold text-indigo-700 font-sans">Liter for PO</span>
                                                    <span class="font-extrabold text-indigo-900" x-text="group.fuel_liters_required ? (group.fuel_liters_required + ' L') : '—'"></span>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Modal Footer --}}
                <div class="px-6 py-4 bg-slate-50 border-t border-slate-200 flex items-center justify-between">
                    <div>
                        <button type="button"
                                x-show="importPreview"
                                @click="importPreview = null; importFile = null;"
                                class="text-xs text-blue-600 hover:text-blue-800 font-semibold underline cursor-pointer">
                            &larr; Choose different file
                        </button>
                    </div>

                    <div class="flex items-center gap-3">
                        <button type="button"
                                @click="showImportModal = false"
                                :disabled="isImportSaving"
                                class="rounded-xl border border-slate-300 bg-white px-4 py-2 text-xs font-semibold text-slate-700 hover:bg-slate-100 transition cursor-pointer disabled:opacity-50">
                            Cancel
                        </button>

                        <button type="button"
                                x-show="importPreview"
                                @click="commitImport()"
                                :disabled="isImportSaving"
                                style="background-color: #16a34a !important; color: #ffffff !important;"
                                class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-6 py-2.5 text-xs font-black text-white hover:bg-emerald-700 shadow-md transition cursor-pointer disabled:opacity-50">
                            <span x-show="!isImportSaving" class="flex items-center gap-2">
                                <svg class="h-4 w-4 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                <span>SAVE TO PO CHECKLIST (<span x-text="importPreview?.summary?.total_dates || 1"></span> Record)</span>
                            </span>
                            <span x-show="isImportSaving" x-cloak class="flex items-center gap-2">
                                <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Saving to Checklist...</span>
                            </span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-app-layout>
