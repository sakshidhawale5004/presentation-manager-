<?php
declare(strict_types=1);

/**
 * Email system (Module 6).
 *
 * Email types (email_logs.email_type)
 * -----------------------------------
 *  monthly_reminder     sent once per month to members whose due date falls in that month
 *  thirty_day_reminder  optional: due date is within the next 30 days (once per cycle)
 *  followup             a reminder was sent but date/venue are still unconfirmed (once per cycle)
 *  overdue_admin        weekly summary of overdue members to the admin alert address
 *  test                 "Send test email" button
 *  manual_resend        sent by hand (presentation page / resend button)
 *
 * Duplicate protection: email_logs.dedupe_key is UNIQUE. Automatic emails get a key such as
 * "monthly:{presentation_id}:{YYYY-MM}", so even if the job runs twice, nobody gets two copies.
 */

require_once __DIR__ . '/smtp.php';

const EMAIL_MAX_ATTEMPTS = 3;   // automatic retries for a failed automatic email

// ---------------------------------------------------------------------------
// Small helpers
// ---------------------------------------------------------------------------

function email_type_label(string $type): string
{
    return [
        'monthly_reminder'    => 'Monthly reminder',
        'thirty_day_reminder' => '30-day reminder',
        'followup'            => 'Follow-up',
        'overdue_admin'       => 'Overdue alert (admin)',
        'test'                => 'Test',
        'manual_resend'       => 'Manual',
    ][$type] ?? ucfirst(str_replace('_', ' ', $type));
}

function email_status_badge(string $status): string
{
    $map = ['sent' => 'success', 'failed' => 'danger', 'pending' => 'warning'];
    return '<span class="badge text-bg-' . e($map[$status] ?? 'secondary') . '">' . e(ucfirst($status)) . '</span>';
}

/** All settings rows as key => value (cached per request; pass true to reload after saving). */
function email_settings_all(bool $reload = false): array
{
    static $cache = null;

    if ($cache === null || $reload) {
        $cache = [];
        try {
            foreach (db()->query('SELECT setting_key, setting_value FROM settings') as $row) {
                $cache[(string)$row['setting_key']] = (string)$row['setting_value'];
            }
        } catch (PDOException $ex) {
            // settings table missing: defaults are used
        }
    }

    return $cache;
}

function email_setting(string $key, string $default = ''): string
{
    return email_settings_all()[$key] ?? $default;
}

function email_setting_on(string $key, bool $default = false): bool
{
    $v = email_setting($key, $default ? '1' : '0');
    return $v === '1';
}

/** True when the Gmail App Password has been added to config/mail.php. */
function mail_configured(): bool
{
    return defined('MAIL_PASSWORD') && trim(str_replace(' ', '', (string)MAIL_PASSWORD)) !== '';
}

function mail_sender_address(): string
{
    return defined('MAIL_USERNAME') ? (string)MAIL_USERNAME : '';
}

function smtp_mailer(): SmtpMailer
{
    static $mailer = null;

    if ($mailer === null) {
        $mailer = new SmtpMailer(
            (string)MAIL_HOST,
            (int)MAIL_PORT,
            (string)MAIL_SECURE,
            (string)MAIL_USERNAME,
            str_replace(' ', '', (string)MAIL_PASSWORD),   // Google shows the App Password in groups of 4
            20,
            (bool)MAIL_VERIFY_PEER,
            (string)MAIL_CA_FILE
        );
    }

    return $mailer;
}

