<?php

namespace App\Http\Controllers;

use App\Exports\LongIdlingExport;
use App\Exports\LongIdlingTemplateExport;
use App\Http\Requests\StoreReportRequest;
use App\Http\Requests\UpdateReportRequest;
use App\Models\LongIdlingRecord;
use App\Models\Report;
use App\Services\LongIdlingImportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Maatwebsite\Excel\Facades\Excel;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $query = Report::where('created_by', auth()->id())
            ->where('report_type', 'long_idling')
            ->withCount('longIdlingRecords')
            ->orderByDesc('report_date');

        if ($request->filled('search')) {
            $query->where('report_date', 'like', '%'.$request->search.'%');
        }
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $reports = $query->paginate(15)->withQueryString();

        return view('reports.index', compact('reports'));
    }

    public function create(): View
    {
        $previousReport = Report::where('created_by', auth()->id())
            ->where('report_type', 'long_idling')
            ->orderByDesc('report_date')
            ->first();

        return view('reports.create', compact('previousReport'));
    }

    public function store(StoreReportRequest $request): RedirectResponse
    {
        $report = Report::create([
            'report_type' => 'long_idling',
            'report_date' => $request->report_date,
            'remarks' => $request->remarks,
            'status' => 'draft',
            'created_by' => auth()->id(),
        ]);

        if ($request->filled('duplicate_from')) {
            $source = Report::with('longIdlingRecords')->find($request->duplicate_from);
            if ($source && $source->created_by === auth()->id()) {
                foreach ($source->longIdlingRecords as $index => $record) {
                    LongIdlingRecord::create([
                        'report_id' => $report->id,
                        'device_name' => $record->device_name,
                        'imei' => $record->imei,
                        'model' => $record->model,
                        'sort_order' => $index,
                    ]);
                }
            }
        }

        return redirect()->route('reports.show', $report)
            ->with('success', 'Report created successfully. Add your Long Idling records below.');
    }

    public function show(Report $report): View
    {
        $this->authorizeReport($report);

        $report->load('longIdlingRecords');

        return view('reports.show', compact('report'));
    }

    public function edit(Report $report): View
    {
        $this->authorizeReport($report);

        return view('reports.edit', compact('report'));
    }

    public function update(UpdateReportRequest $request, Report $report): RedirectResponse
    {
        $this->authorizeReport($report);

        $report->update([
            'report_date' => $request->report_date,
            'remarks' => $request->remarks,
        ]);

        return redirect()->route('reports.show', $report)
            ->with('success', 'Report updated successfully.');
    }

    public function destroy(Report $report): RedirectResponse
    {
        $this->authorizeReport($report);

        $report->longIdlingRecords->each(function ($record) {
            if ($record->image) {
                Storage::disk('public')->delete($record->image);
            }
        });

        $report->delete();

        return redirect()->route('reports.index')
            ->with('success', 'Report deleted successfully.');
    }

    public function markComplete(Report $report): RedirectResponse
    {
        $this->authorizeReport($report);

        $report->update(['status' => 'completed']);

        return back()->with('success', 'Report marked as completed.');
    }

    public function markDraft(Report $report): RedirectResponse
    {
        $this->authorizeReport($report);

        $report->update(['status' => 'draft']);

        return back()->with('success', 'Report reverted to draft.');
    }

    public function rangeView(Request $request): View
    {
        $userId = auth()->id();
        $reports = Report::where('created_by', $userId)
            ->where('report_type', 'long_idling')
            ->orderByDesc('report_date')
            ->get();

        $latestDate = $reports->first()?->report_date?->format('Y-m-d') ?? now()->toDateString();
        $earliestDate = $reports->count() > 1 ? $reports->slice(0, 3)->last()?->report_date?->format('Y-m-d') : $latestDate;

        $startDate = $request->query('start_date', $earliestDate);
        $endDate = $request->query('end_date', $latestDate);

        if ($startDate > $endDate) {
            [$startDate, $endDate] = [$endDate, $startDate];
        }

        return view('reports.range', compact('startDate', 'endDate', 'reports'));
    }

    public function generateRangePdf(Request $request)
    {
        return $this->generatePdf(null);
    }

    public function generatePdf(?Report $report = null)
    {
        if ($report) {
            $this->authorizeReport($report);
        }

        ini_set('memory_limit', '512M');
        set_time_limit(180);

        $userId = auth()->id();
        $sort = request()->input('sort');
        $search = trim((string) request()->input('search', ''));
        $device = trim((string) request()->input('device', ''));
        $idsInput = request()->input('ids');
        $startDate = request()->input('start_date');
        $endDate = request()->input('end_date');

        $ids = [];
        if (! empty($idsInput)) {
            if (is_string($idsInput)) {
                $ids = array_filter(array_map('intval', explode(',', $idsInput)));
            } elseif (is_array($idsInput)) {
                $ids = array_filter(array_map('intval', $idsInput));
            }
        }

        $filterLabel = null;
        $suffix = '';

        if (! empty($ids)) {
            $recordsQuery = LongIdlingRecord::whereIn('id', $ids)
                ->whereHas('report', fn ($q) => $q->where('created_by', $userId))
                ->with('report');

            $count = count($ids);
            $filterLabel = "Selected ({$count} ".Str::plural('record', $count).')';
            $suffix = "-Selected-{$count}";
        } elseif ($startDate && $endDate) {
            $start = min($startDate, $endDate);
            $end = max($startDate, $endDate);

            $recordsQuery = LongIdlingRecord::whereHas('report', function ($q) use ($userId, $start, $end) {
                $q->where('created_by', $userId)
                    ->where('report_type', 'long_idling')
                    ->whereDate('report_date', '>=', $start)
                    ->whereDate('report_date', '<=', $end);
            })->with('report');

            if ($device && $device !== 'all') {
                $recordsQuery->where('device_name', $device);
                $filterLabel = "Device: {$device}";
                $cleanDevice = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $device));
                $suffix = "-{$cleanDevice}";
            }

            if ($search) {
                $q = $search;
                $recordsQuery->where(function ($query) use ($q) {
                    $query->where('device_name', 'like', "%{$q}%")
                        ->orWhere('imei', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('driver_name', 'like', "%{$q}%");
                });

                $filterLabel = $filterLabel ? "{$filterLabel}, Search: \"{$search}\"" : "Search: \"{$search}\"";
                if (empty($suffix)) {
                    $cleanSearch = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $search));
                    $suffix = "-{$cleanSearch}";
                }
            }
        } elseif ($report) {
            $recordsQuery = $report->longIdlingRecords()->with('report');

            if ($device && $device !== 'all') {
                $recordsQuery->where('device_name', $device);
                $filterLabel = "Device: {$device}";
                $cleanDevice = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $device));
                $suffix = "-{$cleanDevice}";
            }

            if ($search) {
                $q = $search;
                $recordsQuery->where(function ($query) use ($q) {
                    $query->where('device_name', 'like', "%{$q}%")
                        ->orWhere('imei', 'like', "%{$q}%")
                        ->orWhere('address', 'like', "%{$q}%")
                        ->orWhere('model', 'like', "%{$q}%")
                        ->orWhere('driver_name', 'like', "%{$q}%");
                });

                $filterLabel = $filterLabel ? "{$filterLabel}, Search: \"{$search}\"" : "Search: \"{$search}\"";
                if (empty($suffix)) {
                    $cleanSearch = preg_replace('/[^A-Za-z0-9\-]/', '', str_replace(' ', '-', $search));
                    $suffix = "-{$cleanSearch}";
                }
            }
        } else {
            abort(404, 'No report or date range specified.');
        }

        if ($sort === 'stay_time_desc') {
            $recordsQuery->orderByDesc('stay_time');
        } elseif ($sort === 'stay_time_asc') {
            $recordsQuery->orderBy('stay_time');
        } elseif ($sort === 'device_asc') {
            $recordsQuery->orderBy('device_name');
        } elseif ($sort === 'device_desc') {
            $recordsQuery->orderByDesc('device_name');
        } else {
            $recordsQuery->orderBy('sort_order')->orderBy('id');
        }

        $records = $recordsQuery->get();

        $dates = $records->map(fn ($r) => $r->report?->report_date)->filter()->unique()->sort();
        $isDateRangeRequested = ($startDate && $endDate && $startDate !== $endDate);
        $isMultiDate = $dates->count() > 1 || $isDateRangeRequested;

        if ($isDateRangeRequested) {
            $firstDate = Carbon::parse(min($startDate, $endDate));
            $lastDate = Carbon::parse(max($startDate, $endDate));
            $dateRangeLabel = $firstDate->format('M j, Y').' – '.$lastDate->format('M j, Y');
            $dateRangeSubtitle = $firstDate->format('F j, Y').' &mdash; '.$lastDate->format('F j, Y');
            $fileDatePrefix = $firstDate->format('Y-m-d').'-to-'.$lastDate->format('Y-m-d');
        } elseif ($dates->count() > 1) {
            $firstDate = $dates->first();
            $lastDate = $dates->last();
            $dateRangeLabel = $firstDate->format('M j, Y').' – '.$lastDate->format('M j, Y');
            $dateRangeSubtitle = $firstDate->format('F j, Y').' &mdash; '.$lastDate->format('F j, Y');
            $fileDatePrefix = $firstDate->format('Y-m-d').'-to-'.$lastDate->format('Y-m-d');
        } elseif ($dates->isNotEmpty()) {
            $singleDate = $dates->first();
            $dateRangeLabel = $singleDate->format('M j, Y');
            $dateRangeSubtitle = $singleDate->format('l, F j, Y');
            $fileDatePrefix = $singleDate->format('Y-m-d');
        } else {
            $fallback = $report?->report_date ?? now();
            $dateRangeLabel = $fallback->format('M j, Y');
            $dateRangeSubtitle = $fallback->format('l, F j, Y');
            $fileDatePrefix = $fallback->format('Y-m-d');
        }

        $reportModel = $report ?? new Report([
            'report_type' => 'long_idling',
            'report_date' => $dates->first() ?? now(),
            'status' => 'completed',
            'created_by' => $userId,
        ]);
        $reportModel->setRelation('longIdlingRecords', $records);

        $pdf = Pdf::loadView('pdf.long-idling-report', [
            'report' => $reportModel,
            'company' => config('foms.company_name'),
            'tagline' => config('foms.company_tagline'),
            'preparedBy' => config('foms.prepared_by'),
            'searchFilter' => $search,
            'filterLabel' => $filterLabel,
            'deviceFilter' => $device,
            'isMultiDate' => $isMultiDate,
            'dateRangeLabel' => $dateRangeLabel,
            'dateRangeSubtitle' => $dateRangeSubtitle,
        ])
            ->setPaper('a4', 'portrait')
            ->setOption('isRemoteEnabled', false)
            ->setOption('isHtml5ParserEnabled', true)
            ->setOption('isFontSubsettingEnabled', true);

        $filename = 'Long-Idling-Report-'.$fileDatePrefix.$suffix.'.pdf';

        return $pdf->download($filename);
    }

    public function exportExcel(Report $report)
    {
        $this->authorizeReport($report);

        $sort = request()->query('sort');
        $filename = 'Long-Idling-Report-'.$report->report_date->format('Y-m-d').'.xlsx';

        return Excel::download(new LongIdlingExport($report, $sort), $filename);
    }

    public function downloadTemplate()
    {
        return Excel::download(new LongIdlingTemplateExport, 'Long-Idling-Import-Template.xlsx');
    }

    public function importData(Request $request, Report $report, LongIdlingImportService $importService): RedirectResponse
    {
        $this->authorizeReport($report);

        $request->validate([
            'file' => ['required', 'file', 'max:10240'],
            'mode' => ['nullable', 'string', 'in:append,replace'],
        ]);

        $file = $request->file('file');
        $ext = strtolower($file->getClientOriginalExtension());
        if (! in_array($ext, ['xlsx', 'xls', 'csv', 'txt'], true)) {
            return redirect()->route('reports.show', $report)
                ->with('error', 'Invalid file type. Please upload an Excel (.xlsx, .xls) or CSV (.csv) file.');
        }

        $mode = $request->input('mode', 'append');
        $result = $importService->import($report, $file, $mode);

        if ($result['success'] && $result['count'] > 0) {
            return redirect()->route('reports.show', $report)
                ->with('success', $result['message']);
        }

        return redirect()->route('reports.show', $report)
            ->with('error', $result['message']);
    }

    private function authorizeReport(Report $report): void
    {
        if ($report->created_by !== auth()->id()) {
            abort(403);
        }
    }
}
