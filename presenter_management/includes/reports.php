<?php
declare(strict_types=1);

/**
 * Report queries (Module 5). Read-only: nothing here changes data.
 * Every query is limited to the chapters the user may see (chapter_scope_sql).
 */

const REPORT_TYPES = [
    'upcoming' => 'Upcoming',
    'overdue'  => 'Overdue',
    'chapter'  => 'Chapter-wise',
    'monthly'  => 'Monthly',
];

/** Column headings shared by the screen table and the CSV for list reports. */
function report_list_columns(): array
{
    return ['Member ID', 'Name', 'Email', 'Mobile', 'Chapter', 'Category', 'Coordinator',
            'Last presented', 'Calculated due date', 'Confirmed date', 'Completed date',
            'Venue', 'Status', 'Days to due (negative = overdue)'];
}

/**
 * Rows for the upcoming / overdue / monthly reports.
 * $opts: chapter_id (int), days (int, upcoming), month ('Y-m', monthly)
 */
function fetch_report_rows(string $type, array $opts): array
{
    $scope  = chapter_scope_sql('m');
    $where  = [$scope['sql']];
    $params = $scope['params'];
    $today  = date('Y-m-d');

    $chapterId = (int)($opts['chapter_id'] ?? 0);
    if (is_super_admin() && $chapterId > 0) {
        $where[] = 'm.chapter_id = :chapter_id';
        $params['chapter_id'] = $chapterId;
    }

    if ($type === 'upcoming') {
        $end = (string)add_calendar_days($today, (int)$opts['days']);
        $where[] = "m.status = 'active'";
        $where[] = "((p.status = 'pending' AND p.calculated_due_date BETWEEN :u1 AND :u2)
                  OR (p.status = 'scheduled' AND p.scheduled_date BETWEEN :u3 AND :u4))";
        $params += ['u1' => $today, 'u2' => $end, 'u3' => $today, 'u4' => $end];
        $order = 'COALESCE(p.scheduled_date, p.calculated_due_date) ASC, m.full_name';
    } elseif ($type === 'overdue') {
        $f = presentation_filter_sql('overdue');
        $where[] = "m.status = 'active'";
        $where[] = $f['sql'];
        $params = array_merge($params, $f['params']);
        $order = 'p.calculated_due_date ASC, m.full_name';
    } else { // monthly
        $first = new DateTimeImmutable($opts['month'] . '-01');
        $s = $first->format('Y-m-d');
        $e = $first->format('Y-m-t');
        $where[] = "((p.status IN ('pending','scheduled') AND m.status = 'active'
                      AND (p.calculated_due_date BETWEEN :m1 AND :m2 OR p.scheduled_date BETWEEN :m3 AND :m4))
                  OR (p.status = 'completed' AND p.completed_date BETWEEN :m5 AND :m6))";
        $params += ['m1' => $s, 'm2' => $e, 'm3' => $s, 'm4' => $e, 'm5' => $s, 'm6' => $e];
        $order = 'COALESCE(p.completed_date, p.scheduled_date, p.calculated_due_date) ASC, m.full_name';
    }

    $stmt = db()->prepare(
        'SELECT p.*, m.member_code, m.full_name, m.email, m.mobile, m.last_presentation_date,
                c.name AS chapter_name, cat.name AS category_name, u.full_name AS coordinator_name
         FROM presentations p
         JOIN members m ON m.id = p.member_id
         JOIN chapters c ON c.id = m.chapter_id
         JOIN categories cat ON cat.id = m.category_id
         LEFT JOIN users u ON u.id = m.coordinator_id
         WHERE ' . implode(' AND ', $where) . '
         ORDER BY ' . $order
    );
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** One list row as plain values (used by the CSV; the screen formats its own). */
function report_list_row(array $r): array
{
    $open = in_array($r['status'], ['pending', 'scheduled'], true);
    $days = ($open && $r['calculated_due_date']) ? days_until(date('Y-m-d'), $r['calculated_due_date']) : '';

    return [
        $r['member_code'], $r['full_name'], $r['email'], $r['mobile'], $r['chapter_name'],
        $r['category_name'], $r['coordinator_name'] ?? '',
        $r['last_presentation_date'] ?? '', $r['calculated_due_date'] ?? '',
        $r['scheduled_date'] ?? '', $r['completed_date'] ?? '', $r['venue'] ?? '',
        ucfirst(presentation_status($r)), $days,
    ];
}

/** Chapter-wise summary: member counts + open presentations by live status. */
function fetch_chapter_report(): array
{
    $scope = chapter_scope_sql('m');
    $today = date('Y-m-d');

    // Members per chapter
    $mStmt = db()->prepare(
        "SELECT c.id, c.name,
                COUNT(m.id) AS total_members,
                COALESCE(SUM(m.status = 'active'), 0) AS active_members
         FROM chapters c
         LEFT JOIN members m ON m.chapter_id = c.id
         WHERE c.is_active = 1 AND " . str_replace('m.chapter_id', 'c.id', $scope['sql']) . "
         GROUP BY c.id, c.name ORDER BY c.name"
    );
    $mStmt->execute($scope['params']);

    $out = [];
    foreach ($mStmt->fetchAll() as $row) {
        $out[(int)$row['id']] = [
            'name' => $row['name'], 'total' => (int)$row['total_members'], 'active' => (int)$row['active_members'],
            'overdue' => 0, 'due' => 0, 'approaching' => 0, 'upcoming' => 0, 'scheduled' => 0, 'completed12' => 0,
        ];
    }

    // Open presentations of active members: count by live status in PHP
    $oStmt = db()->prepare(
        "SELECT p.status, p.calculated_due_date, p.scheduled_date, m.chapter_id
         FROM presentations p JOIN members m ON m.id = p.member_id
         WHERE p.status IN ('pending','scheduled') AND m.status = 'active' AND {$scope['sql']}"
    );
    $oStmt->execute($scope['params']);
    foreach ($oStmt->fetchAll() as $r) {
        $cid = (int)$r['chapter_id'];
        $st  = presentation_status($r, $today);
        if (isset($out[$cid][$st])) {
            $out[$cid][$st]++;
        }
    }

    // Completed in the last 12 months
    $cStmt = db()->prepare(
        "SELECT m.chapter_id, COUNT(*) AS c
         FROM presentations p JOIN members m ON m.id = p.member_id
         WHERE p.status = 'completed' AND p.completed_date >= :since AND {$scope['sql']}
         GROUP BY m.chapter_id"
    );
    $cStmt->execute($scope['params'] + ['since' => (new DateTimeImmutable($today))->modify('-12 months')->format('Y-m-d')]);
    foreach ($cStmt->fetchAll() as $r) {
        if (isset($out[(int)$r['chapter_id']])) {
            $out[(int)$r['chapter_id']]['completed12'] = (int)$r['c'];
        }
    }

    return array_values($out);
}

/** Stop spreadsheet apps running a cell as a formula (CSV injection). */
function csv_safe(mixed $value): string
{
    $s = (string)$value;
    if ($s !== '' && in_array($s[0], ['=', '+', '-', '@', "\t", "\r"], true) && !is_numeric($s)) {
        return "'" . $s;
    }
    return $s;
}

/** Send a CSV download and stop. Opens fine in Excel (UTF-8 BOM added). */
function send_csv(string $filename, array $header, array $rows): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) . '"');
    header('Cache-Control: no-store');

    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, $header, ',', '"', '');
    foreach ($rows as $row) {
        fputcsv($out, array_map('csv_safe', $row), ',', '"', '');
    }
    fclose($out);
    exit;
}