/** Plain-text version of an HTML email (for mail apps that do not show HTML). */
function email_html_to_text(string $html): string
{
    $t = preg_replace('#<(style|head|title)[^>]*>.*?</\1>#is', '', $html) ?? $html;
    $t = preg_replace('#</(p|div|tr|h[1-6]|li|table)>|<br\s*/?>#i', "\n", $t) ?? $t;
    $t = preg_replace('#</t[dh]>#i', '  ', $t) ?? $t;
    $t = html_entity_decode(strip_tags($t), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $t = preg_replace("/[ \t]+/", ' ', $t) ?? $t;
    $t = preg_replace("/ ?\n ?/", "\n", $t) ?? $t;
    $t = preg_replace("/\n{3,}/", "\n\n", $t) ?? $t;
    return trim($t);
}

/**
 * Send one email right now (no logging).
 * Returns [ok, error message, fatal]. "fatal" means the connection/login/quota is broken,
 * so there is no point in trying more emails in this run.
 */
function send_email_now(string $to, string $subject, string $html): array
{
    if (!mail_configured()) {
        return [false, 'Email is not set up yet: add the Gmail App Password in config/mail.php.', true];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return [false, 'Invalid recipient email address.', false];
    }

    $fromName = trim(email_setting('email_from_name', APP_NAME)) ?: APP_NAME;
    $replyTo  = trim(email_setting('email_from_address', ''));

    try {
        smtp_mailer()->send(
            mail_sender_address(),
            $fromName,
            $to,
            $subject,
            $html,
            email_html_to_text($html),
            $replyTo !== '' ? $replyTo : null
        );
        return [true, '', false];
    } catch (SmtpException $ex) {
        return [false, mb_substr($ex->getMessage(), 0, 500), $ex->fatal];
    } catch (Throwable $ex) {
        error_log('Email send error: ' . $ex->getMessage());
        return [false, mb_substr('Unexpected error: ' . $ex->getMessage(), 0, 500), true];
    }
}

// ---------------------------------------------------------------------------
// Templates
// ---------------------------------------------------------------------------

function email_layout(string $heading, string $bodyHtml): string
{
    $app = e(APP_NAME);

    return '<!DOCTYPE html><html><head><meta charset="utf-8"></head>'
        . '<body style="margin:0;padding:0;background:#f4f6fb;">'
        . '<table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f4f6fb;padding:24px 0;"><tr><td align="center">'
        . '<table role="presentation" width="600" cellspacing="0" cellpadding="0" style="max-width:600px;width:100%;background:#ffffff;border-radius:8px;font-family:Arial,Helvetica,sans-serif;color:#1e293b;">'
        . '<tr><td style="background:#4f46e5;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold;border-radius:8px 8px 0 0;">' . $app . '</td></tr>'
        . '<tr><td style="padding:24px;font-size:15px;line-height:1.6;">'
        . '<h2 style="margin:0 0 16px;font-size:20px;color:#1e293b;">' . e($heading) . '</h2>'
        . $bodyHtml
        . '</td></tr>'
        . '<tr><td style="padding:14px 24px;background:#f8fafc;color:#64748b;font-size:12px;border-radius:0 0 8px 8px;">'
        . 'This message was sent by ' . $app . '.</td></tr>'
        . '</table></td></tr></table></body></html>';
}

/** @param array<string,string> $pairs label => plain-text value (escaped here) */
function email_details_table(array $pairs): string
{
    $rows = '';
    foreach ($pairs as $label => $value) {
        $rows .= '<tr><td style="padding:6px 12px 6px 0;color:#64748b;white-space:nowrap;">' . e((string)$label) . '</td>'
            . '<td style="padding:6px 0;font-weight:bold;">' . e((string)$value) . '</td></tr>';
    }
    return '<table role="presentation" cellspacing="0" cellpadding="0" style="margin:12px 0 16px;">' . $rows . '</table>';
}

/**
 * Build the email for one member/presentation row, using the wording from the project plan.
 * $r comes from email_reminder_select_sql() (see below).
 *
 * @return array{subject:string, html:string}
 */
function build_reminder_email(array $r, string $type): array
{
    $due      = $r['calculated_due_date'] ?? null;
    $dueText  = format_date($due);
    $left     = ($due !== null && is_valid_date((string)$due)) ? days_until(date('Y-m-d'), (string)$due) : null;
    $overdue  = $left !== null && $left < 0;
    $relative = $due !== null ? due_in_text((string)$due) : '';

    $subject = $type === 'followup'
        ? 'Reminder: please confirm your presentation details'
        : 'Upcoming Presentation Reminder';

    $intro = $type === 'followup'
        ? 'This is a follow-up to our earlier presentation reminder.'
        : 'This email is regarding your upcoming presentation reminder.';

    // "As your last presentation was conducted on {date} at {venue}, under {chapter}, ..."
    $lastDate    = $r['last_presentation_date'] ?? null;
    $lastVenue   = trim((string)($r['m_venue'] ?? ''));
    $lastChapter = trim((string)($r['last_chapter_name'] ?? ''));

    if ($lastDate !== null && is_valid_date((string)$lastDate)) {
        $ask = 'As your last presentation was conducted on <strong>' . e(format_date((string)$lastDate)) . '</strong>'
            . ($lastVenue !== '' ? ' at <strong>' . e($lastVenue) . '</strong>' : '')
            . ($lastChapter !== '' ? ', under <strong>' . e($lastChapter) . '</strong>' : '')
            . ', we request you to kindly confirm your upcoming presentation date, venue, and chapter.';
    } else {
        $ask = 'We request you to kindly confirm your upcoming presentation date, venue, and chapter.';
    }

    $dueSentence = '';
    if ($due !== null && $dueText !== '—') {
        $dueSentence = $overdue
            ? 'Your presentation was due on <strong>' . e($dueText) . '</strong> (' . e($relative) . ').'
            : 'Your next presentation is due on <strong>' . e($dueText) . '</strong>' . ($relative !== '' ? ' (' . e($relative) . ')' : '') . '.';
    }

    if (!empty($r['coordinator_email'])) {
        $contact = 'You can reply to this email or contact <strong>' . e((string)$r['coordinator_name']) . '</strong> ('
            . e((string)$r['coordinator_email']) . ').';
    } else {
        $contact = '';
    }

    $body = '<p style="margin:0 0 12px;">Namaskar ' . e((string)$r['full_name']) . ',</p>'
        . '<p style="margin:0 0 12px;">' . $intro . '</p>'
        . '<p style="margin:0 0 12px;">' . $ask . '</p>'
        . ($dueSentence !== '' ? '<p style="margin:0 0 12px;">' . $dueSentence . '</p>' : '')
        . ($contact !== '' ? '<p style="margin:0 0 12px;">' . $contact . '</p>' : '')
        . '<p style="margin:0 0 12px;">Thank you for your cooperation.</p>'
        . '<p style="margin:16px 0 0;">Regards,<br>Presentation Coordination Team</p>';

    return ['subject' => $subject, 'html' => email_layout('Presentation reminder', $body)];
}

/**
 * Weekly summary for the admin alert address.
 *
 * @return array{subject:string, html:string}
 */
function build_overdue_digest_email(array $rows, int $total): array
{
    $tr = '';
    foreach ($rows as $r) {
        $tr .= '<tr>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . e((string)$r['full_name']) . '<br>'
            . '<span style="color:#64748b;font-size:12px;">' . e((string)$r['member_code']) . '</span></td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;">' . e((string)$r['chapter_name']) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;white-space:nowrap;">'
            . e(format_date((string)$r['calculated_due_date'])) . '</td>'
            . '<td style="padding:6px 8px;border-bottom:1px solid #e5e7eb;color:#b91c1c;white-space:nowrap;">'
            . e(due_in_text((string)$r['calculated_due_date'])) . '</td>'
            . '</tr>';
    }

    $more = $total > count($rows)
        ? '<p style="margin:12px 0 0;color:#64748b;">Showing the ' . count($rows) . ' longest overdue of ' . $total . '. Open Reports &rarr; Overdue for the full list.</p>'
        : '';

    $body = '<p style="margin:0 0 12px;"><strong>' . $total . '</strong> member' . ($total === 1 ? ' is' : 's are') . ' overdue for a presentation.</p>'
        . '<table cellspacing="0" cellpadding="0" width="100%" style="font-size:14px;border-collapse:collapse;">'
        . '<tr style="background:#f1f5f9;text-align:left;"><th style="padding:6px 8px;">Member</th><th style="padding:6px 8px;">Chapter</th>'
        . '<th style="padding:6px 8px;">Due date</th><th style="padding:6px 8px;">Status</th></tr>'
        . $tr . '</table>' . $more;

    return [
        'subject' => 'Overdue presentations: ' . $total . ' member' . ($total === 1 ? '' : 's') . ' need attention',
        'html'    => email_layout('Overdue presentations', $body),
    ];
}

// ---------------------------------------------------------------------------
// Logging + delivery
// ---------------------------------------------------------------------------

/**
 * Insert a "pending" log row. Returns the new id, or null when this dedupe_key already exists
 * (meaning: this automatic email was already created before, so do not send it again).
 */
function email_log_insert(array $d): ?int
{
    $stmt = db()->prepare(
        "INSERT INTO email_logs
            (member_id, presentation_id, recipient_email, email_type, subject, body, status, attempts, dedupe_key, sent_by)
         VALUES
            (:member_id, :presentation_id, :recipient, :type, :subject, :body, 'pending', 0, :dedupe, :sent_by)"
    );

    try {
        $stmt->execute([
            'member_id'       => $d['member_id'] ?? null,
            'presentation_id' => $d['presentation_id'] ?? null,
            'recipient'       => $d['recipient'],
            'type'            => $d['type'],
            'subject'         => mb_substr((string)$d['subject'], 0, 255),
            'body'            => $d['body'],
            'dedupe'          => $d['dedupe_key'] ?? null,
            'sent_by'         => $d['sent_by'] ?? null,
        ]);
    } catch (PDOException $ex) {
        if ((int)($ex->errorInfo[1] ?? 0) === 1062) {
            return null;   // duplicate dedupe_key
        }
        throw $ex;
    }

    return (int)db()->lastInsertId();
}

/**
 * Try to send one logged email and record the result.
 * Returns ['ok' => bool, 'error' => string, 'fatal' => bool].
 */
function email_deliver(int $logId): array
{
    $pdo  = db();
    $stmt = $pdo->prepare('SELECT * FROM email_logs WHERE id = :id LIMIT 1');
    $stmt->execute(['id' => $logId]);
    $row = $stmt->fetch();

    if (!$row) {
        return ['ok' => false, 'error' => 'Email record not found.', 'fatal' => false];
    }
    if ($row['status'] === 'sent') {
        return ['ok' => false, 'error' => 'This email was already sent.', 'fatal' => false];
    }

    [$ok, $error, $fatal] = send_email_now((string)$row['recipient_email'], (string)$row['subject'], (string)$row['body']);

    if ($ok) {
        $pdo->prepare(
            "UPDATE email_logs SET status = 'sent', error_message = NULL, attempts = attempts + 1, sent_at = :t WHERE id = :id"
        )->execute(['t' => date('Y-m-d H:i:s'), 'id' => $logId]);
    } else {
        // A broken connection/login is not the email's fault, so it does not use up a retry.
        $inc = $fatal ? 0 : 1;
        $pdo->prepare(
            "UPDATE email_logs SET status = 'failed', error_message = :e, attempts = attempts + {$inc} WHERE id = :id"
        )->execute(['e' => $error, 'id' => $logId]);
    }

    return ['ok' => $ok, 'error' => $error, 'fatal' => $fatal];
}

function load_email_log(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT e.*, m.full_name AS member_name, m.member_code, m.chapter_id, u.full_name AS sent_by_name
         FROM email_logs e
         LEFT JOIN members m ON m.id = e.member_id
         LEFT JOIN users u ON u.id = e.sent_by
         WHERE e.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }
    if ($row['chapter_id'] === null) {
        // Not linked to a member (test / admin alert): Super Admin only.
        return is_super_admin() ? $row : null;
    }
    return can_access_chapter((int)$row['chapter_id']) ? $row : null;
}

