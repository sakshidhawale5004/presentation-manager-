<?php
declare(strict_types=1);

/**
 * Presentation history and 90-day cycle helpers (Module 3).
 *
 * How the cycle works
 * -------------------
 * - Every member has ONE open presentation record (status pending or scheduled).
 * - calculated_due_date = last COMPLETED date + 90 calendar days (never edited by hand).
 * - scheduled_date      = the date someone actually confirmed (separate column).
 * - When a presentation is completed, that row becomes "completed" and a NEW
 *   pending row is created for the next cycle. Old rows are never deleted.
 */

/** Days before the due date when the badge changes from "Approaching" to "Due". */
const DUE_WINDOW_DAYS = 7;

/** Days before the due date when "Upcoming" becomes "Approaching" (settings table, default 30). */
function approaching_days(): int
{
    static $days = null;

    if ($days === null) {
        $days = 30;
        try {
            $stmt = db()->prepare('SELECT setting_value FROM settings WHERE setting_key = :k LIMIT 1');
            $stmt->execute(['k' => 'approaching_days']);
            $value = $stmt->fetchColumn();
            if (is_string($value) && ctype_digit($value) && (int)$value > DUE_WINDOW_DAYS && (int)$value <= 365) {
                $days = (int)$value;
            }
        } catch (PDOException $ex) {
            // settings table missing: keep the default
        }
    }

    return $days;
}

/** Whole days from $from to $to (negative if $to is earlier). */
function days_until(string $from, string $to): int
{
    $a = new DateTimeImmutable($from);
    $b = new DateTimeImmutable($to);
    return (int)$a->diff($b)->format('%r%a');
}

/**
 * Work out the display status of one presentation row.
 * This is calculated every time, never stored, so it is always correct.
 */
function presentation_status(array $p, ?string $today = null): string
{
    $today ??= date('Y-m-d');

    if ($p['status'] === 'completed') {
        return 'completed';
    }
    if ($p['status'] === 'cancelled') {
        return 'cancelled';
    }

    if ($p['status'] === 'scheduled' && !empty($p['scheduled_date'])) {
        return $p['scheduled_date'] < $today ? 'overdue' : 'scheduled';
    }

    $due = $p['calculated_due_date'] ?? null;
    if (empty($due)) {
        return 'upcoming';
    }
    if ($due < $today) {
        return 'overdue';
    }

    $left = days_until($today, $due);
    if ($left <= DUE_WINDOW_DAYS) {
        return 'due';
    }
    if ($left <= approaching_days()) {
        return 'approaching';
    }
    return 'upcoming';
}

function presentation_status_label(string $status): string
{
    return ucfirst($status);
}

function presentation_status_badge(string $status): string
{
    $map = [
        'upcoming'    => 'text-bg-info',
        'approaching' => 'text-bg-warning',
        'due'         => 'badge-due',
        'overdue'     => 'text-bg-danger',
        'scheduled'   => 'text-bg-primary',
        'completed'   => 'text-bg-success',
        'cancelled'   => 'text-bg-secondary',
    ];
    $class = $map[$status] ?? 'text-bg-secondary';
    return '<span class="badge ' . e($class) . '">' . e(presentation_status_label($status)) . '</span>';
}

/** "in 12 days", "today", "5 days overdue" */
function due_in_text(?string $due): string
{
    if ($due === null || !is_valid_date($due)) {
        return '';
    }
    $n = days_until(date('Y-m-d'), $due);
    if ($n === 0) {
        return 'due today';
    }
    if ($n > 0) {
        return 'in ' . $n . ' day' . ($n === 1 ? '' : 's');
    }
    $n = abs($n);
    return $n . ' day' . ($n === 1 ? '' : 's') . ' overdue';
}

/**
 * SQL fragment + parameters for filtering presentation rows by display status.
 * Only matches the logic in presentation_status() above.
 * Returns ['sql' => '...', 'params' => [...]]
 */
