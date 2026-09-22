<?php

namespace App\Http\Controllers;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LongIdlingRecordController extends Controller
{
    public function uploadScreenshot(Request $request, Report $report): JsonResponse
    {
        if ($report->created_by !== auth()->id()) {
            abort(403);
        }

        // Clean record_id if it came as string "null", empty string, or non-numeric
        $recordId = $request->input('record_id');
        if (! is_numeric($recordId) || (int) $recordId <= 0) {
            $request->merge(['record_id' => null]);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:10240',
            'record_id' => 'nullable|exists:long_idling_records,id',
        ]);

        $path = $request->file('image')->store(
            'screenshots/'.$report->id,
            'public'
        );

        if ($request->filled('record_id')) {
            $record = LongIdlingRecord::find($request->record_id);
            if ($record && $record->report_id === $report->id) {
                if ($record->image) {
                    Storage::disk('public')->delete($record->image);
                }
                $record->update(['image' => $path]);
            }
        }

        return response()->json([
            'success' => true,
            'image' => $path,
            'image_url' => Storage::disk('public')->url($path),
        ]);
    }

    public function uploadImage(Request $request, LongIdlingRecord $record): JsonResponse
    {
        if ($record->report->created_by !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'image' => 'required|image|mimes:jpeg,jpg,png,webp|max:5120',
        ]);

        if ($record->image) {
            Storage::disk('public')->delete($record->image);
        }

        $path = $request->file('image')->store(
            'screenshots/'.$record->report_id,
            'public'
        );

        $record->update(['image' => $path]);

        return response()->json([
            'success' => true,
            'image_url' => Storage::disk('public')->url($path),
            'image' => $path,
        ]);
    }

    public function destroy(LongIdlingRecord $record): JsonResponse
    {
        if ($record->report->created_by !== auth()->id()) {
            abort(403);
        }

        if ($record->image) {
            Storage::disk('public')->delete($record->image);
        }

        $record->delete();

        return response()->json(['success' => true]);
    }
}