// ---------------------------------------------------------------------------
// Who should get which email
// ---------------------------------------------------------------------------

/** Shared SELECT: open presentation + active member + chapter + coordinator. */
function email_reminder_select_sql(): string
{
    return "SELECT p.id AS presentation_id, p.calculated_due_date, p.scheduled_date, p.status AS p_status,
                   p.details_confirmed, p.venue AS p_venue,
                   m.id AS member_id, m.full_name, m.member_code, m.email, m.venue AS m_venue,
                   m.last_presentation_date, lc.name AS last_chapter_name,
                   ch.name AS chapter_name,
                   u.full_name AS coordinator_name, u.email AS coordinator_email
            FROM presentations p
            JOIN members m   ON m.id = p.member_id
            JOIN chapters ch ON ch.id = m.chapter_id
            LEFT JOIN chapters lc ON lc.id = m.last_presentation_chapter_id
            LEFT JOIN users u ON u.id = m.coordinator_id AND u.is_active = 1
            WHERE p.status IN ('pending','scheduled')
              AND m.status = 'active'
              AND m.email <> ''";
}

/** The dedupe_key for an automatic email (must match the SQL below). */
function email_dedupe_key(string $type, int $presentationId, DateTimeImmutable $now): ?string
{
    return match ($type) {
        'monthly_reminder'    => 'monthly:' . $presentationId . ':' . $now->format('Y-m'),
        'thirty_day_reminder' => 'thirty:' . $presentationId,
        'followup'            => 'followup:' . $presentationId,
        default               => null,
    };
}