function presentation_filter_sql(string $status, string $a = 'p'): array
{
    $today   = date('Y-m-d');
    $dueEnd  = (string)add_calendar_days($today, DUE_WINDOW_DAYS);
    $apprEnd = (string)add_calendar_days($today, approaching_days());

    switch ($status) {
        case 'overdue':
            return [
                'sql' => "(({$a}.status = 'pending' AND {$a}.calculated_due_date < :st_a)
                          OR ({$a}.status = 'scheduled' AND {$a}.scheduled_date < :st_b))",
                'params' => ['st_a' => $today, 'st_b' => $today],
            ];
        case 'scheduled':
            return [
                'sql' => "({$a}.status = 'scheduled' AND {$a}.scheduled_date >= :st_a)",
                'params' => ['st_a' => $today],
            ];
        case 'due':
            return [
                'sql' => "({$a}.status = 'pending' AND {$a}.calculated_due_date >= :st_a AND {$a}.calculated_due_date <= :st_b)",
                'params' => ['st_a' => $today, 'st_b' => $dueEnd],
            ];
        case 'approaching':
            return [
                'sql' => "({$a}.status = 'pending' AND {$a}.calculated_due_date > :st_a AND {$a}.calculated_due_date <= :st_b)",
                'params' => ['st_a' => $dueEnd, 'st_b' => $apprEnd],
            ];
        case 'upcoming':
            return [
                'sql' => "({$a}.status = 'pending' AND {$a}.calculated_due_date > :st_a)",
                'params' => ['st_a' => $apprEnd],
            ];
        case 'completed':
            return ['sql' => "{$a}.status = 'completed'", 'params' => []];
        default:
            return ['sql' => "{$a}.status IN ('pending','scheduled')", 'params' => []];
    }
}

/** Run $fn inside a database transaction (re-uses an existing one if already inside). */
function with_transaction(callable $fn): mixed
{
    $pdo = db();

    if ($pdo->inTransaction()) {
        return $fn($pdo);
    }

    $pdo->beginTransaction();
    try {
        $result = $fn($pdo);
        $pdo->commit();
        return $result;
    } catch (Throwable $t) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        throw $t;
    }
}

/** One line for the notes log: [26 Oct 2026 14:05 · Hasina] text */
function audit_line(string $text): string
{
    $who = current_user()['full_name'] ?? 'System';
    return '[' . date('d M Y H:i') . ' · ' . $who . '] ' . $text;
}

function append_note(?string $old, string $line): string
{
    $old = trim((string)$old);
    return $old === '' ? $line : $old . "\n" . $line;
}

function member_has_history(int $memberId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM presentations WHERE member_id = :id LIMIT 1');
    $stmt->execute(['id' => $memberId]);
    return (bool)$stmt->fetchColumn();
}

