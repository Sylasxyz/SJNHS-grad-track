<?php
declare(strict_types=1);

/**
 * Field-level completion for one graduate profile (same rules as the student dashboard).
 *
 * @param array<string, mixed>|null $profile
 */
function score_graduate_profile_completion(?array $profile): int
{
    if (!$profile) {
        return 0;
    }
    $checks = [
        trim((string)($profile['Full_Name'] ?? '')) !== '',
        trim((string)($profile['Course'] ?? '')) !== '',
        !empty($profile['Batch_Year']),
        trim((string)($profile['Employment_Status'] ?? '')) !== '',
        trim((string)($profile['Contact_Info'] ?? '')) !== '',
        trim((string)($profile['Profile_Picture'] ?? '')) !== '',
    ];
    $status = (string)($profile['Employment_Status'] ?? '');
    if (in_array($status, ['Employed', 'Self-Employed'], true)) {
        $checks[] = trim((string)($profile['Company_School_Name'] ?? '')) !== '';
    } elseif ($status === 'Further Studies') {
        $program = trim((string)($profile['Further_Studies_Program'] ?? ''));
        $company = trim((string)($profile['Company_School_Name'] ?? ''));
        $checks[] = ($program !== '' || $company !== '');
    }
    $done = count(array_filter($checks));
    return (int)round(($done / max(1, count($checks))) * 100);
}

/**
 * Normalize employment filter to a status list, or null for all statuses.
 *
 * @return list<string>|null
 */
function resolve_employment_status_filter(string $employmentFilter): ?array
{
    $filter = trim($employmentFilter);
    if ($filter === '' || strcasecmp($filter, 'all') === 0 || strcasecmp($filter, 'All Status') === 0) {
        return null;
    }
    if (strcasecmp($filter, 'All Employed') === 0 || $filter === 'employed_all') {
        return ['Employed', 'Self-Employed'];
    }
    $allowed = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
    if (in_array($filter, $allowed, true)) {
        return [$filter];
    }
    return null;
}

/**
 * Human-readable label for an employment filter value.
 */
function employment_filter_label(string $employmentFilter): string
{
    $filter = trim($employmentFilter);
    if ($filter === '' || strcasecmp($filter, 'all') === 0 || strcasecmp($filter, 'All Status') === 0) {
        return 'All Status';
    }
    if (strcasecmp($filter, 'All Employed') === 0 || $filter === 'employed_all') {
        return 'All Employed';
    }
    return $filter;
}

/**
 * Compute graduate-tracking analytics for dashboard decision-making.
 *
 * @param string $employmentFilter ''|all = all statuses; 'All Employed' = Employed+Self-Employed; or a single status
 * @return array<string, mixed>
 */
