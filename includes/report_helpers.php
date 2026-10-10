<?php
declare(strict_types=1);

require_once __DIR__ . '/dashboard_analytics.php';

/**
 * Build a report summary payload from analytics data.
 *
 * @param array<string, mixed> $analytics
 * @param array<int, array<string, mixed>> $graduates
 * @return array<string, mixed>
 */
function build_report_summary(array $analytics, array $graduates = []): array
{
    return [
        'filters' => $analytics['filters'],
        'employment_counts' => $analytics['counts_by_status'],
        'employment_rate' => $analytics['employment_rate'],
        'verification_rate' => $analytics['verification_rate'],
        'profile_completion_rate' => $analytics['profile_completion_rate'],
        'complete_profiles' => (int)($analytics['complete_profiles'] ?? 0),
        'incomplete_profiles' => (int)($analytics['incomplete_profiles'] ?? 0),
        'students_without_profile' => (int)($analytics['students_without_profile'] ?? 0),
        'total_profiles' => $analytics['total_profiles'],
        'batch_breakdown' => $analytics['batch_matrix'] ?: new stdClass(),
        'batch_trends' => $analytics['batch_trends'] ?? [],
        'course_breakdown' => $analytics['course_breakdown'],
        'lacking_criteria' => $analytics['lacking_criteria'],
        'decision_insights' => $analytics['decision_insights'],
        'graduates' => $graduates,
        'generated_at' => date('c'),
    ];
}

/**
 * Allowed employment filter values for Generate Reports.
 *
 * @return list<string>
 */
