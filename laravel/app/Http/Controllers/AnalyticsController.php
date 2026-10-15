<?php

namespace App\Http\Controllers;

use App\Services\GraduateAnalytics;
use App\Services\ReportBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Analytics & Decision Support, ported from analytics.php: KPIs and charts for an
 * optional batch range, plus "Save snapshot", which stores a report in Reports.
 */
class AnalyticsController extends Controller
{
    public function index(Request $request, GraduateAnalytics $analytics, ReportBuilder $reports): View
    {
        $batchFrom = max(0, (int) $request->query('batch_from', 0));
        $batchTo = max(0, (int) $request->query('batch_to', 0));

        $data = null;
        $analyticsError = '';
        try {
            $data = $analytics->compute($batchFrom, $batchTo);
        } catch (Throwable $e) {
            report($e);
            $analyticsError = 'Unable to load analytics right now. Please try again after confirming the database is up to date.';
        }

        try {
            $unverified = $analytics->awaitingVerification($batchFrom, $batchTo);
        } catch (Throwable $e) {
            report($e);
            $unverified = [];
        }

        try {
            $snapshotTrends = $reports->snapshotTrends(12);
        } catch (Throwable $e) {
            report($e);
            $snapshotTrends = [];
        }

        $statusOrder = $data['status_order'] ?? GraduateAnalytics::STATUS_ORDER;

        return view('analytics.index', [
            'analytics' => $data,
            'analyticsError' => $analyticsError,
            'statusOrder' => $statusOrder,
            'countsByStatus' => $data['counts_by_status'] ?? array_fill_keys($statusOrder, 0),
            'unverified' => $unverified,
            'snapshotTrends' => $snapshotTrends,
            'batchFrom' => $batchFrom,
            'batchTo' => $batchTo,
        ]);
    }

    public function storeSnapshot(Request $request, GraduateAnalytics $analytics, ReportBuilder $reports): RedirectResponse
    {
        $data = $request->validate([
            'batch_from' => ['nullable', 'integer', 'between:0,9999'],
            'batch_to' => ['nullable', 'integer', 'between:0,9999'],
        ], [
            'batch_from.*' => 'Batch from is invalid.',
            'batch_to.*' => 'Batch to is invalid.',
        ]);
        $batchFrom = (int) ($data['batch_from'] ?? 0);
        $batchTo = (int) ($data['batch_to'] ?? 0);

        $reports->save('Dashboard Analytics Snapshot', $analytics->compute($batchFrom, $batchTo));

        return redirect()
            ->route('analytics', array_filter(['batch_from' => $batchFrom, 'batch_to' => $batchTo]))
            ->with('success', 'Analytics snapshot saved.');
    }
}
