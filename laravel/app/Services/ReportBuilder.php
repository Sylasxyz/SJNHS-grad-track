<?php

namespace App\Services;

use App\Models\Report;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use stdClass;

/**
 * Saved analytics reports, ported from includes/report_helpers.php.
 * Summary_JSON keeps the legacy shape so legacy and Laravel pages read each other's reports.
 */
class ReportBuilder
{
    /** Allowed employment filter values for Generate Reports. */
    public const FILTER_OPTIONS = ['all', 'All Employed', 'Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];

    public function __construct(private GraduateAnalytics $analytics) {}

    /**
     * @param  array<string, mixed>  $analytics
     * @param  array<int, array<string, mixed>>  $graduates
     * @return array<string, mixed>
     */
    public static function buildSummary(array $analytics, array $graduates = []): array
    {
        return [
            'filters' => $analytics['filters'],
            'employment_counts' => $analytics['counts_by_status'],
            'employment_rate' => $analytics['employment_rate'],
            'verification_rate' => $analytics['verification_rate'],
            'profile_completion_rate' => $analytics['profile_completion_rate'],
            'complete_profiles' => (int) ($analytics['complete_profiles'] ?? 0),
            'incomplete_profiles' => (int) ($analytics['incomplete_profiles'] ?? 0),
            'students_without_profile' => (int) ($analytics['students_without_profile'] ?? 0),
            'total_profiles' => $analytics['total_profiles'],
            'batch_breakdown' => $analytics['batch_matrix'] ?: new stdClass,
            'batch_trends' => $analytics['batch_trends'] ?? [],
            'course_breakdown' => $analytics['course_breakdown'],
            'lacking_criteria' => $analytics['lacking_criteria'],
            'decision_insights' => $analytics['decision_insights'],
            'graduates' => $graduates,
            'generated_at' => date('c'),
        ];
    }

    /**
     * Graduate rows for a report roster.
     *
     * @return array<int, array<string, mixed>>
     */
    public function graduates(int $batchFrom = 0, int $batchTo = 0, string $employmentFilter = '', int $limit = 500): array
    {
        [$whereSql, $params] = GraduateAnalytics::profileWhere($batchFrom, $batchTo, $employmentFilter);

        $rows = DB::select(
            "SELECT gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status,
                    gp.Company_School_Name, gp.Contact_Info, gp.Is_Verified,
                    ua.Username, ua.Student_No
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             $whereSql
             ORDER BY gp.Batch_Year DESC, gp.Full_Name ASC
             LIMIT ".max(1, min(1000, $limit)),
            $params
        );

        return array_map(fn (stdClass $row) => [
            'full_name' => (string) $row->Full_Name,
            'student_id' => (string) (($row->Student_No ?? '') !== '' ? $row->Student_No : ($row->Username ?? '')),
            'batch_year' => (int) $row->Batch_Year,
            'strand' => (string) ($row->Course ?? ''),
            'employment_status' => (string) $row->Employment_Status,
            'company_school' => (string) ($row->Company_School_Name ?? ''),
            'contact' => (string) ($row->Contact_Info ?? ''),
            'verified' => ((int) ($row->Is_Verified ?? 0) === 1) ? 'Yes' : 'No',
        ], $rows);
    }

    /**
     * Compute and persist one report snapshot for the given filters.
     */
    public function generate(string $reportName, int $batchFrom, int $batchTo, string $employmentFilter): void
    {
        $analytics = $this->analytics->compute($batchFrom, $batchTo, $employmentFilter);
        $graduates = $this->graduates($batchFrom, $batchTo, $employmentFilter);
        $this->save($reportName, $analytics, $graduates);
    }

    /**
     * @param  array<string, mixed>  $analytics
     * @param  array<int, array<string, mixed>>  $graduates
     */
    public function save(string $reportName, array $analytics, array $graduates = []): void
    {
        // Query-builder insert so Generated_Date keeps its database default.
        Report::query()->toBase()->insert([
            'Report_Name' => $reportName,
            'Summary_JSON' => json_encode(self::buildSummary($analytics, $graduates), JSON_UNESCAPED_UNICODE),
        ]);
    }

    /**
     * @return Collection<int, Report>
     */
    public function saved(int $limit = 50): Collection
    {
        return Report::query()->orderByDesc('Generated_Date')->limit(max(1, $limit))->get();
    }

    /**
     * Snapshot trend points for the line chart, oldest first.
     *
     * @return list<array{label: string, employment_rate: float, verification_rate: float, profile_completion_rate: float}>
     */
    public function snapshotTrends(int $limit = 12): array
    {
        return $this->saved($limit)->reverse()->map(function (Report $report) {
            $summary = $report->summary();

            return [
                'label' => Carbon::parse($report->generatedDate())->format('M j, Y'),
                'employment_rate' => (float) ($summary['employment_rate'] ?? 0),
                'verification_rate' => (float) ($summary['verification_rate'] ?? 0),
                'profile_completion_rate' => (float) ($summary['profile_completion_rate'] ?? 0),
            ];
        })->values()->all();
    }

    public static function csvFilename(Report $report): string
    {
        return 'report-'.(int) $report->Report_ID.'-'.date('Y-m-d').'.csv';
    }

    /**
     * @param  resource  $out
     */
    public static function writeCsv(Report $report, $out): void
    {
        $summary = $report->summary();
        $filters = is_array($summary['filters'] ?? null) ? $summary['filters'] : [];

        fputcsv($out, ['Report', $report->Report_Name]);
        fputcsv($out, ['Generated', $report->generatedDate()]);
        fputcsv($out, ['Employment filter', (string) ($filters['employment_status'] ?? 'All Status')]);
        fputcsv($out, ['Batch from', (string) ($filters['batch_from'] ?? '')]);
        fputcsv($out, ['Batch to', (string) ($filters['batch_to'] ?? '')]);
        fputcsv($out, []);

        fputcsv($out, ['Metric', 'Value']);
        fputcsv($out, ['Total profiles', (int) ($summary['total_profiles'] ?? 0)]);
        fputcsv($out, ['Employment rate (%)', (float) ($summary['employment_rate'] ?? 0)]);
        fputcsv($out, ['Verification rate (%)', (float) ($summary['verification_rate'] ?? 0)]);
        fputcsv($out, ['Profile completion (%)', (float) ($summary['profile_completion_rate'] ?? 0)]);
        fputcsv($out, ['Complete profiles', (int) ($summary['complete_profiles'] ?? 0)]);
        fputcsv($out, ['Incomplete profiles', (int) ($summary['incomplete_profiles'] ?? 0)]);
        fputcsv($out, ['Students without profile', (int) ($summary['students_without_profile'] ?? 0)]);
        fputcsv($out, []);

        fputcsv($out, ['Employment status', 'Count']);
        foreach (($summary['employment_counts'] ?? []) as $status => $count) {
            fputcsv($out, [(string) $status, (int) $count]);
        }
        fputcsv($out, []);

        fputcsv($out, ['Course', 'Profiles', 'Employed', 'Rate (%)']);
        foreach (($summary['course_breakdown'] ?? []) as $course) {
            if (! is_array($course)) {
                continue;
            }
            fputcsv($out, [
                (string) ($course['course'] ?? ''),
                (int) ($course['total'] ?? 0),
                (int) ($course['employed'] ?? 0),
                (float) ($course['rate'] ?? 0),
            ]);
        }

        $graduates = is_array($summary['graduates'] ?? null) ? $summary['graduates'] : [];
        if ($graduates) {
            fputcsv($out, []);
            fputcsv($out, ['Graduate roster']);
            fputcsv($out, ['Full Name', 'Student ID', 'Batch', 'Strand', 'Employment Status', 'Company/School', 'Contact', 'Verified']);
            foreach ($graduates as $g) {
                if (! is_array($g)) {
                    continue;
                }
                fputcsv($out, [
                    (string) ($g['full_name'] ?? ''),
                    (string) ($g['student_id'] ?? ''),
                    (string) ($g['batch_year'] ?? ''),
                    (string) ($g['strand'] ?? ''),
                    (string) ($g['employment_status'] ?? ''),
                    (string) ($g['company_school'] ?? ''),
                    (string) ($g['contact'] ?? ''),
                    (string) ($g['verified'] ?? ''),
                ]);
            }
        }
    }
}
