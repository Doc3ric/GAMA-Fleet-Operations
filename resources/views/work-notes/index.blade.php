<x-app-layout>
    @section('page-title', 'My Work')
    @section('breadcrumb', 'Fleet Management / My Work / Notes')

    <div class="space-y-5" x-data="{
        showCreateModal: false,
        showEditModal: false,
        showDeleteModal: false,

        // Create Note State
        newTitle: '',
        newContent: '',
        newColor: 'slate',
        newIsPinned: false,

        // Edit Note State
        editNote: {
            id: null,
            title: '',
            content: '',
            color: 'slate',
            is_pinned: false,
            url: ''
        },

        // Delete Note State
        deleteNote: {
            id: null,
            title: '',
            url: ''
        },

        submitting: false,
        copiedId: null,

        openCreateModal() {
            this.newTitle = '';
            this.newContent = '';
            this.newColor = 'slate';
            this.newIsPinned = false;
            this.showCreateModal = true;
            this.$nextTick(() => {
                this.$refs.createSubjectInput?.focus();
            });
        },

        openEditModal(note) {
            this.editNote = {
                id: note.id,
                title: note.title,
                content: note.content || '',
                color: note.color || 'slate',
                is_pinned: Boolean(note.is_pinned),
                url: '/work-notes/' + note.id
            };
            this.showEditModal = true;
            this.$nextTick(() => {
                this.$refs.editSubjectInput?.focus();
            });
        },

        openDeleteModal(id, title) {
            this.deleteNote = {
                id: id,
                title: title,
                url: '/work-notes/' + id
            };
            this.showDeleteModal = true;
        },

        copyNote(id, title, content) {
            const fullText = (title + '\n\n' + (content || '')).trim();
            navigator.clipboard.writeText(fullText).then(() => {
                this.copiedId = id;
                setTimeout(() => { this.copiedId = null; }, 2000);
            });
        }
    }">

        {{-- Top Action Header with 4-tab Switcher --}}
        <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-1">
            <div>
                <div class="flex items-center gap-2.5">
                    <h2 class="text-xl font-bold text-slate-800 tracking-tight">My Work</h2>
                    <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 border border-amber-200">
                        Operational Notes ({{ $totalCount }})
                    </span>
                </div>
                <p class="text-xs text-slate-500 mt-0.5">Quick reference memos, operational scratchpad, and shift reminders &mdash; only subject &amp; description</p>
            </div>

            <div class="flex items-center gap-2.5 shrink-0">
                {{-- View Switcher Tab (Tasks / Calendar / Accomplishments / Notes) --}}
                <div class="inline-flex items-center p-0.5 rounded-lg bg-slate-200/80 border border-slate-200 text-xs font-semibold">
                    <a href="{{ route('work-tasks.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75Zm0 5.25h.007v.008H3.75V12Zm0 5.25h.007v.008H3.75v-.008Z" />
                        </svg>
                        <span>Tasks</span>
                    </a>
                    <a href="{{ route('work-tasks.calendar') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                        <span>Calendar</span>
                    </a>
                    <a href="{{ route('work-tasks.accomplishments') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition text-slate-600 hover:text-slate-900">
                        <svg class="h-3.5 w-3.5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                        </svg>
                        <span>Accomplishments</span>
                    </a>
                    <a href="{{ route('work-notes.index') }}"
                       class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-md transition bg-white text-slate-900 shadow-xs">
                        <svg class="h-3.5 w-3.5 text-amber-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                        </svg>
                        <span>Notes</span>
                    </a>
                </div>

                {{-- Add Note Button --}}
                <button type="button" @click="openCreateModal()"
                        class="inline-flex items-center gap-1.5 rounded-lg bg-blue-600 px-3.5 py-2 text-xs font-semibold text-white shadow-sm hover:bg-blue-700 transition-colors cursor-pointer">
                    <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                    </svg>
                    <span>Add Note</span>
                </button>
            </div>
        </div>

        {{-- Toolbar: Search & Info --}}
        <div class="bg-white rounded-xl border border-slate-200 shadow-xs p-3.5">
            <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                <form method="GET" action="{{ route('work-notes.index') }}" class="flex items-center gap-2 flex-1 max-w-md">
                    <div class="relative w-full">
                        <input type="text" name="search" value="{{ request('search') }}"
                               placeholder="Search notes by subject or description..."
                               class="w-full rounded-lg border border-slate-300 text-xs px-3 py-1.5 pl-8 text-slate-800 placeholder:text-slate-400 focus:ring-2 focus:ring-blue-500">
                        <svg class="h-4 w-4 text-slate-400 absolute left-2.5 top-2" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
                        </svg>
                    </div>

                    @if(request('search'))
                        <a href="{{ route('work-notes.index') }}"
                           class="text-xs text-slate-500 hover:text-slate-800 underline px-1 shrink-0">
                            Clear
                        </a>
                    @endif
                </form>

                <div class="flex items-center gap-3 text-xs text-slate-500 font-medium">
                    @if($pinnedCount > 0)
                        <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full bg-amber-50 text-amber-800 border border-amber-200 font-bold">
                            <span>📌</span>
                            <span>{{ $pinnedCount }} {{ Str::plural('Pinned Reminder', $pinnedCount) }}</span>
                        </span>
                    @endif
                    <span>Total Notes: <strong>{{ $totalCount }}</strong></span>
                </div>
            </div>
        </div>

        {{-- Notes Grid --}}
        @if($notes->count() > 0)
            @php
                $pinnedNotes = $notes->filter(fn ($n) => $n->is_pinned);
                $regularNotes = $notes->filter(fn ($n) => !$n->is_pinned);
            @endphp

            <div class="space-y-6">
                {{-- ─── 1. Pinned Notes Section ───────────────────────────────────── --}}
                @if($pinnedNotes->count() > 0)
                    <div>
                        <div class="flex items-center gap-2 mb-3">
                            <span class="text-sm">📌</span>
                            <h3 class="text-xs font-bold text-amber-900 uppercase tracking-wider">
                                Pinned Reminders ({{ $pinnedNotes->count() }})
                            </h3>
                            <div class="flex-1 border-b border-amber-200/80"></div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($pinnedNotes as $note)
                                @php
                                    $style = $note->color_style;
                                    $noteJson = [
                                        'id' => $note->id,
                                        'title' => $note->title,
                                        'content' => $note->content,
                                        'color' => $note->color,
                                        'is_pinned' => $note->is_pinned,
                                    ];
                                @endphp
                                <div class="rounded-xl border shadow-xs p-4.5 flex flex-col justify-between transition-all hover:shadow-md {{ $style['card'] }}">
                                    {{-- Note Header: Tag, Subject & Pin Toggle --}}
                                    <div>
                                        <div class="flex items-start justify-between gap-3 mb-2.5 pb-2.5 border-b border-black/5">
                                            <div class="space-y-1 flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $style['badge'] }}">
                                                        {{ ucfirst($note->color) }} Note
                                                    </span>
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-100 text-amber-800 border border-amber-300">
                                                        📌 Pinned
                                                    </span>
                                                </div>
                                                <h4 class="text-sm font-bold leading-snug break-words {{ $style['header'] }}">
                                                    {{ $note->title }}
                                                </h4>
                                            </div>

                                            {{-- Pin Toggle Button with Text + Icon --}}
                                            <form method="POST" action="{{ route('work-notes.toggle-pin', $note) }}" class="inline shrink-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-semibold text-amber-800 bg-amber-100/90 hover:bg-amber-200 border border-amber-300 shadow-2xs transition cursor-pointer"
                                                        title="Unpin reminder">
                                                    <svg class="h-3.5 w-3.5 fill-amber-600 text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 2a.75.75 0 0 1 .75.75v3.586l2.121 2.121a.75.75 0 0 1 .22.53v.513a.75.75 0 0 1-.75.75H11.5v6a.75.75 0 0 1-1.5 0v-6H7.659a.75.75 0 0 1-.75-.75v-.514a.75.75 0 0 1 .22-.53L9.25 6.336V2.75A.75.75 0 0 1 10 2Z" />
                                                    </svg>
                                                    <span>Unpin</span>
                                                </button>
                                            </form>
                                        </div>

                                        {{-- Note Body: Description Box (no indentation, crisp layout) --}}
                                        <div class="rounded-lg bg-white/80 border border-black/5 p-3 text-xs text-slate-800 leading-relaxed whitespace-pre-line break-words min-h-[64px] font-normal select-text">{{ trim($note->content ?: 'No additional description.') }}</div>
                                    </div>

                                    {{-- Note Footer: Timestamp & Actions WITH TEXT --}}
                                    <div class="mt-3.5 pt-2.5 border-t border-black/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-[11px] text-slate-500">
                                        <div class="flex items-center gap-1.5 text-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <span>Updated {{ $note->updated_at->diffForHumans() }}</span>
                                        </div>

                                        <div class="flex items-center gap-1.5 self-end sm:self-auto">
                                            {{-- Copy Button --}}
                                            <button type="button"
                                                    @click="copyNote({{ $note->id }}, '{{ addslashes($note->title) }}', '{{ addslashes($note->content ?? '') }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-slate-700 hover:text-slate-900 hover:bg-slate-50 hover:border-slate-300 shadow-2xs transition cursor-pointer"
                                                    title="Copy note text">
                                                <span x-show="copiedId !== {{ $note->id }}" class="inline-flex items-center gap-1">
                                                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    <span>Copy</span>
                                                </span>
                                                <span x-show="copiedId === {{ $note->id }}" class="inline-flex items-center gap-1 text-emerald-600 font-semibold" style="display: none;">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>Copied!</span>
                                                </span>
                                            </button>

                                            {{-- Edit Button --}}
                                            <button type="button"
                                                    @click="openEditModal({{ json_encode($noteJson) }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-blue-600 hover:text-blue-700 hover:bg-blue-50 hover:border-blue-200 shadow-2xs transition cursor-pointer"
                                                    title="Edit note">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                                <span>Edit</span>
                                            </button>

                                            {{-- Delete Button --}}
                                            <button type="button"
                                                    @click="openDeleteModal({{ $note->id }}, '{{ addslashes($note->title) }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-rose-600 hover:text-rose-700 hover:bg-rose-50 hover:border-rose-200 shadow-2xs transition cursor-pointer"
                                                    title="Delete note">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                {{-- ─── 2. Regular Notes Section ──────────────────────────────────── --}}
                @if($regularNotes->count() > 0)
                    <div>
                        @if($pinnedNotes->count() > 0)
                            <div class="flex items-center gap-2 mb-3">
                                <h3 class="text-xs font-bold text-slate-500 uppercase tracking-wider">
                                    Other Notes &amp; Memos ({{ $regularNotes->count() }})
                                </h3>
                                <div class="flex-1 border-b border-slate-200"></div>
                            </div>
                        @endif

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
                            @foreach($regularNotes as $note)
                                @php
                                    $style = $note->color_style;
                                    $noteJson = [
                                        'id' => $note->id,
                                        'title' => $note->title,
                                        'content' => $note->content,
                                        'color' => $note->color,
                                        'is_pinned' => $note->is_pinned,
                                    ];
                                @endphp
                                <div class="rounded-xl border shadow-xs p-4.5 flex flex-col justify-between transition-all hover:shadow-md {{ $style['card'] }}">
                                    {{-- Note Header: Tag, Subject & Pin Toggle --}}
                                    <div>
                                        <div class="flex items-start justify-between gap-3 mb-2.5 pb-2.5 border-b border-black/5">
                                            <div class="space-y-1 flex-1 min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider {{ $style['badge'] }}">
                                                        {{ ucfirst($note->color) }} Note
                                                    </span>
                                                </div>
                                                <h4 class="text-sm font-bold leading-snug break-words {{ $style['header'] }}">
                                                    {{ $note->title }}
                                                </h4>
                                            </div>

                                            {{-- Pin Toggle Button with Text + Icon --}}
                                            <form method="POST" action="{{ route('work-notes.toggle-pin', $note) }}" class="inline shrink-0">
                                                @csrf
                                                @method('PATCH')
                                                <button type="submit"
                                                        class="inline-flex items-center gap-1 px-2 py-1 rounded-md text-[11px] font-medium text-slate-500 hover:text-amber-800 bg-white/80 hover:bg-amber-50 border border-slate-200 hover:border-amber-300 shadow-2xs transition cursor-pointer"
                                                        title="Pin reminder to top">
                                                    <svg class="h-3.5 w-3.5 text-slate-400 group-hover:text-amber-600" viewBox="0 0 20 20" fill="currentColor">
                                                        <path d="M10 2a.75.75 0 0 1 .75.75v3.586l2.121 2.121a.75.75 0 0 1 .22.53v.513a.75.75 0 0 1-.75.75H11.5v6a.75.75 0 0 1-1.5 0v-6H7.659a.75.75 0 0 1-.75-.75v-.514a.75.75 0 0 1 .22-.53L9.25 6.336V2.75A.75.75 0 0 1 10 2Z" />
                                                    </svg>
                                                    <span>Pin</span>
                                                </button>
                                            </form>
                                        </div>

                                        {{-- Note Body: Description Box --}}
                                        <div class="rounded-lg bg-white/80 border border-black/5 p-3 text-xs text-slate-800 leading-relaxed whitespace-pre-line break-words min-h-[64px] font-normal select-text">{{ trim($note->content ?: 'No additional description.') }}</div>
                                    </div>

                                    {{-- Note Footer: Timestamp & Actions WITH TEXT --}}
                                    <div class="mt-3.5 pt-2.5 border-t border-black/5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 text-[11px] text-slate-500">
                                        <div class="flex items-center gap-1.5 text-slate-400">
                                            <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                                            </svg>
                                            <span>Updated {{ $note->updated_at->diffForHumans() }}</span>
                                        </div>

                                        <div class="flex items-center gap-1.5 self-end sm:self-auto">
                                            {{-- Copy Button --}}
                                            <button type="button"
                                                    @click="copyNote({{ $note->id }}, '{{ addslashes($note->title) }}', '{{ addslashes($note->content ?? '') }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-slate-700 hover:text-slate-900 hover:bg-slate-50 hover:border-slate-300 shadow-2xs transition cursor-pointer"
                                                    title="Copy note text">
                                                <span x-show="copiedId !== {{ $note->id }}" class="inline-flex items-center gap-1">
                                                    <svg class="h-3.5 w-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 17.25v3.375c0 .621-.504 1.125-1.125 1.125h-9.75a1.125 1.125 0 0 1-1.125-1.125V7.875c0-.621.504-1.125 1.125-1.125H6.75a9.06 9.06 0 0 1 1.5.124m7.5 10.376h3.375c.621 0 1.125-.504 1.125-1.125V11.25c0-4.46-3.243-8.161-7.5-8.876a9.06 9.06 0 0 0-1.5-.124H9.375c-.621 0-1.125.504-1.125 1.125v3.5m7.5 10.375H9.375a1.125 1.125 0 0 1-1.125-1.125v-9.25m12 6.625v-1.875a3.375 3.375 0 0 0-3.375-3.375h-1.5a1.125 1.125 0 0 1-1.125-1.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H9.75" />
                                                    </svg>
                                                    <span>Copy</span>
                                                </span>
                                                <span x-show="copiedId === {{ $note->id }}" class="inline-flex items-center gap-1 text-emerald-600 font-semibold" style="display: none;">
                                                    <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                                                        <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                                    </svg>
                                                    <span>Copied!</span>
                                                </span>
                                            </button>

                                            {{-- Edit Button --}}
                                            <button type="button"
                                                    @click="openEditModal({{ json_encode($noteJson) }})"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-blue-600 hover:text-blue-700 hover:bg-blue-50 hover:border-blue-200 shadow-2xs transition cursor-pointer"
                                                    title="Edit note">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                                                </svg>
                                                <span>Edit</span>
                                            </button>

                                            {{-- Delete Button --}}
                                            <button type="button"
                                                    @click="openDeleteModal({{ $note->id }}, '{{ addslashes($note->title) }}')"
                                                    class="inline-flex items-center gap-1 px-2.5 py-1 rounded-md text-xs font-medium bg-white border border-slate-200 text-rose-600 hover:text-rose-700 hover:bg-rose-50 hover:border-rose-200 shadow-2xs transition cursor-pointer"
                                                    title="Delete note">
                                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                                    <path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
                                                </svg>
                                                <span>Delete</span>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            @endforeach

                            {{-- Quick Add Note Dashed Card --}}
                            <button type="button" @click="openCreateModal()"
                                    class="rounded-xl border-2 border-dashed border-slate-300 hover:border-blue-400 hover:bg-blue-50/20 p-5 flex flex-col items-center justify-center text-center transition group min-h-[170px] cursor-pointer">
                                <div class="h-10 w-10 rounded-full bg-slate-100 group-hover:bg-blue-100 flex items-center justify-center text-slate-400 group-hover:text-blue-600 transition mb-2">
                                    <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                                    </svg>
                                </div>
                                <span class="text-xs font-semibold text-slate-700 group-hover:text-blue-700 transition">Add Another Note</span>
                                <span class="text-[11px] text-slate-400 mt-0.5">Quick memo, shift reminder, or scratchpad</span>
                            </button>
                        </div>
                    </div>
                @elseif($pinnedNotes->count() > 0)
                    {{-- If only pinned notes exist, show the dashed quick add button too --}}
                    <div class="pt-2">
                        <button type="button" @click="openCreateModal()"
                                class="w-full sm:w-auto inline-flex items-center gap-2 px-4 py-2.5 rounded-xl border border-dashed border-slate-300 hover:border-blue-400 bg-white hover:bg-blue-50/20 text-xs font-semibold text-slate-600 hover:text-blue-700 transition cursor-pointer">
                            <svg class="h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 4.5v15m7.5-7.5h-15" />
                            </svg>
                            <span>Add Another Note</span>
                        </button>
                    </div>
                @endif
            </div>

            {{-- Pagination --}}
            <div class="mt-6">
                {{ $notes->links() }}
            </div>
        @else
            {{-- Empty State --}}
            <div class="rounded-2xl border border-dashed border-slate-300 bg-white p-12 text-center shadow-xs">
                <div class="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-amber-50 text-amber-600 mb-3 border border-amber-200">
                    <svg class="h-7 w-7" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" />
                    </svg>
                </div>
                <h3 class="text-base font-bold text-slate-900">
                    {{ request('search') ? 'No notes matched your search' : 'No notes added yet' }}
                </h3>
                <p class="text-xs text-slate-500 max-w-sm mx-auto mt-1">
                    {{ request('search') ? 'Try searching with a different keyword.' : 'Quickly jot down operational shift memos, tech numbers, formulas, and reminders with just a Subject & Description.' }}
                </p>
                <div class="mt-4">
                    @if(request('search'))
                        <a href="{{ route('work-notes.index') }}"
                           class="inline-flex items-center gap-1.5 px-4 py-2 bg-slate-100 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-200 transition shadow-xs">
                            Clear Search
                        </a>
                    @else
                        <button type="button" @click="openCreateModal()"
                                class="inline-flex items-center gap-1.5 px-4 py-2 bg-blue-600 text-white text-xs font-semibold rounded-lg hover:bg-blue-700 transition shadow-xs cursor-pointer">
                            + Add Your First Note
                        </button>
                    @endif
                </div>
            </div>
        @endif

        {{-- ─── MODAL 1: Create Note ─────────────────────────────────────────────── --}}
        <div x-show="showCreateModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showCreateModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg border border-slate-200 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Add Operational Note</h3>
                        <p class="text-xs text-slate-400 mt-0.5">Subject &amp; description only &mdash; no deadlines or calendar required</p>
                    </div>
                    <button type="button" @click="showCreateModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" action="{{ route('work-notes.store') }}" @submit="submitting = true">
                    @csrf

                    <div class="p-5 space-y-3.5">
                        {{-- Subject (Required) --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Subject <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" x-model="newTitle" x-ref="createSubjectInput" required
                                   placeholder="e.g. Driver Handover Notes, Technician Hotline, GPS Server IP..."
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Description (Text/Reminder) --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Description / Memo Text
                            </label>
                            <textarea name="content" rows="5" x-model="newContent"
                                      placeholder="Type your operational text, formula, contact details, or checklist reminders here..."
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 placeholder:text-slate-400 focus:outline-none focus:ring-2 focus:ring-blue-500 leading-relaxed"></textarea>
                        </div>

                        {{-- Options: Color and Pin --}}
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-4 flex-wrap">
                            {{-- Color Accent Picker --}}
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 font-medium">Color:</span>
                                <div class="flex items-center gap-1.5">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="slate" x-model="newColor" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-slate-200 border-2 transition"
                                              :class="newColor === 'slate' ? 'ring-2 ring-slate-600 border-white' : 'border-slate-300'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="blue" x-model="newColor" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-blue-300 border-2 transition"
                                              :class="newColor === 'blue' ? 'ring-2 ring-blue-600 border-white' : 'border-blue-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="amber" x-model="newColor" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-amber-300 border-2 transition"
                                              :class="newColor === 'amber' ? 'ring-2 ring-amber-600 border-white' : 'border-amber-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="emerald" x-model="newColor" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-emerald-300 border-2 transition"
                                              :class="newColor === 'emerald' ? 'ring-2 ring-emerald-600 border-white' : 'border-emerald-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="rose" x-model="newColor" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-rose-300 border-2 transition"
                                              :class="newColor === 'rose' ? 'ring-2 ring-rose-600 border-white' : 'border-rose-400'"></span>
                                    </label>
                                </div>
                            </div>

                            {{-- Pin to Top Checkbox --}}
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="is_pinned" value="1" x-model="newIsPinned"
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>📌 Pin to top of board</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                        <button type="button" @click="showCreateModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting || !newTitle.trim()"
                                :class="(submitting || !newTitle.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                class="px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg shadow-xs cursor-pointer">
                            <span x-show="!submitting">Save Note</span>
                            <span x-show="submitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ─── MODAL 2: Edit Note ───────────────────────────────────────────────── --}}
        <div x-show="showEditModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showEditModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-xl w-full max-w-lg border border-slate-200 overflow-hidden" @click.stop>
                <div class="flex items-center justify-between px-5 py-3.5 border-b border-slate-100 bg-slate-50/50">
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Edit Note</h3>
                        <p class="text-xs text-slate-400 mt-0.5 truncate max-w-[280px]" x-text="editNote.title"></p>
                    </div>
                    <button type="button" @click="showEditModal = false" class="text-slate-400 hover:text-slate-600 text-sm p-1 rounded">✕</button>
                </div>

                <form method="POST" :action="editNote.url" @submit="submitting = true">
                    @csrf
                    @method('PATCH')

                    <div class="p-5 space-y-3.5">
                        {{-- Subject (Required) --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Subject <span class="text-red-500">*</span>
                            </label>
                            <input type="text" name="title" x-model="editNote.title" x-ref="editSubjectInput" required
                                   class="w-full px-3 py-2 border border-slate-300 rounded-lg text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500">
                        </div>

                        {{-- Description --}}
                        <div>
                            <label class="block text-xs font-semibold text-slate-700 uppercase tracking-wider mb-1">
                                Description / Memo Text
                            </label>
                            <textarea name="content" rows="6" x-model="editNote.content"
                                      class="w-full px-3 py-2 border border-slate-300 rounded-lg text-xs text-slate-800 focus:outline-none focus:ring-2 focus:ring-blue-500 leading-relaxed"></textarea>
                        </div>

                        {{-- Options: Color and Pin --}}
                        <div class="pt-2 border-t border-slate-100 flex items-center justify-between gap-4 flex-wrap">
                            {{-- Color Accent Picker --}}
                            <div class="flex items-center gap-2">
                                <span class="text-xs text-slate-500 font-medium">Color:</span>
                                <div class="flex items-center gap-1.5">
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="slate" x-model="editNote.color" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-slate-200 border-2 transition"
                                              :class="editNote.color === 'slate' ? 'ring-2 ring-slate-600 border-white' : 'border-slate-300'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="blue" x-model="editNote.color" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-blue-300 border-2 transition"
                                              :class="editNote.color === 'blue' ? 'ring-2 ring-blue-600 border-white' : 'border-blue-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="amber" x-model="editNote.color" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-amber-300 border-2 transition"
                                              :class="editNote.color === 'amber' ? 'ring-2 ring-amber-600 border-white' : 'border-amber-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="emerald" x-model="editNote.color" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-emerald-300 border-2 transition"
                                              :class="editNote.color === 'emerald' ? 'ring-2 ring-emerald-600 border-white' : 'border-emerald-400'"></span>
                                    </label>
                                    <label class="cursor-pointer">
                                        <input type="radio" name="color" value="rose" x-model="editNote.color" class="sr-only">
                                        <span class="inline-block h-5 w-5 rounded-full bg-rose-300 border-2 transition"
                                              :class="editNote.color === 'rose' ? 'ring-2 ring-rose-600 border-white' : 'border-rose-400'"></span>
                                    </label>
                                </div>
                            </div>

                            {{-- Pin to Top Checkbox --}}
                            <label class="inline-flex items-center gap-2 cursor-pointer text-xs font-semibold text-slate-700">
                                <input type="checkbox" name="is_pinned" value="1" x-model="editNote.is_pinned"
                                       class="rounded border-slate-300 text-blue-600 focus:ring-blue-500">
                                <span>📌 Pin to top of board</span>
                            </label>
                        </div>
                    </div>

                    <div class="flex items-center justify-end gap-2 px-5 py-3.5 border-t border-slate-100 bg-slate-50">
                        <button type="button" @click="showEditModal = false"
                                class="px-3.5 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                            Cancel
                        </button>
                        <button type="submit" :disabled="submitting || !editNote.title.trim()"
                                :class="(submitting || !editNote.title.trim()) ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700'"
                                class="px-4 py-1.5 bg-blue-600 text-white text-xs font-semibold rounded-lg shadow-xs cursor-pointer">
                            <span x-show="!submitting">Save Changes</span>
                            <span x-show="submitting">Saving...</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>

        {{-- ─── MODAL 3: Delete Confirmation ─────────────────────────────────────── --}}
        <div x-show="showDeleteModal"
             x-transition:enter="ease-out duration-150"
             x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100"
             x-transition:leave="ease-in duration-100"
             x-transition:leave-start="opacity-100"
             x-transition:leave-end="opacity-0"
             class="fixed inset-0 z-50 flex items-center justify-center px-4"
             style="display: none;">
            <div class="absolute inset-0 bg-slate-900/40 backdrop-blur-2xs" @click="showDeleteModal = false"></div>
            <div class="relative bg-white rounded-xl shadow-lg p-5 w-full max-w-sm border border-slate-200">
                <div class="flex items-center gap-3 mb-3">
                    <div class="h-9 w-9 rounded-full bg-red-100 flex items-center justify-center shrink-0">
                        <svg class="h-5 w-5 text-red-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                        </svg>
                    </div>
                    <div>
                        <h3 class="text-sm font-bold text-slate-800">Delete Operational Note</h3>
                        <p class="text-xs text-slate-500 truncate max-w-[200px]" x-text="deleteNote.title"></p>
                    </div>
                </div>
                <p class="text-xs text-slate-600 mb-4">Are you sure? This note will be permanently removed.</p>
                <div class="flex items-center justify-end gap-2">
                    <button type="button" @click="showDeleteModal = false"
                            class="px-3 py-1.5 bg-white border border-slate-300 text-slate-700 text-xs font-semibold rounded-lg hover:bg-slate-50">
                        Cancel
                    </button>
                    <form method="POST" :action="deleteNote.url" class="inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit"
                                class="px-3.5 py-1.5 bg-red-600 text-white text-xs font-semibold rounded-lg hover:bg-red-700 cursor-pointer shadow-xs">
                            Delete
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</x-app-layout>