function compute_dashboard_analytics(PDO $pdo, int $batchFrom = 0, int $batchTo = 0, string $employmentFilter = ''): array
{
    $statusOrder = ['Employed', 'Unemployed', 'Self-Employed', 'Further Studies'];
    $countsByStatus = array_fill_keys($statusOrder, 0);
    $hasBatchFilter = $batchFrom > 0 || $batchTo > 0;
    $statusFilter = resolve_employment_status_filter($employmentFilter);
    $hasStatusFilter = $statusFilter !== null;
    $narrowFilter = $hasBatchFilter || $hasStatusFilter;

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
    $whereSql = 'WHERE ' . implode(' AND ', $where);

    $stmt = $pdo->prepare(
        "SELECT gp.Employment_Status, COUNT(*) AS cnt
         FROM Graduate_Profiles gp
         INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
         $whereSql
         GROUP BY gp.Employment_Status"
    );
    $stmt->execute($params);
    foreach ($stmt->fetchAll() as $row) {
        $key = (string)$row['Employment_Status'];
        if (isset($countsByStatus[$key])) {
            $countsByStatus[$key] = (int)$row['cnt'];
        }
    }

    $totalProfiles = array_sum($countsByStatus);
    $employedCount = $countsByStatus['Employed'] + $countsByStatus['Self-Employed'];
    $employmentRate = $totalProfiles > 0 ? round(($employedCount / $totalProfiles) * 100, 1) : 0.0;
    $unemploymentRate = $totalProfiles > 0 ? round(($countsByStatus['Unemployed'] / $totalProfiles) * 100, 1) : 0.0;

    $verifiedCount = 0;
    $missingCourse = 0;
    $missingContact = 0;
    $employedMissingEmployer = 0;
    try {
        $detailStmt = $pdo->prepare(
            "SELECT
               SUM(CASE WHEN gp.Is_Verified = 1 THEN 1 ELSE 0 END) AS verified_cnt,
               SUM(CASE WHEN TRIM(COALESCE(gp.Course, '')) = '' THEN 1 ELSE 0 END) AS missing_course,
               SUM(CASE WHEN TRIM(COALESCE(gp.Contact_Info, '')) = '' THEN 1 ELSE 0 END) AS missing_contact,
               SUM(CASE WHEN gp.Employment_Status IN ('Employed', 'Self-Employed')
                         AND TRIM(COALESCE(gp.Company_School_Name, '')) = '' THEN 1 ELSE 0 END) AS employed_no_employer
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             $whereSql"
        );
        $detailStmt->execute($params);
        $detail = $detailStmt->fetch() ?: [];
        $verifiedCount = (int)($detail['verified_cnt'] ?? 0);
        $missingCourse = (int)($detail['missing_course'] ?? 0);
        $missingContact = (int)($detail['missing_contact'] ?? 0);
        $employedMissingEmployer = (int)($detail['employed_no_employer'] ?? 0);
    } catch (Throwable $e) {
        // Columns may be missing on older schemas.
    }

    $verificationRate = $totalProfiles > 0 ? round(($verifiedCount / $totalProfiles) * 100, 1) : 0.0;

    $studentsWithoutProfile = 0;
    $activeStudents = $totalProfiles;
    $completionSum = 0;
    $completionCount = 0;
    $completeProfiles = 0;
    $incompleteProfiles = 0;

    try {
        $profileStmt = $pdo->prepare(
            "SELECT gp.Full_Name, gp.Course, gp.Batch_Year, gp.Employment_Status,
                    gp.Company_School_Name, gp.Further_Studies_Program, gp.Contact_Info, gp.Profile_Picture
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             $whereSql"
        );
        $profileStmt->execute($params);
        foreach ($profileStmt->fetchAll() as $profileRow) {
            $score = score_graduate_profile_completion($profileRow);
            $completionSum += $score;
            $completionCount++;
            if ($score >= 100) {
                $completeProfiles++;
            } else {
                $incompleteProfiles++;
            }
        }
    } catch (Throwable $e) {
        $completionSum = 0;
        $completionCount = 0;
    }

    if (!$narrowFilter) {
        try {
            $studentStmt = $pdo->query(
                "SELECT
                   SUM(CASE WHEN ua.Status = 'Active' THEN 1 ELSE 0 END) AS active_students,
                   SUM(CASE WHEN ua.Status = 'Active' AND gp.Profile_ID IS NULL THEN 1 ELSE 0 END) AS no_profile
                 FROM User_Accounts ua
                 LEFT JOIN Graduate_Profiles gp ON gp.User_ID = ua.User_ID
                 WHERE ua.Role = 'Student'"
            );
            $studentRow = $studentStmt->fetch() ?: [];
            $activeStudents = (int)($studentRow['active_students'] ?? 0);
            $studentsWithoutProfile = (int)($studentRow['no_profile'] ?? 0);
        } catch (Throwable $e) {
            $activeStudents = $totalProfiles;
            $studentsWithoutProfile = 0;
        }
        $completionCount += $studentsWithoutProfile;
    } else {
        $activeStudents = $totalProfiles;
        $studentsWithoutProfile = 0;
    }

    $profileCompletionRate = $completionCount > 0
        ? round($completionSum / $completionCount, 1)
        : 0.0;

    $pendingRegistrations = 0;
    if (!$narrowFilter) {
        try {
            $pendingRegistrations = (int)$pdo->query(
                "SELECT COUNT(*) FROM User_Accounts WHERE Status = 'Pending'"
            )->fetchColumn();
        } catch (Throwable $e) {
            $pendingRegistrations = 0;
        }
    }

    $unverifiedCount = max(0, $totalProfiles - $verifiedCount);

    $batchYears = [];
    $matrix = [];
    $batchStmt = $pdo->prepare(
        "SELECT gp.Batch_Year, gp.Employment_Status, COUNT(*) AS cnt
         FROM Graduate_Profiles gp
         INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
         $whereSql
         GROUP BY gp.Batch_Year, gp.Employment_Status
         ORDER BY gp.Batch_Year ASC"
    );
    $batchStmt->execute($params);
    foreach ($batchStmt->fetchAll() as $row) {
        $year = (int)$row['Batch_Year'];
        $status = (string)$row['Employment_Status'];
        if (!isset($matrix[$year])) {
            $matrix[$year] = array_fill_keys($statusOrder, 0);
            $batchYears[] = $year;
        }
        if (isset($matrix[$year][$status])) {
            $matrix[$year][$status] = (int)$row['cnt'];
        }
    }

    $courseBreakdown = [];
    try {
        $courseStmt = $pdo->prepare(
            "SELECT COALESCE(NULLIF(TRIM(gp.Course), ''), 'Unspecified') AS course_label,
                    COUNT(*) AS total,
                    SUM(CASE WHEN gp.Employment_Status IN ('Employed', 'Self-Employed') THEN 1 ELSE 0 END) AS employed_cnt
             FROM Graduate_Profiles gp
             INNER JOIN User_Accounts ua ON ua.User_ID = gp.User_ID
             $whereSql
             GROUP BY course_label
             ORDER BY total DESC, course_label ASC
             LIMIT 12"
        );
        $courseStmt->execute($params);
        foreach ($courseStmt->fetchAll() as $row) {
            $total = (int)$row['total'];
            $employed = (int)$row['employed_cnt'];
            $courseBreakdown[] = [
                'course' => (string)$row['course_label'],
                'total' => $total,
                'employed' => $employed,
                'rate' => $total > 0 ? round(($employed / $total) * 100, 1) : 0.0,
            ];
        }
    } catch (Throwable $e) {
        $courseBreakdown = [];
    }

    $batchInsights = [];
    foreach ($batchYears as $year) {
        $batchTotal = array_sum($matrix[$year]);
        if ($batchTotal <= 0) {
            continue;
        }
        $batchEmployed = ($matrix[$year]['Employed'] ?? 0) + ($matrix[$year]['Self-Employed'] ?? 0);
        $batchRate = round(($batchEmployed / $batchTotal) * 100, 1);
        $batchInsights[] = [
            'year' => $year,
            'total' => $batchTotal,
            'employment_rate' => $batchRate,
            'unemployed' => (int)($matrix[$year]['Unemployed'] ?? 0),
        ];
    }
    usort($batchInsights, static fn(array $a, array $b): int => $a['employment_rate'] <=> $b['employment_rate']);

    $lackingCriteria = [];
    if ($totalProfiles === 0) {
        $lackingCriteria[] = [
            'severity' => 'high',
            'label' => 'No graduate profile data',
            'detail' => 'There are no active graduate profiles for the selected filters.',
            'action' => 'Encourage students to complete profiles and verify pending registrations.',
        ];
    }
    if ($studentsWithoutProfile > 0) {
        $lackingCriteria[] = [
            'severity' => 'high',
            'label' => 'Students with no graduate profile',
            'detail' => "{$studentsWithoutProfile} active student account(s) have no graduate profile on file.",
            'action' => 'Follow up with students who have not submitted their graduate information.',
        ];
    }
    if ($incompleteProfiles > 0) {
        $lackingCriteria[] = [
            'severity' => 'high',
            'label' => 'Incomplete graduate profiles',
            'detail' => "{$incompleteProfiles} profile(s) in this view are missing required fields (course, contact, photo, or status details).",
            'action' => 'Ask graduates to fill remaining profile fields before using the data for reports.',
        ];
    }
    if ($unverifiedCount > 0) {
        $lackingCriteria[] = [
            'severity' => 'medium',
            'label' => 'Unverified profiles',
            'detail' => "{$unverifiedCount} profile(s) are not yet verified by an instructor.",
            'action' => 'Review and verify profiles in Graduate Records before using data for reporting.',
        ];
    }
    if ($missingCourse > 0) {
        $lackingCriteria[] = [
            'severity' => 'medium',
            'label' => 'Missing course/strand',
            'detail' => "{$missingCourse} profile(s) do not specify a course or strand.",
            'action' => 'Course data is required to compare outcomes by program.',
        ];
    }
    if ($missingContact > 0) {
        $lackingCriteria[] = [
            'severity' => 'low',
            'label' => 'Missing contact information',
            'detail' => "{$missingContact} profile(s) have no contact details.",
            'action' => 'Contact info helps alumni follow-up and tracer studies.',
        ];
    }
    if ($employedMissingEmployer > 0) {
        $lackingCriteria[] = [
            'severity' => 'medium',
            'label' => 'Employed without employer name',
            'detail' => "{$employedMissingEmployer} employed or self-employed graduate(s) did not name a company or school.",
            'action' => 'Collect employer names to validate employment claims.',
        ];
    }
    if ($pendingRegistrations > 0) {
        $lackingCriteria[] = [
            'severity' => 'medium',
            'label' => 'Pending account approvals',
            'detail' => "{$pendingRegistrations} registration(s) await admin approval.",
            'action' => 'Approve or archive pending accounts so students can access the system.',
        ];
    }
    if ($countsByStatus['Unemployed'] > 0 && $unemploymentRate >= 25) {
        $lackingCriteria[] = [
            'severity' => 'high',
            'label' => 'Elevated unemployment share',
            'detail' => round($unemploymentRate, 1) . '% of tracked graduates are marked Unemployed.',
            'action' => 'Consider livelihood programs or alumni placement support for affected batches.',
        ];
    }

    $decisionInsights = [];
    if ($totalProfiles > 0) {
        $decisionInsights[] = "Overall employment rate (Employed + Self-Employed) is {$employmentRate}% across {$totalProfiles} active graduate profile(s).";
        if ($employmentRate < 50) {
            $decisionInsights[] = 'Employment rate is below 50% — prioritize job placement partnerships and profile follow-ups.';
        } elseif ($employmentRate >= 70) {
            $decisionInsights[] = 'Employment rate is strong (70%+) — maintain current placement programs and document best practices.';
        }
    }
    if ($verificationRate < 80 && $totalProfiles > 0) {
        $decisionInsights[] = "Only {$verificationRate}% of profiles are verified — treat unverified records as provisional until reviewed.";
    }
    if ($profileCompletionRate < 80 && $completionCount > 0) {
        $decisionInsights[] = "Profile completion is {$profileCompletionRate}% for the current view — missing fields can skew employment statistics.";
    }
    if ($batchInsights) {
        $lowest = $batchInsights[0];
        $highest = $batchInsights[count($batchInsights) - 1];
        if ($lowest['year'] !== $highest['year']) {
            $decisionInsights[] = "Batch {$lowest['year']} has the lowest employment rate ({$lowest['employment_rate']}%), while batch {$highest['year']} leads at {$highest['employment_rate']}%.";
        }
    }
    if (!$decisionInsights && $totalProfiles === 0) {
        $decisionInsights[] = 'Collect and verify graduate profiles before making placement or program decisions.';
    }

    $datasets = [];
    foreach ($statusOrder as $status) {
        $data = [];
        foreach ($batchYears as $year) {
            $data[] = (int)($matrix[$year][$status] ?? 0);
        }
        $datasets[] = [
            'label' => $status,
            'data' => $data,
        ];
    }

    $batchTrends = [];
    foreach ($batchYears as $year) {
        $batchTotal = array_sum($matrix[$year]);
        if ($batchTotal <= 0) {
            continue;
        }
        $batchEmployed = ($matrix[$year]['Employed'] ?? 0) + ($matrix[$year]['Self-Employed'] ?? 0);
        $batchTrends[] = [
            'year' => $year,
            'total' => $batchTotal,
            'employment_rate' => round(($batchEmployed / $batchTotal) * 100, 1),
            'unemployment_rate' => round((($matrix[$year]['Unemployed'] ?? 0) / $batchTotal) * 100, 1),
        ];
    }

    $filters = [
        'batch_from' => $batchFrom,
        'batch_to' => $batchTo,
        'employment_status' => employment_filter_label($employmentFilter),
        'employment_filter' => $employmentFilter === '' ? 'all' : $employmentFilter,
    ];

    return [
        'status_order' => $statusOrder,
        'counts_by_status' => $countsByStatus,
        'total_profiles' => $totalProfiles,
        'employment_rate' => $employmentRate,
        'unemployment_rate' => $unemploymentRate,
        'verification_rate' => $verificationRate,
        'verified_count' => $verifiedCount,
        'unverified_count' => $unverifiedCount,
        'profile_completion_rate' => $profileCompletionRate,
        'complete_profiles' => $completeProfiles,
        'incomplete_profiles' => $incompleteProfiles,
        'active_students' => $activeStudents,
        'students_without_profile' => $studentsWithoutProfile,
        'pending_registrations' => $pendingRegistrations,
        'batch_years' => $batchYears,
        'batch_matrix' => $matrix,
        'batch_datasets' => $datasets,
        'batch_trends' => $batchTrends,
        'batch_insights' => $batchInsights,
        'course_breakdown' => $courseBreakdown,
        'lacking_criteria' => $lackingCriteria,
        'decision_insights' => $decisionInsights,
        'filters' => $filters,
    ];
}