function get_open_presentation(int $memberId): ?array
{
    $stmt = db()->prepare(
        "SELECT * FROM presentations
         WHERE member_id = :id AND status IN ('pending','scheduled')
         ORDER BY id DESC LIMIT 1"
    );
    $stmt->execute(['id' => $memberId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Full history of one member, newest first. Nothing is ever removed from this list. */
function fetch_member_presentations(int $memberId): array
{
    $stmt = db()->prepare(
        'SELECT p.*, ch.name AS completed_chapter_name
         FROM presentations p
         LEFT JOIN chapters ch ON ch.id = p.completed_chapter_id
         WHERE p.member_id = :id
         ORDER BY COALESCE(p.completed_date, p.scheduled_date, p.calculated_due_date) DESC, p.id DESC'
    );
    $stmt->execute(['id' => $memberId]);
    return $stmt->fetchAll();
}

/** Load one presentation with member details. Returns null if missing or not allowed. */
function load_presentation(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT p.*,
                m.member_code, m.full_name, m.email, m.mobile,
                m.status AS member_status, m.chapter_id,
                c.name AS chapter_name,
                cc.name AS completed_chapter_name
         FROM presentations p
         JOIN members m ON m.id = p.member_id
         JOIN chapters c ON c.id = m.chapter_id
         LEFT JOIN chapters cc ON cc.id = p.completed_chapter_id
         WHERE p.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row || !can_access_chapter((int)$row['chapter_id'])) {
        return null;
    }
    return $row;
}

/**
 * Make sure an ACTIVE member that has a last presentation date also has one open
 * (pending) record for the next cycle. Safe to call many times.
 */
function ensure_open_cycle(int $memberId): void
{
    with_transaction(function (PDO $pdo) use ($memberId) {
        $stmt = $pdo->prepare('SELECT id, status, last_presentation_date FROM members WHERE id = :id FOR UPDATE');
        $stmt->execute(['id' => $memberId]);
        $m = $stmt->fetch();

        if (!$m || $m['status'] !== 'active' || empty($m['last_presentation_date'])) {
            return;
        }

        $open = $pdo->prepare("SELECT id FROM presentations WHERE member_id = :id AND status IN ('pending','scheduled') LIMIT 1");
        $open->execute(['id' => $memberId]);
        if ($open->fetchColumn()) {
            return;
        }

        $ins = $pdo->prepare(
            "INSERT INTO presentations (member_id, calculated_due_date, status, created_by, updated_by)
             VALUES (:member_id, :due, 'pending', :uid, :uid2)"
        );
        $uid = current_user()['id'] ?? null;
        $ins->execute([
            'member_id' => $memberId,
            'due'       => add_calendar_days($m['last_presentation_date']),
            'uid'       => $uid,
            'uid2'      => $uid,
        ]);
    });
}

/**
 * Called after a member is saved on the Member form.
 * If the member has a last presentation date but no history yet, store it as the
 * first COMPLETED history row, then make sure the next cycle exists.
 */
function sync_member_history(int $memberId, ?string $lastDate, ?string $venue, ?int $chapterId = null): void
{
    with_transaction(function (PDO $pdo) use ($memberId, $lastDate, $venue, $chapterId) {
        $lock = $pdo->prepare('SELECT id FROM members WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $memberId]);

        if ($lastDate !== null && $lastDate !== '' && !member_has_history($memberId)) {
            $uid = current_user()['id'] ?? null;
            $ins = $pdo->prepare(
                "INSERT INTO presentations
                    (member_id, calculated_due_date, completed_date, venue, completed_chapter_id, status, details_confirmed, notes, created_by, updated_by)
                 VALUES
                    (:member_id, NULL, :done, :venue, :chapter, 'completed', 1, :notes, :uid, :uid2)"
            );
            $ins->execute([
                'member_id' => $memberId,
                'done'      => $lastDate,
                'venue'     => $venue,
                'chapter'   => $chapterId,
                'notes'     => audit_line('Recorded from the member profile.'),
                'uid'       => $uid,
                'uid2'      => $uid,
            ]);
        }
    });

    ensure_open_cycle($memberId);
}

/**
 * Record that a presentation was ACTUALLY completed on $date.
 *
 *  - Closes the open cycle (or creates a completed row if the member has no open cycle).
 *  - Updates the member's last_presentation_date and next_due_date.
 *  - Creates the next pending cycle = $date + 90 calendar days (active members only).
 *
 * Returns [true|false, message].
 */
function record_completed_presentation(
    int $memberId,
    string $date,
    ?string $venue,
    ?string $notes,
    ?int $presentationId = null,
    ?int $chapterId = null
): array {
    $date  = trim($date);
    $venue = trim((string)$venue);
    $notes = trim((string)$notes);

    if (!is_valid_date($date)) {
        return [false, 'Enter a valid completion date.'];
    }
    if ($date > date('Y-m-d')) {
        return [false, 'The completion date cannot be in the future. Use "Schedule" for future dates.'];
    }
    if (mb_strlen($venue) > 190) {
        return [false, 'Venue must be 190 characters or fewer.'];
    }
    if (mb_strlen($notes) > 1000) {
        return [false, 'Notes must be 1000 characters or fewer.'];
    }
    if ($chapterId === null || $chapterId <= 0) {
        return [false, 'Choose the chapter where the presentation took place.'];
    }
    $chk = db()->prepare('SELECT 1 FROM chapters WHERE id = :id AND is_active = 1');
    $chk->execute(['id' => $chapterId]);
    if (!$chk->fetchColumn()) {
        return [false, 'Please choose a valid chapter.'];
    }

    try {
        $message = with_transaction(function (PDO $pdo) use ($memberId, $date, $venue, $notes, $presentationId, $chapterId) {
            $uid = current_user()['id'] ?? null;

            $mStmt = $pdo->prepare('SELECT id, status FROM members WHERE id = :id FOR UPDATE');
            $mStmt->execute(['id' => $memberId]);
            $member = $mStmt->fetch();
            if (!$member) {
                throw new DomainException('Member not found.');
            }

            $maxStmt = $pdo->prepare("SELECT MAX(completed_date) FROM presentations WHERE member_id = :id AND status = 'completed'");
            $maxStmt->execute(['id' => $memberId]);
            $lastDone = $maxStmt->fetchColumn();
            if ($lastDone && $date <= $lastDone) {
                throw new DomainException(
                    'The completion date must be after the last recorded presentation (' . format_date((string)$lastDone) . ').'
                );
            }

            $openStmt = $pdo->prepare(
                "SELECT * FROM presentations
                 WHERE member_id = :id AND status IN ('pending','scheduled')
                 ORDER BY id DESC LIMIT 1 FOR UPDATE"
            );
            $openStmt->execute(['id' => $memberId]);
            $open = $openStmt->fetch();

            if ($presentationId !== null) {
                if (!$open || (int)$open['id'] !== $presentationId) {
                    throw new DomainException('This presentation is no longer open. Please refresh the page.');
                }
            } elseif ($open) {
                throw new DomainException('This member already has an open presentation. Record the completion on that one.');
            }

            $logText = 'Completed on ' . format_date($date)
                . ($venue !== '' ? ' at ' . $venue : '')
                . ($notes !== '' ? ' — ' . $notes : '');

            if ($open) {
                $upd = $pdo->prepare(
                    "UPDATE presentations
                     SET status = 'completed', completed_date = :done, venue = :venue,
                         completed_chapter_id = :chapter,
                         details_confirmed = 1, notes = :notes, updated_by = :uid
                     WHERE id = :id"
                );
                $upd->execute([
                    'done'  => $date,
                    'venue' => $venue !== '' ? $venue : $open['venue'],
                    'chapter' => $chapterId,
                    'notes' => append_note($open['notes'], audit_line($logText)),
                    'uid'   => $uid,
                    'id'    => $open['id'],
                ]);
            } else {
                $ins = $pdo->prepare(
                    "INSERT INTO presentations
                        (member_id, calculated_due_date, completed_date, venue, completed_chapter_id, status, details_confirmed, notes, created_by, updated_by)
                     VALUES
                        (:member_id, NULL, :done, :venue, :chapter, 'completed', 1, :notes, :uid, :uid2)"
                );
                $ins->execute([
                    'member_id' => $memberId,
                    'done'      => $date,
                    'venue'     => $venue !== '' ? $venue : null,
                    'chapter'   => $chapterId,
                    'notes'     => audit_line($logText),
                    'uid'       => $uid,
                    'uid2'      => $uid,
                ]);
            }

            $nextDue = (string)add_calendar_days($date);

            $mUpd = $pdo->prepare(
                'UPDATE members
                 SET last_presentation_date = :done, next_due_date = :next, venue = COALESCE(:venue, venue),
                     last_presentation_chapter_id = :chapter
                 WHERE id = :id'
            );
            $mUpd->execute([
                'done'  => $date,
                'next'  => $nextDue,
                'venue' => $venue !== '' ? $venue : null,
                'chapter' => $chapterId,
                'id'    => $memberId,
            ]);

            if ($member['status'] === 'active') {
                $new = $pdo->prepare(
                    "INSERT INTO presentations (member_id, calculated_due_date, status, created_by, updated_by)
                     VALUES (:member_id, :due, 'pending', :uid, :uid2)"
                );
                $new->execute([
                    'member_id' => $memberId,
                    'due'       => $nextDue,
                    'uid'       => $uid,
                    'uid2'      => $uid,
                ]);
                return 'Presentation recorded. Next due date: ' . format_date($nextDue) . '.';
            }

            return 'Presentation recorded. The member is inactive, so no next cycle was created.';
        });

        return [true, (string)$message];
    } catch (DomainException $ex) {
        return [false, $ex->getMessage()];
    } catch (PDOException $ex) {
        error_log('record_completed_presentation: ' . $ex->getMessage());
        return [false, 'Could not save the presentation. Please try again.'];
    }
}

/**
 * Confirm (or change) the scheduled date of an open presentation.
 * The calculated due date is NOT changed.
 */
function schedule_presentation(int $presentationId, string $date, ?string $venue, bool $confirmed, ?string $notes): array
{
    $date  = trim($date);
    $venue = trim((string)$venue);
    $notes = trim((string)$notes);

    if (!is_valid_date($date)) {
        return [false, 'Enter a valid scheduled date.'];
    }
    if ($date < date('Y-m-d')) {
        return [false, 'The scheduled date cannot be in the past. If it already happened, use "Record completed".'];
    }
    if (mb_strlen($venue) > 190) {
        return [false, 'Venue must be 190 characters or fewer.'];
    }
    if (mb_strlen($notes) > 1000) {
        return [false, 'Notes must be 1000 characters or fewer.'];
    }

    try {
        with_transaction(function (PDO $pdo) use ($presentationId, $date, $venue, $confirmed, $notes) {
            $stmt = $pdo->prepare(
                'SELECT p.*, m.status AS member_status
                 FROM presentations p
                 JOIN members m ON m.id = p.member_id
                 WHERE p.id = :id FOR UPDATE'
            );
            $stmt->execute(['id' => $presentationId]);
            $p = $stmt->fetch();

            if (!$p || !in_array($p['status'], ['pending', 'scheduled'], true)) {
                throw new DomainException('Only open presentations can be scheduled.');
            }
            if ($p['member_status'] !== 'active') {
                throw new DomainException('Reactivate the member before scheduling a presentation.');
            }

            $text = ($p['scheduled_date']
                    ? 'Rescheduled from ' . format_date($p['scheduled_date']) . ' to ' . format_date($date)
                    : 'Scheduled for ' . format_date($date))
                . ($venue !== '' ? ' at ' . $venue : '')
                . ($confirmed ? ' (details confirmed)' : ' (details not confirmed yet)')
                . ($notes !== '' ? ' — ' . $notes : '');

            $upd = $pdo->prepare(
                "UPDATE presentations
                 SET status = 'scheduled', scheduled_date = :sd, venue = :venue,
                     details_confirmed = :conf, notes = :notes, updated_by = :uid
                 WHERE id = :id"
            );
            $upd->execute([
                'sd'    => $date,
                'venue' => $venue !== '' ? $venue : $p['venue'],
                'conf'  => $confirmed ? 1 : 0,
                'notes' => append_note($p['notes'], audit_line($text)),
                'uid'   => current_user()['id'] ?? null,
                'id'    => $presentationId,
            ]);
        });

        return [true, 'Schedule saved.'];
    } catch (DomainException $ex) {
        return [false, $ex->getMessage()];
    } catch (PDOException $ex) {
        error_log('schedule_presentation: ' . $ex->getMessage());
        return [false, 'Could not save the schedule. Please try again.'];
    }
}

/** Remove the confirmed date (the calculated due date stays). */
function clear_presentation_schedule(int $presentationId): array
{
    try {
        $result = with_transaction(function (PDO $pdo) use ($presentationId) {
            $stmt = $pdo->prepare('SELECT * FROM presentations WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $presentationId]);
            $p = $stmt->fetch();

            if (!$p || $p['status'] !== 'scheduled') {
                throw new DomainException('This presentation has no confirmed schedule to clear.');
            }

            $upd = $pdo->prepare(
                "UPDATE presentations
                 SET status = 'pending', scheduled_date = NULL, details_confirmed = 0,
                     notes = :notes, updated_by = :uid
                 WHERE id = :id"
            );
            $upd->execute([
                'notes' => append_note($p['notes'], audit_line('Schedule cleared (was ' . format_date($p['scheduled_date']) . ').')),
                'uid'   => current_user()['id'] ?? null,
                'id'    => $presentationId,
            ]);
            return 'Schedule cleared. The calculated due date is unchanged.';
        });

        return [true, (string)$result];
    } catch (DomainException $ex) {
        return [false, $ex->getMessage()];
    } catch (PDOException $ex) {
        error_log('clear_presentation_schedule: ' . $ex->getMessage());
        return [false, 'Could not clear the schedule. Please try again.'];
    }
}

/**
 * Limits a query to the chapter(s) the logged-in user may see.
 * Super Admin = everything. Chapter Coordinator = own chapter only.
 * Uses the named parameter :scope_chapter (use it once per query).
 */
function chapter_scope_sql(string $alias = 'm'): array
{
    if (is_super_admin()) {
        return ['sql' => '1=1', 'params' => []];
    }

    $cid = (int)(current_user()['chapter_id'] ?? 0);
    if ($cid < 1) {
        return ['sql' => '1=0', 'params' => []];
    }

    return ['sql' => "{$alias}.chapter_id = :scope_chapter", 'params' => ['scope_chapter' => $cid]];
}
