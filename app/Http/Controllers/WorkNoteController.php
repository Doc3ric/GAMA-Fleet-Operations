<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWorkNoteRequest;
use App\Http\Requests\UpdateWorkNoteRequest;
use App\Models\WorkNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class WorkNoteController extends Controller
{
    /**
     * Display a listing of the user's operational notes and reminders.
     */
    public function index(Request $request): View
    {
        Gate::authorize('viewAny', WorkNote::class);

        $userId = auth()->id();
        $query = WorkNote::where('user_id', $userId);

        if ($search = $request->input('search')) {
            $query->search($search);
        }

        $notes = $query->orderByDesc('is_pinned')
            ->orderByDesc('updated_at')
            ->paginate(24)
            ->withQueryString();

        $totalCount = WorkNote::where('user_id', $userId)->count();
        $pinnedCount = WorkNote::where('user_id', $userId)->pinned()->count();

        return view('work-notes.index', compact(
            'notes',
            'totalCount',
            'pinnedCount'
        ));
    }

    /**
     * Store a newly created operational note.
     */
    public function store(StoreWorkNoteRequest $request): RedirectResponse
    {
        Gate::authorize('create', WorkNote::class);

        $data = $request->validated();
        $data['user_id'] = auth()->id();
        $data['is_pinned'] = $request->boolean('is_pinned');
        $data['color'] = $request->input('color', WorkNote::COLOR_SLATE);

        WorkNote::create($data);

        return redirect()
            ->route('work-notes.index')
            ->with('success', 'Note created successfully.');
    }

    /**
     * Update the specified operational note.
     */
    public function update(UpdateWorkNoteRequest $request, WorkNote $workNote): RedirectResponse
    {
        Gate::authorize('update', $workNote);

        $data = $request->validated();
        if ($request->has('is_pinned')) {
            $data['is_pinned'] = $request->boolean('is_pinned');
        }

        $workNote->update($data);

        return redirect()
            ->route('work-notes.index')
            ->with('success', 'Note updated successfully.');
    }

    /**
     * Toggle the pinned status of a note.
     */
    public function togglePin(WorkNote $workNote): RedirectResponse
    {
        Gate::authorize('togglePin', $workNote);

        $workNote->update([
            'is_pinned' => ! $workNote->is_pinned,
        ]);

        $status = $workNote->is_pinned ? 'pinned to top' : 'unpinned';

        return redirect()
            ->back()
            ->with('success', "Note \"{$workNote->title}\" {$status}.");
    }

    /**
     * Remove the specified operational note.
     */
    public function destroy(WorkNote $workNote): RedirectResponse
    {
        Gate::authorize('delete', $workNote);

        $workNote->delete();

        return redirect()
            ->route('work-notes.index')
            ->with('success', 'Note deleted.');
    }
}
