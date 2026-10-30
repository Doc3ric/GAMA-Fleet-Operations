<div class="space-y-4"
     x-data="tripHistoryReplay({
        initialTrack: @js($trackData),
        initialPoints: @js($initialPoints ?? []),
        initialStops: @js($stops),
        initialPointsKey: @js($pointsCacheKey ?? '')
     })"
>
    @push('styles')
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
        <style>
            #trip-replay-map {
                height: 600px !important;
                min-height: 600px !important;
                width: 100% !important;
                border-radius: 0.75rem;
                z-index: 10;
            }
            .leaflet-popup-content-wrapper {
                border-radius: 0.75rem;
                padding: 0;
                overflow: hidden;
                box-shadow: 0 20px 25px -5px rgb(0 0 0 / 0.15), 0 8px 10px -6px rgb(0 0 0 / 0.1);
                border: 1px solid #e2e8f0;
            }
            .leaflet-popup-content {
                margin: 0;
                line-height: 1.4;
            }
            .custom-replay-pin {
                background: transparent;
                border: none;
            }
        </style>
    @endpush

    {{-- Header & Target Filter Bar --}}
    <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-4 lg:p-5">
        <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 dark:text-white tracking-tight">Trip History & Route Replay</h2>
                    <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-xs font-bold bg-blue-50 text-blue-700 border border-blue-200 dark:bg-blue-950/60 dark:text-blue-300 dark:border-blue-800">
                        <svg class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/></svg>
                        TrackSolid Route Telemetry
                    </span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                    Select a vehicle or tracker to replay GPS breadcrumbs, inspect engine status transitions, and evaluate planned route adherence.
                </p>
            </div>

            {{-- Quick Presets --}}
            <div class="flex flex-wrap items-center gap-1.5 bg-slate-100 dark:bg-slate-800/80 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                <button type="button"
                        wire:click="applyPreset('today')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all {{ $preset === 'today' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Today
                </button>
                <button type="button"
                        wire:click="applyPreset('yesterday')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all {{ $preset === 'yesterday' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Yesterday
                </button>
                <button type="button"
                        wire:click="applyPreset('past_24h')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all {{ $preset === 'past_24h' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Past 24h
                </button>
                <button type="button"
                        wire:click="applyPreset('past_7d')"
                        class="px-2.5 py-1 text-xs font-semibold rounded-md transition-all {{ $preset === 'past_7d' ? 'bg-white dark:bg-slate-700 text-blue-600 dark:text-blue-400 shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white' }}">
                    Past 7 Days
                </button>
            </div>
        </div>

        {{-- Query Filter Controls --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-12 gap-3 pt-4">
            {{-- Target Vehicle / Device Picker --}}
            <div class="lg:col-span-5">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    Vehicle / GPS Tracker
                </label>
                <select wire:model="selectedTarget"
                        class="w-full rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-sm focus:ring-blue-500 focus:border-blue-500 shadow-sm">
                    <option value="">-- Choose Vehicle or Tracker --</option>
                    @foreach($targetOptions as $opt)
                        <option value="{{ $opt['key'] }}">
                            {{ $opt['label'] }} — {{ $opt['sublabel'] }}
                        </option>
                    @endforeach
                </select>
            </div>

            {{-- Start Date & Time --}}
            <div class="lg:col-span-3">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    Start (Date & Time)
                </label>
                <div class="flex items-center gap-1.5">
                    <input type="date"
                           wire:model="startDate"
                           class="w-3/5 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                    <input type="time"
                           wire:model="startTime"
                           class="w-2/5 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                </div>
            </div>

            {{-- End Date & Time --}}
            <div class="lg:col-span-3">
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider mb-1">
                    End (Date & Time)
                </label>
                <div class="flex items-center gap-1.5">
                    <input type="date"
                           wire:model="endDate"
                           class="w-3/5 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                    <input type="time"
                           wire:model="endTime"
                           class="w-2/5 rounded-lg border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-white text-xs py-2 focus:ring-blue-500 focus:border-blue-500 shadow-sm" />
                </div>
            </div>

            {{-- Load Button --}}
            <div class="lg:col-span-1 flex items-end">
                <button type="button"
                        wire:click="loadTrack"
                        wire:loading.attr="disabled"
                        class="w-full inline-flex items-center justify-center gap-1.5 px-3 py-2 bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white rounded-lg text-sm font-semibold shadow-sm transition-all duration-150 disabled:opacity-50">
                    <span wire:loading.remove wire:target="loadTrack">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                    </span>
                    <span wire:loading wire:target="loadTrack">
                        <svg class="animate-spin w-4 h-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>
                    </span>
                    <span class="hidden lg:inline">Fetch</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Error Notice --}}
    @if($errorMessage)
        <div class="rounded-xl border border-amber-300 bg-amber-50 dark:bg-amber-950/40 dark:border-amber-900 p-4 text-sm text-amber-800 dark:text-amber-200 flex items-start gap-3 shadow-sm">
            <svg class="h-5 w-5 text-amber-500 flex-shrink-0 mt-0.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
            </svg>
            <div>
                <p class="font-bold">Trip History Query Notice</p>
                <p class="text-xs mt-0.5">{{ $errorMessage }}</p>
            </div>
        </div>
    @endif

    {{-- Success / Metric Strip --}}
    @if($trackData && !empty($trackData['total_points']))
        <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-3">
            {{-- Total Distance --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Total Odometer Distance</div>
                <div class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5 flex items-baseline gap-1">
                    {{ number_format((float) $trackData['total_distance_km'], 1) }}
                    <span class="text-xs font-semibold text-slate-500">km</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">{{ $trackData['total_points'] }} GPS breadcrumbs</div>
            </div>

            {{-- Moving Time --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-emerald-600 dark:text-emerald-400 tracking-wider">Moving Time</div>
                <div class="text-lg font-extrabold text-emerald-700 dark:text-emerald-300 mt-0.5">
                    {{ $trackData['moving_duration_human'] }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">Avg {{ $trackData['avg_speed_kmh'] }} km/h</div>
            </div>

            {{-- Idling Time --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-amber-600 dark:text-amber-400 tracking-wider">Idling Duration</div>
                <div class="text-lg font-extrabold text-amber-700 dark:text-amber-300 mt-0.5">
                    {{ $trackData['idling_duration_human'] }}
                </div>
                <div class="text-[10px] text-amber-600/80 dark:text-amber-400/80 mt-0.5">Engine ON stationary</div>
            </div>

            {{-- Parked Time --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-blue-600 dark:text-blue-400 tracking-wider">Parked Duration</div>
                <div class="text-lg font-extrabold text-blue-700 dark:text-blue-300 mt-0.5">
                    {{ $trackData['parking_duration_human'] }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">Engine OFF stationary</div>
            </div>

            {{-- Max Speed --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-slate-500 tracking-wider">Top Recorded Speed</div>
                <div class="text-lg font-extrabold text-slate-900 dark:text-white mt-0.5 flex items-baseline gap-1">
                    {{ $trackData['max_speed_kmh'] }}
                    <span class="text-xs font-semibold text-slate-500">km/h</span>
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">Verified telematics</div>
            </div>

            {{-- Total Stops --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl p-3.5 border border-slate-200 dark:border-slate-800 shadow-sm">
                <div class="text-[10px] uppercase font-bold text-purple-600 dark:text-purple-400 tracking-wider">Trip Stops</div>
                <div class="text-lg font-extrabold text-purple-700 dark:text-purple-300 mt-0.5">
                    {{ count($stops) }}
                </div>
                <div class="text-[10px] text-slate-400 mt-0.5">&ge; 60s stationary</div>
            </div>
        </div>

        {{-- Planned Itinerary Comparison Alert if exists --}}
        @if($itineraryComparison && $itineraryComparison['has_itinerary'])
            <div class="bg-gradient-to-r from-blue-50 to-indigo-50 dark:from-slate-800/90 dark:to-slate-900/90 rounded-xl border border-blue-200 dark:border-blue-900/50 p-4 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="flex items-start gap-3">
                    <div class="p-2 rounded-lg bg-blue-600 text-white mt-0.5">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                    </div>
                    <div>
                        <div class="flex items-center gap-2">
                            <span class="text-xs font-bold uppercase tracking-wider text-blue-700 dark:text-blue-400">Scheduled Itinerary Linked</span>
                            <span class="text-[10px] px-2 py-0.5 rounded-full font-bold {{ $itineraryComparison['status'] === 'FINALIZED' ? 'bg-emerald-100 text-emerald-800' : 'bg-amber-100 text-amber-800' }}">
                                {{ $itineraryComparison['status'] }}
                            </span>
                        </div>
                        <h4 class="text-sm font-bold text-slate-900 dark:text-white mt-0.5">
                            {{ $itineraryComparison['title'] }} &bull; Destination: {{ $itineraryComparison['destination'] }}
                        </h4>
                        <p class="text-xs text-slate-600 dark:text-slate-400">
                            Assigned Driver: <strong>{{ $itineraryComparison['driver_name'] }}</strong> &bull; {{ $itineraryComparison['legs_count'] }} Leg(s)
                        </p>
                    </div>
                </div>

                <div class="flex items-center gap-6 self-end md:self-auto text-right">
                    <div>
                        <span class="text-[10px] text-slate-500 uppercase font-semibold block">Planned vs Actual Odo</span>
                        <div class="text-sm font-extrabold text-slate-800 dark:text-white">
                            {{ number_format((float) $itineraryComparison['planned_distance'], 1) }} km
                            <span class="text-slate-400 font-normal">planned</span> &bull;
                            {{ number_format((float) $itineraryComparison['actual_distance'], 1) }} km
                            <span class="text-slate-400 font-normal">actual</span>
                        </div>
                    </div>
                    <div>
                        <span class="text-[10px] text-slate-500 uppercase font-semibold block">Mileage Variance</span>
                        <span class="text-sm font-extrabold {{ $itineraryComparison['variance_km'] > 0 ? 'text-amber-600 dark:text-amber-400' : 'text-emerald-600 dark:text-emerald-400' }}">
                            {{ $itineraryComparison['variance_km'] > 0 ? '+' : '' }}{{ $itineraryComparison['variance_km'] }} km
                            ({{ $itineraryComparison['variance_percent'] }}%)
                        </span>
                    </div>
                </div>
            </div>
        @endif
    @endif

    {{-- Main Replay Workspace (Map & Controls & Sidebar - Always rendered to prevent Leaflet container collapse) --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        {{-- Left Side: Interactive Map & Playback Deck --}}
        <div class="lg:col-span-8 space-y-3">
            {{-- Leaflet Map Container --}}
            <div class="relative bg-slate-100 dark:bg-slate-900 rounded-xl overflow-hidden shadow-sm border border-slate-200 dark:border-slate-800">
                <div id="trip-replay-map" wire:ignore></div>

                {{-- Empty Route Prompt Overlay --}}
                <div x-show="!hasTrack"
                     x-transition:leave="transition ease-in duration-200"
                     x-transition:leave-start="opacity-100"
                     x-transition:leave-end="opacity-0"
                     class="absolute inset-0 z-[400] flex items-center justify-center bg-slate-900/50 backdrop-blur-[2px]">
                    <div class="text-center p-6 max-w-sm bg-white dark:bg-slate-900 rounded-2xl shadow-xl border border-slate-200 dark:border-slate-800">
                        <div class="h-12 w-12 rounded-full bg-blue-100 dark:bg-blue-950/80 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto mb-3">
                            <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 20l-5.447-2.724A1 1 0 013 16.382V5.618a1 1 0 011.447-.894L9 7m0 13l6-3m-6 3V7m6 10l4.553 2.276A1 1 0 0021 18.382V7.618a1 1 0 00-.553-.894L15 4m0 13V4m0 0L9 7"/></svg>
                        </div>
                        <h3 class="text-sm font-bold text-slate-800 dark:text-white">Route Replay Ready</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Select a vehicle and click <strong>Fetch</strong> to inspect GPS breadcrumbs, engine states, and stop durations.</p>
                    </div>
                </div>

                {{-- HUD Overlay on Map --}}
                <div x-show="hasTrack"
                     x-transition
                     class="absolute top-4 left-4 z-[500] bg-slate-900/90 backdrop-blur-md text-white p-3.5 rounded-xl border border-white/10 shadow-2xl pointer-events-none min-w-[220px]">
                    <div class="text-[10px] uppercase font-bold text-blue-400 tracking-wider">HUD Telemetry</div>
                    <div class="text-xs font-semibold text-slate-300 mt-0.5" x-text="currentTimestamp || 'Ready'"></div>

                    <div class="flex items-baseline gap-2 mt-2">
                        <span class="text-2xl font-black text-white" x-text="currentSpeed"></span>
                        <span class="text-xs font-semibold text-slate-400">km/h</span>
                        <span class="ml-auto text-xs font-bold px-2 py-0.5 rounded"
                              :class="currentIgnition ? 'bg-amber-500/20 text-amber-300 border border-amber-500/30' : 'bg-slate-700 text-slate-300'"
                              x-text="currentIgnition ? '⚡ ACC ON' : '⭕ ACC OFF'">
                        </span>
                    </div>

                    <div class="mt-2.5 pt-2 border-t border-white/10 flex items-center justify-between text-[11px] text-slate-400">
                        <div>
                            <span>Dist: </span>
                            <strong class="text-white" x-text="currentDistanceKm + ' / ' + totalDistanceKm + ' km'"></strong>
                        </div>
                        <div class="flex items-center gap-1">
                            <span>Heading: </span>
                            <strong class="text-white" x-text="currentHeading + '°'"></strong>
                            <span class="inline-block transform" :style="'transform: rotate(' + currentHeading + 'deg)'">&uarr;</span>
                        </div>
                    </div>
                </div>

                {{-- Map Action Floating Badges (Top Right) --}}
                <div x-show="hasTrack"
                     x-transition
                     class="absolute top-4 right-4 z-[500] flex flex-col gap-2">
                    <button type="button"
                            @click="fitBounds()"
                            class="bg-white/95 dark:bg-slate-800/95 hover:bg-white text-slate-700 dark:text-slate-200 px-3 py-1.5 rounded-lg text-xs font-bold shadow-md border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1.5 backdrop-blur-sm">
                        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 8V4m0 0h4M4 4l5 5m11-5h-4m4 0v4m0-4l-5 5M4 16v4m0 0h4m-4 0l5-5m11 5l-5-5m5 5v-4m0 4h-4"/></svg>
                        Fit Track
                    </button>
                    <button type="button"
                            @click="autoFollow = !autoFollow"
                            :class="autoFollow ? 'bg-blue-600 text-white' : 'bg-white/95 dark:bg-slate-800/95 text-slate-700 dark:text-slate-200'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold shadow-md border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1.5 backdrop-blur-sm">
                        <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                        <span x-text="autoFollow ? 'Tracking ON' : 'Tracking OFF'"></span>
                    </button>
                    <button type="button"
                            @click="toggleStops()"
                            :class="showStopsOnMap ? 'bg-amber-600 text-white' : 'bg-white/95 dark:bg-slate-800/95 text-slate-700 dark:text-slate-200'"
                            class="px-3 py-1.5 rounded-lg text-xs font-bold shadow-md border border-slate-200 dark:border-slate-700 transition-all flex items-center gap-1.5 backdrop-blur-sm">
                        <span class="h-2 w-2 rounded-full" :class="showStopsOnMap ? 'bg-white' : 'bg-amber-500'"></span>
                        <span x-text="showStopsOnMap ? 'Stops Shown' : 'Stops Hidden'"></span>
                    </button>
                </div>
            </div>

            {{-- Playback Control Deck --}}
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-4 space-y-3">
                {{-- Timeline Scrubber Slider --}}
                <div class="space-y-1">
                    <div class="flex items-center justify-between text-xs font-bold text-slate-600 dark:text-slate-400">
                        <span x-text="currentTimestamp || 'Start'"></span>
                        <span class="text-blue-600 dark:text-blue-400 font-extrabold" x-text="totalPoints > 0 ? (Math.round(((currentIndex + 1) / Math.max(1, totalPoints)) * 100) + '% Completed') : 'No Route Loaded'"></span>
                        <span x-text="trackEndTimestamp || 'End'"></span>
                    </div>
                    <input type="range"
                           min="0"
                           :max="Math.max(0, totalPoints - 1)"
                           x-model.number="currentIndex"
                           @input="seekTo(currentIndex)"
                           :disabled="!hasTrack || totalPoints === 0"
                           class="w-full h-2 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-blue-600 disabled:opacity-50">
                </div>

                {{-- Playback Buttons & Speed Multipliers --}}
                <div class="flex flex-wrap items-center justify-between gap-3 pt-1">
                    {{-- Transport Controls --}}
                    <div class="flex items-center gap-2">
                        {{-- Rewind --}}
                        <button type="button"
                                @click="seekTo(0)"
                                :disabled="!hasTrack"
                                title="Rewind to Beginning"
                                class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors disabled:opacity-40">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M11 18V6l-8.5 6 8.5 6zm.5-6l8.5 6V6l-8.5 6z"/></svg>
                        </button>

                        {{-- Step Backward --}}
                        <button type="button"
                                @click="stepBackward()"
                                :disabled="!hasTrack"
                                title="Step Backward"
                                class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors disabled:opacity-40">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 6h2v12H6zm3.5 6l8.5 6V6z"/></svg>
                        </button>

                        {{-- Play / Pause Main Button --}}
                        <button type="button"
                                @click="togglePlay()"
                                :disabled="!hasTrack || totalPoints === 0"
                                class="px-5 py-2 rounded-lg bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-bold text-sm shadow-sm transition-all flex items-center gap-2 disabled:opacity-50">
                            <template x-if="!isPlaying">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M8 5v14l11-7z"/></svg>
                                    <span>Play Replay</span>
                                </div>
                            </template>
                            <template x-if="isPlaying">
                                <div class="flex items-center gap-1.5">
                                    <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 19h4V5H6v14zm8-14v14h4V5h-4z"/></svg>
                                    <span>Pause</span>
                                </div>
                            </template>
                        </button>

                        {{-- Step Forward --}}
                        <button type="button"
                                @click="stepForward()"
                                :disabled="!hasTrack"
                                title="Step Forward"
                                class="p-2 rounded-lg bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 transition-colors disabled:opacity-40">
                            <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24"><path d="M6 18l8.5-6L6 6v12zM16 6v12h2V6h-2z"/></svg>
                        </button>
                    </div>

                    {{-- Speed Multiplier Pills --}}
                    <div class="flex items-center gap-1 bg-slate-100 dark:bg-slate-800 p-1 rounded-lg border border-slate-200 dark:border-slate-700">
                        <span class="text-[10px] uppercase font-bold text-slate-400 px-1">Speed</span>
                        <template x-for="spd in [1, 2, 5, 10, 20, 50, 100]" :key="spd">
                            <button type="button"
                                    @click="playbackSpeed = spd"
                                    :class="playbackSpeed === spd ? 'bg-blue-600 text-white font-bold shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
                                    class="px-2 py-0.5 rounded text-xs transition-colors"
                                    x-text="spd + 'x'">
                            </button>
                        </template>
                    </div>
                </div>
            </div>
        </div>

        {{-- Right Side: Stops List & Trip Leg Details --}}
        <div class="lg:col-span-4 space-y-3">
            <div class="bg-white dark:bg-slate-900 rounded-xl shadow-sm border border-slate-200 dark:border-slate-800 p-4 flex flex-col h-full max-h-[730px]">
                <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-slate-800">
                    <div>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Trip Stops & Stationary Events</h3>
                        <p class="text-xs text-slate-500">{{ count($stops) }} stops detected along trip</p>
                    </div>
                    @if(count($stops) > 0)
                        <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-400">
                            Click to jump
                        </span>
                    @endif
                </div>

                {{-- Scrollable Stops List --}}
                <div class="overflow-y-auto space-y-2.5 mt-3 pr-1 flex-1">
                    @forelse($stops as $idx => $stop)
                        <div @click="seekTo({{ $stop['point_index'] }})"
                             class="p-3 rounded-lg border border-slate-200 dark:border-slate-800 hover:border-blue-400 dark:hover:border-blue-500 cursor-pointer transition-all hover:shadow-sm bg-slate-50/50 dark:bg-slate-800/40 group">
                            <div class="flex items-center justify-between">
                                <div class="flex items-center gap-2">
                                    <span class="flex items-center justify-center w-5 h-5 rounded-full text-[10px] font-extrabold {{ $stop['type'] === 'idling' ? 'bg-amber-100 text-amber-800 dark:bg-amber-900/60 dark:text-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-900/60 dark:text-blue-300' }}">
                                        {{ $idx + 1 }}
                                    </span>
                                    <span class="text-xs font-bold {{ $stop['type'] === 'idling' ? 'text-amber-700 dark:text-amber-400' : 'text-blue-700 dark:text-blue-400' }}">
                                        {{ $stop['type'] === 'idling' ? 'Idling Stop' : 'Parking Stop' }}
                                    </span>
                                </div>
                                <span class="text-xs font-extrabold text-slate-800 dark:text-slate-200">
                                    {{ $stop['duration_human'] }}
                                </span>
                            </div>

                            <div class="text-[11px] text-slate-500 dark:text-slate-400 mt-2 flex items-center justify-between">
                                <span>{{ \Carbon\Carbon::parse($stop['start_time'])->format('M d, h:i A') }} &rarr; {{ \Carbon\Carbon::parse($stop['end_time'])->format('h:i A') }}</span>
                                <span class="text-blue-600 dark:text-blue-400 font-semibold group-hover:underline text-[10px]">
                                    Jump &rarr;
                                </span>
                            </div>

                            <div class="text-[10px] text-slate-400 font-mono mt-1">
                                {{ number_format((float) $stop['lat'], 5) }}, {{ number_format((float) $stop['lng'], 5) }}
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16 text-slate-400">
                            <svg class="w-8 h-8 mx-auto text-slate-300 dark:text-slate-600 mb-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                            <p class="text-xs font-semibold">No extended stops during this interval</p>
                            <p class="text-[11px] text-slate-400 mt-0.5">Vehicle was either in continuous transit or stationary periods were under 60 seconds.</p>
                        </div>
                    @endforelse
                </div>

                {{-- Map Legend --}}
                <div class="mt-4 pt-3 border-t border-slate-100 dark:border-slate-800 text-[11px] text-slate-500 space-y-1.5">
                    <div class="font-bold text-slate-700 dark:text-slate-300 uppercase text-[10px] tracking-wider">Map Legend</div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-500"></span>
                        <span>Start Point (Green)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-red-500"></span>
                        <span>End / Destination Point (Red)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-amber-500"></span>
                        <span>Idling Stop (Engine ON, Speed &lt; 3 km/h)</span>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="h-2.5 w-2.5 rounded-full bg-blue-500"></span>
                        <span>Parking Stop (Engine OFF)</span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Leaflet Map & Replay JavaScript Engine --}}
    @push('scripts')
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <script>
            // Defensive patch for Leaflet Popup zoom animation to prevent null _map crashes
            if (typeof L !== 'undefined' && L.Popup && L.Popup.prototype._animateZoom) {
                const _origPopupAnimateZoom = L.Popup.prototype._animateZoom;
                L.Popup.prototype._animateZoom = function(opt) {
                    if (!this._map) return;
                    return _origPopupAnimateZoom.call(this, opt);
                };
            }

            window.tripHistoryReplay = function tripHistoryReplay(config) {
                return {
                    track: config.initialTrack || null,
                    stops: config.initialStops || [],
                    points: config.initialPoints || [],
                    pointsKey: config.initialPointsKey || '',
                    map: null,
                    polyline: null,
                    vehicleMarker: null,
                    startMarker: null,
                    endMarker: null,
                    stopMarkers: [],
                    currentIndex: 0,
                    totalPoints: 0,
                    isPlaying: false,
                    playbackSpeed: 2,
                    playbackTimer: null,
                    autoFollow: true,
                    showStopsOnMap: true,
                    hasTrack: false,

                    // HUD state
                    currentSpeed: 0,
                    currentTimestamp: '',
                    currentIgnition: false,
                    currentDistanceKm: '0.0',
                    totalDistanceKm: '0.0',
                    currentHeading: 0,
                    trackEndTimestamp: '',

                    /**
                     * Fetch track points from the server-side cache endpoint.
                     * This avoids embedding large coordinate arrays in Livewire payloads.
                     */
                    async fetchPoints(key) {
                        if (!key) return [];
                        try {
                            console.log('[TripReplay] Fetching points for key:', key);
                            const res = await fetch(`/trip-history/points/${key}`, {
                                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                            });
                            if (!res.ok) {
                                console.warn('[TripReplay] fetchPoints returned HTTP ' + res.status);
                                return [];
                            }
                            const json = await res.json();
                            const pts = Array.isArray(json.points) ? json.points : [];
                            console.log('[TripReplay] Successfully fetched points count:', pts.length);
                            return pts;
                        } catch (e) {
                            console.warn('[TripReplay] Failed to fetch track points:', e);
                            return [];
                        }
                    },

                    init() {
                        this.$nextTick(async () => {
                            this.initMap();

                            // Load initial track: prefer in-memory points, fall back to server fetch via key
                            if (this.track) {
                                let pts = Array.isArray(this.points) && this.points.length > 0 ? this.points : [];

                                if (pts.length === 0 && this.pointsKey) {
                                    pts = await this.fetchPoints(this.pointsKey);
                                }

                                if (pts.length > 0) {
                                    const fullTrack = { ...this.track, points: pts };
                                    this.loadTrackData(fullTrack, this.stops);
                                }
                            }
                        });

                        const handleTrackLoaded = async (rawPayload) => {
                            let raw = rawPayload;
                            if (Array.isArray(raw) && raw.length > 0) raw = raw[0];
                            const track = raw?.track || raw;
                            const stops = raw?.stops || this.stops || [];
                            const key = raw?.pointsKey || track?.pointsKey || this.pointsKey || '';
                            console.log('[TripReplay] track-data-loaded event received:', { key, hasTrack: !!track });
                            if (track) {
                                let pts = [];
                                if (key) {
                                    pts = await this.fetchPoints(key);
                                } else if (Array.isArray(track.points) && track.points.length > 0) {
                                    pts = track.points;
                                }
                                const fullTrack = { ...track, points: pts };
                                this.loadTrackData(fullTrack, stops);
                            }
                        };

                        // Listen for Livewire event on track load
                        window.addEventListener('track-data-loaded', async (e) => {
                            await handleTrackLoaded(e.detail);
                        });

                        window.addEventListener('track-data-cleared', () => {
                            this.clearMap();
                            this.hasTrack = false;
                        });

                        window.addEventListener('resize', () => {
                            if (this.map) {
                                this.map.invalidateSize();
                            }
                        });
                    },

                    initMap() {
                        if (typeof L === 'undefined') {
                            setTimeout(() => this.initMap(), 100);
                            return;
                        }

                        const container = document.getElementById('trip-replay-map');
                        if (!container) return;

                        if (this.map) {
                            this.map.invalidateSize();
                            return;
                        }

                        if (container._leaflet_id) {
                            container._leaflet_id = null;
                        }

                        // Default coordinates: Metro Manila / Calabarzon region
                        this.map = L.map('trip-replay-map', {
                            zoomControl: true,
                            attributionControl: false
                        }).setView([14.2833, 121.0833], 12);

                        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                            maxZoom: 19
                        }).addTo(this.map);

                        setTimeout(() => {
                            if (this.map) {
                                this.map.invalidateSize();
                            }
                        }, 250);
                    },

                    loadTrackData(track, stops) {
                        if (!track) return;

                        this.track = track;
                        this.stops = stops || [];
                        this.pause();

                        // Guarantee map is ready
                        if (!this.map) {
                            this.initMap();
                        }

                        if (!this.map) {
                            setTimeout(() => this.loadTrackData(track, stops), 150);
                            return;
                        }

                        this.clearMap();

                        const rawPoints = track.points || [];
                        // Parse and validate each coordinate as valid non-zero float
                        const validPoints = [];
                        for (const p of rawPoints) {
                            if (!p) continue;
                            const lat = parseFloat(p.lat);
                            const lng = parseFloat(p.lng);
                            if (!isNaN(lat) && !isNaN(lng) && (Math.abs(lat) > 0.001 || Math.abs(lng) > 0.001)) {
                                p.lat = lat;
                                p.lng = lng;
                                validPoints.push(p);
                            }
                        }

                        if (validPoints.length === 0) {
                            console.warn('[TripReplay] No valid coordinates in track points:', rawPoints.length);
                            this.hasTrack = false;
                            return;
                        }

                        this.track.points = validPoints;
                        this.totalPoints = validPoints.length;
                        this.totalDistanceKm = Number(track.total_distance_km || 0).toFixed(1);
                        this.trackEndTimestamp = validPoints[validPoints.length - 1].gps_time || '';
                        this.currentIndex = 0;
                        this.hasTrack = true;

                        // Build lat/lng array for Leaflet polyline
                        const latLngs = validPoints.map(p => [p.lat, p.lng]);

                        // Polyline with primary blue casing
                        this.polyline = L.polyline(latLngs, {
                            color: '#2563eb',
                            weight: 5,
                            opacity: 0.85,
                            lineCap: 'round',
                            lineJoin: 'round'
                        }).addTo(this.map);

                        // Start Marker (Green Flag)
                        const startPt = validPoints[0];
                        this.startMarker = L.circleMarker([startPt.lat, startPt.lng], {
                            radius: 8,
                            fillColor: '#10b981',
                            color: '#ffffff',
                            weight: 3,
                            opacity: 1,
                            fillOpacity: 1
                        }).addTo(this.map).bindPopup(`
                            <div style="font-family: inherit; padding: 10px; width: 180px;">
                                <div style="font-size: 10px; font-weight: 800; color: #10b981; text-transform: uppercase;">Trip Origin</div>
                                <div style="font-size: 12px; font-weight: 700; color: #0f172a;">${startPt.gps_time}</div>
                            </div>
                        `);

                        // End Marker (Red Flag)
                        const endPt = validPoints[validPoints.length - 1];
                        this.endMarker = L.circleMarker([endPt.lat, endPt.lng], {
                            radius: 8,
                            fillColor: '#ef4444',
                            color: '#ffffff',
                            weight: 3,
                            opacity: 1,
                            fillOpacity: 1
                        }).addTo(this.map).bindPopup(`
                            <div style="font-family: inherit; padding: 10px; width: 180px;">
                                <div style="font-size: 10px; font-weight: 800; color: #ef4444; text-transform: uppercase;">Trip Destination</div>
                                <div style="font-size: 12px; font-weight: 700; color: #0f172a;">${endPt.gps_time}</div>
                            </div>
                        `);

                        // Stop Markers along the route
                        this.stops.forEach((stop, idx) => {
                            if (!stop) return;
                            const sLat = parseFloat(stop.lat);
                            const sLng = parseFloat(stop.lng);
                            if (isNaN(sLat) || isNaN(sLng) || (Math.abs(sLat) < 0.001 && Math.abs(sLng) < 0.001)) return;

                            const isIdling = stop.type === 'idling';
                            const stopMarker = L.circleMarker([sLat, sLng], {
                                radius: 6,
                                fillColor: isIdling ? '#f59e0b' : '#3b82f6',
                                color: '#ffffff',
                                weight: 2,
                                opacity: 1,
                                fillOpacity: 0.95
                            }).bindPopup(`
                                <div style="font-family: inherit; padding: 10px; width: 210px;">
                                    <div style="font-size: 10px; font-weight: 800; color: ${isIdling ? '#d97706' : '#2563eb'}; text-transform: uppercase;">
                                        Stop #${idx + 1} &bull; ${isIdling ? 'Idling (Engine ON)' : 'Parked (Engine OFF)'}
                                    </div>
                                    <div style="font-size: 13px; font-weight: 800; color: #0f172a; margin-top: 2px;">
                                        ${stop.duration_human}
                                    </div>
                                    <div style="font-size: 11px; color: #64748b; margin-top: 4px;">
                                        ${stop.start_time} &rarr; ${stop.end_time}
                                    </div>
                                </div>
                            `);

                            if (this.showStopsOnMap) {
                                stopMarker.addTo(this.map);
                            }
                            this.stopMarkers.push(stopMarker);
                        });

                        // Moving Vehicle Marker
                        this.vehicleMarker = L.marker([startPt.lat, startPt.lng], {
                            icon: this.createVehicleIcon(startPt.direction || 0, startPt.speed || 0, startPt.is_ignition_on)
                        }).addTo(this.map);

                        // Update HUD for initial point
                        this.updateHud(startPt);

                        // Fit polyline bounds without animation to prevent zoom animation collision
                        this.fitBounds(false);

                        setTimeout(() => {
                            if (this.map) {
                                this.map.invalidateSize();
                                this.fitBounds(false);
                            }
                        }, 250);
                    },

                    createVehicleIcon(heading, speed, isIgnitionOn) {
                        const pulse = speed >= 3 ? `<div class="absolute -inset-1 rounded-full bg-emerald-400 opacity-60 animate-ping"></div>` : '';
                        const color = speed >= 3 ? '#10b981' : (isIgnitionOn ? '#f59e0b' : '#3b82f6');

                        const html = `
                            <div class="relative flex items-center justify-center">
                                ${pulse}
                                <div style="background-color: ${color}; transform: rotate(${heading}deg);"
                                     class="relative flex items-center justify-center h-8 w-8 rounded-full shadow-2xl border-2 border-white ring-2 ring-black/20">
                                    <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 24 24">
                                        <path d="M12 2L4.5 20.29l.71.71L12 18l6.79 3 .71-.71z"/>
                                    </svg>
                                </div>
                            </div>
                        `;

                        return L.divIcon({
                            className: 'custom-replay-pin',
                            html: html,
                            iconSize: [32, 32],
                            iconAnchor: [16, 16]
                        });
                    },

                    updateHud(pt) {
                        if (!pt) return;
                        this.currentSpeed = Math.round(pt.speed || 0);
                        this.currentTimestamp = pt.gps_time || '';
                        this.currentIgnition = !!pt.is_ignition_on;
                        this.currentDistanceKm = Number(pt.cumulative_distance_km || 0).toFixed(1);
                        this.currentHeading = Math.round(pt.direction || 0);
                    },

                    toggleStops() {
                        this.showStopsOnMap = !this.showStopsOnMap;
                        this.stopMarkers.forEach(m => {
                            if (this.showStopsOnMap) {
                                m.addTo(this.map);
                            } else {
                                this.map.removeLayer(m);
                            }
                        });
                    },

                    seekTo(index) {
                        if (!this.track || !this.track.points || this.track.points.length === 0) return;
                        const idx = Math.max(0, Math.min(index, this.totalPoints - 1));
                        this.currentIndex = idx;
                        const pt = this.track.points[idx];

                        if (this.vehicleMarker && pt) {
                            this.vehicleMarker.setLatLng([pt.lat, pt.lng]);
                            this.vehicleMarker.setIcon(this.createVehicleIcon(pt.direction || 0, pt.speed || 0, pt.is_ignition_on));

                            if (this.autoFollow && this.map) {
                                this.map.panTo([pt.lat, pt.lng], { animate: false });
                            }
                        }

                        this.updateHud(pt);
                    },

                    stepForward() {
                        const step = (this.totalPoints > 2000) ? 25 : 5;
                        this.seekTo(this.currentIndex + step);
                    },

                    stepBackward() {
                        const step = (this.totalPoints > 2000) ? 25 : 5;
                        this.seekTo(this.currentIndex - step);
                    },

                    togglePlay() {
                        if (this.isPlaying) {
                            this.pause();
                        } else {
                            this.play();
                        }
                    },

                    play() {
                        if (!this.track || !this.track.points || this.totalPoints === 0) return;
                        this.isPlaying = true;

                        if (this.currentIndex >= this.totalPoints - 1) {
                            this.currentIndex = 0;
                        }

                        this.scheduleNextTick();
                    },

                    pause() {
                        this.isPlaying = false;
                        if (this.playbackTimer) {
                            clearTimeout(this.playbackTimer);
                            this.playbackTimer = null;
                        }
                    },

                    scheduleNextTick() {
                        if (!this.isPlaying) return;

                        const stepSize = (this.totalPoints > 2000 && this.playbackSpeed >= 20) 
                            ? Math.ceil(this.playbackSpeed / 10) 
                            : 1;

                        const delay = Math.max(20, Math.round(300 / this.playbackSpeed));

                        this.playbackTimer = setTimeout(() => {
                            if (!this.isPlaying) return;

                            if (this.currentIndex < this.totalPoints - 1) {
                                this.currentIndex = Math.min(this.totalPoints - 1, this.currentIndex + stepSize);
                                this.seekTo(this.currentIndex);
                                this.scheduleNextTick();
                            } else {
                                this.pause();
                            }
                        }, delay);
                    },

                    fitBounds(animate = false) {
                        if (this.map && this.polyline) {
                            try {
                                const bounds = this.polyline.getBounds();
                                if (bounds && bounds.isValid()) {
                                    this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 16, animate: !!animate });
                                }
                            } catch (e) {
                                console.warn('fitBounds warning:', e);
                            }
                        }
                    },

                    clearMap() {
                        if (this.map) {
                            try { this.map.closePopup(); } catch (e) {}
                        }
                        if (this.polyline && this.map) {
                            try { this.polyline.unbindPopup?.(); } catch (e) {}
                            this.map.removeLayer(this.polyline);
                            this.polyline = null;
                        }
                        if (this.vehicleMarker && this.map) {
                            try { this.vehicleMarker.unbindPopup?.(); } catch (e) {}
                            this.map.removeLayer(this.vehicleMarker);
                            this.vehicleMarker = null;
                        }
                        if (this.startMarker && this.map) {
                            try { this.startMarker.unbindPopup?.(); } catch (e) {}
                            this.map.removeLayer(this.startMarker);
                            this.startMarker = null;
                        }
                        if (this.endMarker && this.map) {
                            try { this.endMarker.unbindPopup?.(); } catch (e) {}
                            this.map.removeLayer(this.endMarker);
                            this.endMarker = null;
                        }
                        this.stopMarkers.forEach(m => {
                            if (m) {
                                try { m.unbindPopup?.(); } catch (e) {}
                                if (this.map) this.map.removeLayer(m);
                            }
                        });
                        this.stopMarkers = [];
                    }
                };
            };

            // Register with Alpine if Alpine is already loaded or when it initializes
            if (window.Alpine) {
                window.Alpine.data('tripHistoryReplay', (config) => window.tripHistoryReplay(config));
            }
            document.addEventListener('alpine:init', () => {
                if (window.Alpine) {
                    window.Alpine.data('tripHistoryReplay', (config) => window.tripHistoryReplay(config));
                }
            });
        </script>
    @endpush
</div>