/**
 * Rows that should receive an automatic email of this kind and have NOT received it yet.
 * Members who already confirmed a future date + venue are skipped (no need to nag them).
 */
function email_candidates(string $type, DateTimeImmutable $now): array
{
    $today = $now->format('Y-m-d');
    $base  = email_reminder_select_sql()
        . " AND NOT (p.status = 'scheduled' AND p.details_confirmed = 1 AND p.scheduled_date >= :today)";
    $params = ['today' => $today];

    switch ($type) {
        case 'monthly_reminder':
            $sql = $base . " AND p.calculated_due_date BETWEEN :d1 AND :d2
                    AND NOT EXISTS (SELECT 1 FROM email_logs e WHERE e.dedupe_key = CONCAT('monthly:', p.id, ':', :ym))
                    ORDER BY p.calculated_due_date, m.full_name";
            $params += ['d1' => $now->format('Y-m-01'), 'd2' => $now->format('Y-m-t'), 'ym' => $now->format('Y-m')];
            break;

        case 'thirty_day_reminder':
            $sql = $base . " AND p.calculated_due_date BETWEEN :d1 AND :d2
                    AND NOT EXISTS (SELECT 1 FROM email_logs e WHERE e.dedupe_key = CONCAT('thirty:', p.id))
                    ORDER BY p.calculated_due_date, m.full_name";
            $params += ['d1' => $today, 'd2' => (string)add_calendar_days($today, 30)];
            break;

        case 'followup':
            $days = max(1, min(60, (int)email_setting('followup_after_days', '7')));
            $sql = $base . " AND p.details_confirmed = 0
                    AND EXISTS (SELECT 1 FROM email_logs e1
                                WHERE e1.presentation_id = p.id AND e1.status = 'sent'
                                  AND e1.email_type IN ('monthly_reminder','thirty_day_reminder')
                                  AND e1.sent_at <= :cutoff)
                    AND NOT EXISTS (SELECT 1 FROM email_logs e2 WHERE e2.dedupe_key = CONCAT('followup:', p.id))
                    ORDER BY p.calculated_due_date, m.full_name";
            $params += ['cutoff' => $now->modify('-' . $days . ' days')->format('Y-m-d H:i:s')];
            break;

        default:
            return [];
    }

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** One member's row for a manual email (same columns as email_candidates). */
function email_row_for_presentation(int $presentationId): ?array
{
    $stmt = db()->prepare(email_reminder_select_sql() . ' AND p.id = :pid LIMIT 1');
    $stmt->execute(['pid' => $presentationId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** Overdue open presentations of active members (for the admin summary). */
function email_overdue_rows(int $limit = 50): array
{
    $f   = presentation_filter_sql('overdue', 'p');
    $sql = "FROM presentations p
            JOIN members m   ON m.id = p.member_id
            JOIN chapters ch ON ch.id = m.chapter_id
            WHERE m.status = 'active' AND {$f['sql']}";

    $count = db()->prepare("SELECT COUNT(*) {$sql}");
    $count->execute($f['params']);
    $total = (int)$count->fetchColumn();

    $list = db()->prepare(
        "SELECT m.full_name, m.member_code, ch.name AS chapter_name, p.calculated_due_date
         {$sql} ORDER BY p.calculated_due_date ASC LIMIT " . (int)$limit
    );
    $list->execute($f['params']);

    return ['total' => $total, 'rows' => $list->fetchAll()];
}

// ---------------------------------------------------------------------------
// The reminder run (cron job + "Send due reminders now" button)
// ---------------------------------------------------------------------------

/**
 * Send all emails that are due. Safe to run as often as you like: each email is
 * only ever created once (dedupe_key), and failures are retried up to 3 times.
 *
 * With $dryRun = true nothing is sent or saved; it only counts what WOULD be sent.
 *
 * @return array{ok:bool, message:string, sent:int, failed:int, by_type:array, notes:string[]}
 */
function run_email_reminders(?int $actorId = null, bool $dryRun = false): array
{
    @set_time_limit(300);

    $now  = new DateTimeImmutable('now');
    $res  = ['ok' => true, 'message' => '', 'sent' => 0, 'failed' => 0, 'by_type' => [], 'notes' => []];
    $limit = max(1, (int)MAIL_MAX_PER_RUN);
    $done  = 0;      // emails attempted in this run
    $stop  = false;  // set when we must stop (daily limit / broken connection)

    if (!$dryRun && !mail_configured()) {
        $res['ok'] = false;
        $res['message'] = 'Email is not set up yet: add the Gmail App Password in config/mail.php.';
        return $res;
    }

    $count = static function (string $type, string $key) use (&$res): void {
        $res['by_type'][$type] ??= ['sent' => 0, 'failed' => 0, 'would_send' => 0];
        $res['by_type'][$type][$key]++;
        if ($key === 'sent') {
            $res['sent']++;
        } elseif ($key === 'failed') {
            $res['failed']++;
        }
    };

    // 1) Retry earlier automatic emails that failed (up to 3 attempts, within the last 7 days).
    if (!$dryRun) {
        $retry = db()->prepare(
            "SELECT id, email_type FROM email_logs
             WHERE status IN ('failed','pending')
               AND attempts < :max
               AND email_type IN ('monthly_reminder','thirty_day_reminder','followup','overdue_admin')
               AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
               AND (status = 'failed' OR created_at <= DATE_SUB(NOW(), INTERVAL 15 MINUTE))
             ORDER BY id"
        );
        $retry->execute(['max' => EMAIL_MAX_ATTEMPTS]);

        foreach ($retry->fetchAll() as $r) {
            if ($stop || $done >= $limit) {
                break;
            }
            $out = email_deliver((int)$r['id']);
            $done++;
            $count((string)$r['email_type'], $out['ok'] ? 'sent' : 'failed');
            if (!$out['ok'] && $out['fatal']) {
                $stop = true;
                $res['notes'][] = 'Stopped: ' . $out['error'];
            }
        }
    }

    // 2) New member emails.
    $jobs = [
        ['monthly_reminder',    'enable_monthly_reminders', true],
        ['thirty_day_reminder', 'enable_30day_reminders',   false],
        ['followup',            'enable_followup_emails',   true],
    ];

    foreach ($jobs as [$type, $settingKey, $defaultOn]) {
        if ($stop || !email_setting_on($settingKey, $defaultOn)) {
            continue;
        }

        foreach (email_candidates($type, $now) as $row) {
            if ($dryRun) {
                $count($type, 'would_send');
                continue;
            }
            if ($done >= $limit) {
                $res['notes'][] = 'Reached the limit of ' . $limit . ' emails for this run. The rest will go out on the next run.';
                $stop = true;
                break;
            }

            $mail = build_reminder_email($row, $type);
            $id   = email_log_insert([
                'member_id'       => (int)$row['member_id'],
                'presentation_id' => (int)$row['presentation_id'],
                'recipient'       => (string)$row['email'],
                'type'            => $type,
                'subject'         => $mail['subject'],
                'body'            => $mail['html'],
                'dedupe_key'      => email_dedupe_key($type, (int)$row['presentation_id'], $now),
                'sent_by'         => $actorId,
            ]);
            if ($id === null) {
                continue;   // already created by another run
            }

            $out = email_deliver($id);
            $done++;
            $count($type, $out['ok'] ? 'sent' : 'failed');

            if (!$out['ok'] && $out['fatal']) {
                $stop = true;
                $res['notes'][] = 'Stopped: ' . $out['error'];
                break;
            }
        }
    }

    // 3) Weekly overdue summary for the admin.
    $adminTo = trim(email_setting('admin_alert_email', ''));
    if (!$stop && $adminTo !== '' && filter_var($adminTo, FILTER_VALIDATE_EMAIL)) {
        $key   = 'overdue_admin:' . $now->format('o-\WW');   // once per ISO week
        $exist = db()->prepare('SELECT 1 FROM email_logs WHERE dedupe_key = :k LIMIT 1');
        $exist->execute(['k' => $key]);

        if (!$exist->fetchColumn()) {
            $overdue = email_overdue_rows(50);

            if ($overdue['total'] > 0) {
                if ($dryRun) {
                    $count('overdue_admin', 'would_send');
                } elseif ($done < $limit) {
                    $mail = build_overdue_digest_email($overdue['rows'], $overdue['total']);
                    $id   = email_log_insert([
                        'recipient'  => $adminTo,
                        'type'       => 'overdue_admin',
                        'subject'    => $mail['subject'],
                        'body'       => $mail['html'],
                        'dedupe_key' => $key,
                        'sent_by'    => $actorId,
                    ]);
                    if ($id !== null) {
                        $out = email_deliver($id);
                        $done++;
                        $count('overdue_admin', $out['ok'] ? 'sent' : 'failed');
                    }
                }
            }
        }
    }

    if (!$dryRun) {
        smtp_mailer()->close();
    }

    // Summary text
    if ($dryRun) {
        $total = 0;
        foreach ($res['by_type'] as $t) {
            $total += $t['would_send'];
        }
        $res['message'] = $total === 0
            ? 'Nothing is waiting to be sent.'
            : $total . ' email(s) are waiting to be sent.';
    } elseif ($res['sent'] === 0 && $res['failed'] === 0) {
        $res['message'] = 'Nothing to send right now.';
    } else {
        $res['message'] = $res['sent'] . ' sent, ' . $res['failed'] . ' failed.';
    }
    if ($res['failed'] > 0 || $stop) {
        $res['ok'] = $res['failed'] === 0;
    }

    return $res;
}
