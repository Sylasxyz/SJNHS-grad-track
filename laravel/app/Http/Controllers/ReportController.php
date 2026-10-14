<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Services\GraduateAnalytics;
use App\Services\ReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generate Reports, ported from admin/reports.php: snapshot trends, saved reports,
 * view one report, CSV export, generate and delete. Permissions live in ReportPolicy.
 */
class ReportController extends Controller
{
    public function index(Request $request, ReportBuilder $reports): View|RedirectResponse
    {
        // Old-style links (admin/reports?view=ID, ?export=ID) go to their own routes.
        if ((int) $request->query('export') > 0) {
            return redirect()->route('reports.export', (int) $request->query('export'));
        }
        if ((int) $request->query('view') > 0) {
            return redirect()->route('reports.show', (int) $request->query('view'));
        }

        return $this->page($request, $reports);
    }

    public function show(Request $request, Report $report, ReportBuilder $reports): View
    {
        return $this->page($request, $reports, $report);
    }

    public function export(Report $report): StreamedResponse
    {
        return response()->streamDownload(function () use ($report) {
            $out = fopen('php://output', 'w');
            ReportBuilder::writeCsv($report, $out);
            fclose($out);
        }, ReportBuilder::csvFilename($report), ['Content-Type' => 'text/csv; charset=utf-8']);
    }

    public function store(Request $request, ReportBuilder $reports): RedirectResponse
    {
        $data = $request->validate([
            'report_name' => ['nullable', 'string', 'max:130'],
            'batch_from' => ['nullable', 'integer', 'between:0,9999'],
            'batch_to' => ['nullable', 'integer', 'between:0,9999'],
            'employment_status' => ['nullable', 'string'],
        ], [
            'report_name.*' => 'Report name is too long. Maximum 130 characters only.',
            'batch_from.*' => 'Batch from is invalid.',
            'batch_to.*' => 'Batch to is invalid.',
        ]);

        $reportName = trim((string) ($data['report_name'] ?? ''));
        if ($reportName === '') {
            $reportName = 'Analytics Report';
        }
        $batchFrom = (int) ($data['batch_from'] ?? 0);
        $batchTo = (int) ($data['batch_to'] ?? 0);
        $employmentFilter = trim((string) ($data['employment_status'] ?? 'all'));
        if (! in_array($employmentFilter, ReportBuilder::FILTER_OPTIONS, true)) {
            $employmentFilter = 'all';
        }
        $separateByStatus = $request->input('separate_by_status') === '1';

        try {
            if ($separateByStatus && $employmentFilter === 'all') {
                $created = DB::transaction(function () use ($reports, $reportName, $batchFrom, $batchTo) {
                    foreach (GraduateAnalytics::STATUS_ORDER as $status) {
                        $reports->generate($reportName.' — '.$status, $batchFrom, $batchTo, $status);
                    }

                    return count(GraduateAnalytics::STATUS_ORDER);
                });
                $message = "Generated {$created} separate reports (one per employment status).";
            } else {
                $filterValue = $employmentFilter === 'all' ? '' : $employmentFilter;
                $label = GraduateAnalytics::filterLabel($filterValue);
                if ($label !== 'All Status' && stripos($reportName, $label) === false) {
                    $reportName .= ' — '.$label;
                }
                $reports->generate($reportName, $batchFrom, $batchTo, $filterValue);
                $message = 'Report generated successfully ('.$label.').';
            }
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->with('error', 'Could not generate report.');
        }

        // Like the legacy page, only the batch range stays filled in after generating.
        return back()->withInput($request->only('batch_from', 'batch_to'))->with('success', $message);
    }

    public function destroy(Report $report): RedirectResponse
    {
        $report->delete();

        return redirect()->route('reports')->with('success', 'Report deleted.');
    }

    private function page(Request $request, ReportBuilder $reports, ?Report $viewReport = null): View
    {
        return view('reports.index', [
            'reports' => $reports->saved(50),
            'snapshotTrends' => $reports->snapshotTrends(12),
            'viewReport' => $viewReport,
            'batchFrom' => (int) old('batch_from', $request->query('batch_from', 0)),
            'batchTo' => (int) old('batch_to', $request->query('batch_to', 0)),
        ]);
    }
}