function report_employment_filter_options(): array
{
    return ['all', 'All Employed', 'Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
}

/**
 * Fetch graduate rows for a report roster.
 *
 * @return array<int, array<string, mixed>>
 */
function fetch_report_graduates(PDO $pdo, int $batchFrom = 0, int $batchTo = 0, string $employmentFilter = '', int $limit = 500): array
{
    $statusFilter = resolve_employment_status_filter($employmentFilter);
    $where = ["ua.Status = 'Active'"];
    $params = [];

    if ($batchFrom > 0) {
        $where[] = 'gp.Batch_Year >= ?';
        $params[] = $batchFrom;
    }
    if ($batchTo > 0) {
        $where[] = 'gp.Batch_Year <= ?';
        $params[] = $batchTo;
    }
    if ($statusFilter !== null) {
        $placeholders = implode(',', array_fill(0, count($statusFilter), '?'));
        $where[] = "gp.Employment_Status IN ($placeholders)";
        foreach ($statusFilter as $statusValue) {
            $params[] = $statusValue;
        }
    }

    $sql =
        "SELECT gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status,
                gp.Company_School_Name, gp.Contact_Info, gp.Is_Verified,
                ua.Username, ua.Student_No
         FROM Graduate_Profiles gp
         INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
         WHERE " . implode(' AND ', $where) . "
         ORDER BY gp.Batch_Year DESC, gp.Full_Name ASC
         LIMIT " . max(1, min(1000, $limit));

    try {
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = [];
        foreach ($stmt->fetchAll() as $row) {
            $rows[] = [
                'full_name' => (string)$row['Full_Name'],
                'student_id' => (string)(($row['Student_No'] ?? '') !== '' ? $row['Student_No'] : ($row['Username'] ?? '')),
                'batch_year' => (int)$row['Batch_Year'],
                'strand' => (string)($row['Course'] ?? ''),
                'employment_status' => (string)$row['Employment_Status'],
                'company_school' => (string)($row['Company_School_Name'] ?? ''),
                'contact' => (string)($row['Contact_Info'] ?? ''),
                'verified' => ((int)($row['Is_Verified'] ?? 0) === 1) ? 'Yes' : 'No',
            ];
        }
        return $rows;
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Persist one analytics report snapshot.
 */
function save_generated_report(PDO $pdo, string $reportName, array $analytics, array $graduates = []): void
{
    $summary = build_report_summary($analytics, $graduates);
    $ins = $pdo->prepare('INSERT INTO Reports (Report_Name, Summary_JSON) VALUES (?, ?)');
    $ins->execute([$reportName, json_encode($summary, JSON_UNESCAPED_UNICODE)]);
}

/**
 * @return array<int, array<string, mixed>>
 */
function fetch_saved_reports(PDO $pdo, int $limit = 50): array
{
    $stmt = $pdo->prepare(
        'SELECT Report_ID, Report_Name, Generated_Date, Summary_JSON
         FROM Reports
         ORDER BY Generated_Date DESC
         LIMIT ?'
    );
    $stmt->bindValue(1, max(1, $limit), PDO::PARAM_INT);
    $stmt->execute();
    $rows = [];
    foreach ($stmt->fetchAll() as $row) {
        $summary = json_decode((string)$row['Summary_JSON'], true);
        $rows[] = [
            'report_id' => (int)$row['Report_ID'],
            'report_name' => (string)$row['Report_Name'],
            'generated_date' => (string)$row['Generated_Date'],
            'summary' => is_array($summary) ? $summary : [],
        ];
    }
    return $rows;
}

/**
 * @return array<string, mixed>|null
 */
function fetch_report_by_id(PDO $pdo, int $reportId): ?array
{
    if ($reportId <= 0) {
        return null;
    }
    $stmt = $pdo->prepare(
        'SELECT Report_ID, Report_Name, Generated_Date, Summary_JSON
         FROM Reports WHERE Report_ID = ? LIMIT 1'
    );
    $stmt->execute([$reportId]);
    $row = $stmt->fetch();
    if (!$row) {
        return null;
    }
    $summary = json_decode((string)$row['Summary_JSON'], true);
    return [
        'report_id' => (int)$row['Report_ID'],
        'report_name' => (string)$row['Report_Name'],
        'generated_date' => (string)$row['Generated_Date'],
        'summary' => is_array($summary) ? $summary : [],
    ];
}

/**
 * Extract snapshot trend points for line charts.
 *
 * @return array<int, array{label: string, employment_rate: float, verification_rate: float, profile_completion_rate: float}>
 */
function fetch_snapshot_trends(PDO $pdo, int $limit = 12): array
{
    $reports = fetch_saved_reports($pdo, $limit);
    $points = [];
    foreach (array_reverse($reports) as $report) {
        $summary = $report['summary'];
        $points[] = [
            'label' => date('M j, Y', strtotime($report['generated_date'])),
            'employment_rate' => (float)($summary['employment_rate'] ?? 0),
            'verification_rate' => (float)($summary['verification_rate'] ?? 0),
            'profile_completion_rate' => (float)($summary['profile_completion_rate'] ?? 0),
        ];
    }
    return $points;
}

function export_report_csv(array $report): void
{
    $summary = $report['summary'];
    $filters = is_array($summary['filters'] ?? null) ? $summary['filters'] : [];
    $filename = 'report-' . (int)$report['report_id'] . '-' . date('Y-m-d') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $out = fopen('php://output', 'w');
    if ($out === false) {
        return;
    }

    fputcsv($out, ['Report', $report['report_name']]);
    fputcsv($out, ['Generated', $report['generated_date']]);
    fputcsv($out, ['Employment filter', (string)($filters['employment_status'] ?? 'All Status')]);
    fputcsv($out, ['Batch from', (string)($filters['batch_from'] ?? '')]);
    fputcsv($out, ['Batch to', (string)($filters['batch_to'] ?? '')]);
    fputcsv($out, []);

    fputcsv($out, ['Metric', 'Value']);
    fputcsv($out, ['Total profiles', (int)($summary['total_profiles'] ?? 0)]);
    fputcsv($out, ['Employment rate (%)', (float)($summary['employment_rate'] ?? 0)]);
    fputcsv($out, ['Verification rate (%)', (float)($summary['verification_rate'] ?? 0)]);
    fputcsv($out, ['Profile completion (%)', (float)($summary['profile_completion_rate'] ?? 0)]);
    fputcsv($out, ['Complete profiles', (int)($summary['complete_profiles'] ?? 0)]);
    fputcsv($out, ['Incomplete profiles', (int)($summary['incomplete_profiles'] ?? 0)]);
    fputcsv($out, ['Students without profile', (int)($summary['students_without_profile'] ?? 0)]);
    fputcsv($out, []);

    fputcsv($out, ['Employment status', 'Count']);
    foreach (($summary['employment_counts'] ?? []) as $status => $count) {
        fputcsv($out, [(string)$status, (int)$count]);
    }
    fputcsv($out, []);

    fputcsv($out, ['Course', 'Profiles', 'Employed', 'Rate (%)']);
    foreach (($summary['course_breakdown'] ?? []) as $course) {
        if (!is_array($course)) {
            continue;
        }
        fputcsv($out, [
            (string)($course['course'] ?? ''),
            (int)($course['total'] ?? 0),
            (int)($course['employed'] ?? 0),
            (float)($course['rate'] ?? 0),
        ]);
    }

    $graduates = is_array($summary['graduates'] ?? null) ? $summary['graduates'] : [];
    if ($graduates) {
        fputcsv($out, []);
        fputcsv($out, ['Graduate roster']);
        fputcsv($out, ['Full Name', 'Student ID', 'Batch', 'Strand', 'Employment Status', 'Company/School', 'Contact', 'Verified']);
        foreach ($graduates as $g) {
            if (!is_array($g)) {
                continue;
            }
            fputcsv($out, [
                (string)($g['full_name'] ?? ''),
                (string)($g['student_id'] ?? ''),
                (string)($g['batch_year'] ?? ''),
                (string)($g['strand'] ?? ''),
                (string)($g['employment_status'] ?? ''),
                (string)($g['company_school'] ?? ''),
                (string)($g['contact'] ?? ''),
                (string)($g['verified'] ?? ''),
            ]);
        }
    }

    fclose($out);
}
