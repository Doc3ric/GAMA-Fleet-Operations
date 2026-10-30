<?php

namespace App\Http\Controllers;

use App\Models\LongIdlingRecord;
use App\Models\Report;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class LongIdlingRecordController extends Controller
{
    public function uploadGenericScreenshot(Request $request): JsonResponse
    {
        // Clean record_id if it came as string "null", empty string, or non-numeric
        $recordId = $request->input('record_id');
        if (! is_numeric($recordId) || (int) $recordId <= 0) {
            $request->merge(['record_id' => null]);
        }

        $reportId = $request->input('report_id');
        if (! is_numeric($reportId) || (int) $reportId <= 0) {
            $request->merge(['report_id' => null]);
        }

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'record_id' => 'nullable|exists:long_idling_records,id',
            'report_id' => 'nullable|exists:reports,id',
        ]);

        if (! $request->hasFile('image') && ! $request->hasFile('images')) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one image file to upload.',
            ], 422);
        }

        $folder = 'screenshots/general';
        $record = null;

        if ($request->filled('record_id')) {
            $record = LongIdlingRecord::with('report')->find($request->record_id);
            if ($record && $record->report && $record->report->created_by === auth()->id()) {
                $folder = 'screenshots/'.$record->report_id;
            } else {
                $record = null;
            }
        } elseif ($request->filled('report_id')) {
            $report = Report::find($request->report_id);
            if ($report && $report->created_by === auth()->id()) {
                $folder = 'screenshots/'.$report->id;
            }
        }

        $files = [];
        if ($request->hasFile('images')) {
            $rawFiles = $request->file('images');
            $files = is_array($rawFiles) ? $rawFiles : [$rawFiles];
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        $uploadedPaths = [];
        $uploadedUrls = [];

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store($folder, 'public');
                $uploadedPaths[] = $path;
                $uploadedUrls[] = Storage::disk('public')->url($path);
            }
        }

        if (empty($uploadedPaths)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images.',
            ], 422);
        }

        if ($record) {
            $currentImages = $record->getAllImages();
            $merged = array_values(array_unique(array_merge($currentImages, $uploadedPaths)));
            $record->update([
                'images' => $merged,
                'image' => $merged[0] ?? null,
            ]);
            $allImages = $merged;
            $allImageUrls = $record->getAllImageUrls();
        } else {
            $allImages = $uploadedPaths;
            $allImageUrls = $uploadedUrls;
        }

        return response()->json([
            'success' => true,
            'image' => $uploadedPaths[0] ?? null,
            'image_url' => $uploadedUrls[0] ?? null,
            'new_images' => $uploadedPaths,
            'new_image_urls' => $uploadedUrls,
            'all_images' => $allImages,
            'all_image_urls' => $allImageUrls,
        ]);
    }

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
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'record_id' => 'nullable|exists:long_idling_records,id',
        ]);

        if (! $request->hasFile('image') && ! $request->hasFile('images')) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one image file.',
            ], 422);
        }

        $files = [];
        if ($request->hasFile('images')) {
            $rawFiles = $request->file('images');
            $files = is_array($rawFiles) ? $rawFiles : [$rawFiles];
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        $uploadedPaths = [];
        $uploadedUrls = [];

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store('screenshots/'.$report->id, 'public');
                $uploadedPaths[] = $path;
                $uploadedUrls[] = Storage::disk('public')->url($path);
            }
        }

        if (empty($uploadedPaths)) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to upload images.',
            ], 422);
        }

        if ($request->filled('record_id')) {
            $record = LongIdlingRecord::find($request->record_id);
            if ($record && $record->report_id === $report->id) {
                $currentImages = $record->getAllImages();
                $merged = array_values(array_unique(array_merge($currentImages, $uploadedPaths)));
                $record->update([
                    'images' => $merged,
                    'image' => $merged[0] ?? null,
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'image' => $uploadedPaths[0] ?? null,
            'image_url' => $uploadedUrls[0] ?? null,
            'new_images' => $uploadedPaths,
            'new_image_urls' => $uploadedUrls,
        ]);
    }

    public function uploadImage(Request $request, LongIdlingRecord $record): JsonResponse
    {
        if ($record->report->created_by !== auth()->id()) {
            abort(403);
        }

        $request->validate([
            'image' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
            'images' => 'nullable|array',
            'images.*' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:10240',
        ]);

        if (! $request->hasFile('image') && ! $request->hasFile('images')) {
            return response()->json([
                'success' => false,
                'message' => 'Please select at least one image file.',
            ], 422);
        }

        $files = [];
        if ($request->hasFile('images')) {
            $rawFiles = $request->file('images');
            $files = is_array($rawFiles) ? $rawFiles : [$rawFiles];
        } elseif ($request->hasFile('image')) {
            $files = [$request->file('image')];
        }

        $uploadedPaths = [];
        $uploadedUrls = [];

        foreach ($files as $file) {
            if ($file && $file->isValid()) {
                $path = $file->store('screenshots/'.$record->report_id, 'public');
                $uploadedPaths[] = $path;
                $uploadedUrls[] = Storage::disk('public')->url($path);
            }
        }

        $currentImages = $record->getAllImages();
        $merged = array_values(array_unique(array_merge($currentImages, $uploadedPaths)));
        $record->update([
            'images' => $merged,
            'image' => $merged[0] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'image' => $uploadedPaths[0] ?? null,
            'image_url' => $uploadedUrls[0] ?? null,
            'new_images' => $uploadedPaths,
            'new_image_urls' => $uploadedUrls,
            'all_images' => $record->getAllImages(),
            'all_image_urls' => $record->getAllImageUrls(),
        ]);
    }

    public function destroy(LongIdlingRecord $record): JsonResponse
    {
        if ($record->report->created_by !== auth()->id()) {
            abort(403);
        }

        $record->deleteAllImages();
        $record->delete();

        return response()->json(['success' => true]);
    }
}
