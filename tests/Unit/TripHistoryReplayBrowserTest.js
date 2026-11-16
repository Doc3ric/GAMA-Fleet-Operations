import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const view = readFileSync(new URL('../../resources/views/livewire/trip-history-replay.blade.php', import.meta.url), 'utf8');
const script = view.match(/<script>\s*([\s\S]*?)\s*<\/script>\s*@endpush/)?.[1];

function createReplay(points, speed = 2) {
    assert.ok(script, 'Replay script is present in the view');
    let now = 1000;
    let nextFrame;
    let markerPosition;
    const context = {
        window: {},
        document: { addEventListener() {} },
        L: { divIcon(options) { return options; } },
        performance: { now: () => now },
        requestAnimationFrame(callback) { nextFrame = callback; return 1; },
        cancelAnimationFrame() {},
        setTimeout() {},
    };
    vm.runInNewContext(script, context);
    const replay = context.window.tripHistoryReplay({ timezone: 'Asia/Manila' });
    replay.track = { points };
    replay.hasTrack = true;
    replay.totalPoints = points.length;
    replay.trackStartMs = points[0].timestamp_ms;
    replay.trackEndMs = points.at(-1).timestamp_ms;
    replay.totalDurationSeconds = (replay.trackEndMs - replay.trackStartMs) / 1000;
    replay.currentPositionMs = replay.trackStartMs;
    replay.playbackSpeed = speed;
    replay.autoFollow = false;
    replay.vehicleMarker = { setLatLng(position) { markerPosition = position; }, setIcon() {} };

    return {
        replay,
        context,
        frame(time) { now = time; nextFrame(time); },
        position() { return markerPosition; },
        setTime(time) { now = time; },
    };
}

test('replay interpolates by elapsed GPS time and speed changes preserve position', () => {
    const start = Date.parse('2026-01-01T00:00:00Z');
    const player = createReplay([
        { lat: 0, lng: 0, timestamp_ms: start, gps_time: '2026-01-01 08:00:00', speed: 20 },
        { lat: 10, lng: 10, timestamp_ms: start + 10000, gps_time: '2026-01-01 08:00:10', speed: 20 },
    ]);

    player.replay.play();
    player.frame(3000);
    assert.equal(player.replay.currentPositionMs, start + 4000);
    assert.deepEqual(Array.from(player.position()), [4, 4]);

    player.setTime(3000);
    player.replay.changePlaybackSpeed(5);
    player.frame(4000);
    assert.equal(player.replay.currentPositionMs, start + 9000);
    assert.deepEqual(Array.from(player.position()), [9, 9]);
    assert.match(player.replay.currentTimestamp, /08:00:09/);
    assert.equal(player.replay.currentGpsFix, '2026-01-01 08:00:00');
});

test('map style switch replaces the base layer without changing the route', () => {
    const start = Date.parse('2026-01-01T00:00:00Z');
    const player = createReplay([{ lat: 14, lng: 121, timestamp_ms: start }]);
    const changes = [];
    const street = { addTo() { changes.push('street'); } };
    const satellite = { addTo() { changes.push('satellite'); } };
    player.replay.map = { removeLayer(layer) { changes.push(layer === street ? 'remove street' : 'remove satellite'); } };
    player.replay.streetLayer = street;
    player.replay.satelliteLayer = satellite;

    player.replay.setMapStyle('street');
    player.replay.setMapStyle('satellite');

    assert.deepEqual(changes, ['street', 'remove street', 'satellite']);
    assert.equal(player.replay.mapStyle, 'satellite');
});

test('idling and parked stops render as distinct visible map icons', () => {
    const start = Date.parse('2026-01-01T00:00:00Z');
    const points = [
        { lat: 14.1, lng: 121.0, gps_time: '2026-01-01 08:00:00', timestamp_ms: start, speed: 0, state: 'idling' },
        { lat: 14.1, lng: 121.0, gps_time: '2026-01-01 08:05:00', timestamp_ms: start + 300000, speed: 0, state: 'parked' },
    ];
    const player = createReplay(points);
    const stopIcons = [];
    const layer = () => ({
        addTo() { return this; },
        bindPopup() { return this; },
        bindTooltip() { return this; },
        setLatLng() {},
        setIcon() {},
    });
    player.context.L.circleMarker = () => layer();
    player.context.L.polyline = () => layer();
    player.context.L.featureGroup = () => ({
        addTo() { return this; },
        getBounds() { return { isValid: () => true }; },
    });
    player.context.L.marker = (_position, options) => {
        if (options.title) stopIcons.push(options.icon);
        return layer();
    };
    player.replay.map = {
        closePopup() {}, removeLayer() {}, fitBounds() {}, invalidateSize() {},
    };
    const stops = [
        { type: 'idling', lat: 14.1, lng: 121.0, duration_human: '5 min', start_time: points[0].gps_time, end_time: points[1].gps_time },
        { type: 'parked', lat: 14.1, lng: 121.0, duration_human: '5 min', start_time: points[0].gps_time, end_time: points[1].gps_time },
    ];

    player.replay.loadTrackData({ points, total_distance_km: 0 }, stops);

    assert.equal(player.replay.stopMarkers.length, 2);
    assert.match(stopIcons[0].html, /#d97706/);
    assert.match(stopIcons[1].html, /#2563eb/);
    assert.notEqual(stopIcons[0].iconAnchor[0], stopIcons[1].iconAnchor[0]);
    assert.equal(player.replay.currentState, 'idling');
    player.replay.seekTo(1);
    assert.equal(player.replay.currentState, 'parked');
});

test('replay skips offline gaps without drawing motion between their endpoints', () => {
    const start = Date.parse('2026-01-01T00:00:00Z');
    const player = createReplay([
        { lat: 0, lng: 0, timestamp_ms: start, speed: 0 },
        { lat: 10, lng: 10, timestamp_ms: start + 10800000, speed: 0 },
    ]);

    player.replay.play();
    player.frame(1016);

    assert.equal(player.replay.currentPositionMs, start + 10800000);
    assert.deepEqual(Array.from(player.position()), [10, 10]);
    assert.equal(player.replay.gapNotice, 'Skipped an interval without GPS data');
    assert.equal(player.replay.isPlaying, false);
});
