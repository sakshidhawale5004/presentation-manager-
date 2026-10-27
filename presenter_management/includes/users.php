<?php
declare(strict_types=1);

/**
 * Module 7 helpers: admin users, chapters / categories, activity log.
 */

/* ------------------------------------------------------------------ */
/* Activity log                                                        */
/* ------------------------------------------------------------------ */

/** Write one line to the activity log. Never stops the page if logging fails. */
function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, string $details = ''): void
{
    try {
        $u = function_exists('current_user') ? current_user() : null;
        $stmt = db()->prepare(
            'INSERT INTO audit_logs (user_id, user_name, action, entity_type, entity_id, details, ip_address)
             VALUES (:uid, :uname, :action, :etype, :eid, :details, :ip)'
        );
        $stmt->execute([
            'uid'     => $u['id'] ?? null,
            'uname'   => $u['full_name'] ?? null,
            'action'  => mb_substr($action, 0, 60),
            'etype'   => $entityType,
            'eid'     => $entityId,
            'details' => $details !== '' ? mb_substr($details, 0, 500) : null,
            'ip'      => mb_substr((string)($_SERVER['REMOTE_ADDR'] ?? ''), 0, 45) ?: null,
        ]);
    } catch (Throwable $t) {
        error_log('audit_log failed: ' . $t->getMessage());
    }
}

/* ------------------------------------------------------------------ */
/* Users                                                               */
/* ------------------------------------------------------------------ */

function user_roles(): array
{
    return [
        'super_admin'         => 'Super Admin',
        'chapter_coordinator' => 'Chapter Coordinator',
    ];
}

function load_user(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT u.*, c.name AS chapter_name,
                (u.locked_until IS NOT NULL AND u.locked_until > NOW()) AS is_locked,
                (SELECT COUNT(*) FROM members m WHERE m.coordinator_id = u.id) AS member_count
         FROM users u
         LEFT JOIN chapters c ON c.id = u.chapter_id
         WHERE u.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function count_active_super_admins(?int $exceptUserId = null): int
{
    $sql    = "SELECT COUNT(*) FROM users WHERE role = 'super_admin' AND is_active = 1";
    $params = [];
    if ($exceptUserId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $exceptUserId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn();
}

function user_email_taken(string $email, ?int $ignoreId = null): bool
{
    $sql    = 'SELECT id FROM users WHERE email = :e';
    $params = ['e' => $email];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    $stmt = db()->prepare($sql . ' LIMIT 1');
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

/** Same rule as install_admin.php. Returns an error message or null. */
function password_problem(string $password): ?string
{
    if (strlen($password) < 10 || strlen($password) > 72) {
        return 'Password must be 10 to 72 characters.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'Password must contain at least one letter and one number.';
    }
    return null;
}

/** Random password that always passes password_problem(). */
function generate_password(int $length = 12): string
{
    $letters = 'abcdefghijkmnpqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';
    $digits  = '23456789';
    $all     = $letters . $digits;
    $out     = [$letters[random_int(0, strlen($letters) - 1)], $digits[random_int(0, strlen($digits) - 1)]];
    while (count($out) < $length) {
        $out[] = $all[random_int(0, strlen($all) - 1)];
    }
    for ($i = count($out) - 1; $i > 0; $i--) {
        $j = random_int(0, $i);
        [$out[$i], $out[$j]] = [$out[$j], $out[$i]];
    }
    return implode('', $out);
}

/**
 * Validate the add/edit user form.
 * Returns [cleanData, errors].
 */
function validate_user_input(array $in, ?int $editId = null): array
{
    $roles  = user_roles();
    $errors = [];

    $data = [
        'full_name'  => trim((string)($in['full_name'] ?? '')),
        'email'      => strtolower(trim((string)($in['email'] ?? ''))),
        'role'       => (string)($in['role'] ?? 'chapter_coordinator'),
        'chapter_id' => (int)($in['chapter_id'] ?? 0),
    ];

    if (mb_strlen($data['full_name']) < 2 || mb_strlen($data['full_name']) > 100) {
        $errors[] = 'Full name must be 2 to 100 characters.';
    }
    if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL) || strlen($data['email']) > 190) {
        $errors[] = 'Please enter a valid email address.';
    } elseif (user_email_taken($data['email'], $editId)) {
        $errors[] = 'Another user already uses this email address.';
    }
    if (!isset($roles[$data['role']])) {
        $errors[] = 'Please choose a valid role.';
    }

    if ($data['role'] === 'chapter_coordinator') {
        if ($data['chapter_id'] <= 0) {
            $errors[] = 'A Chapter Coordinator must be assigned to a chapter.';
        } else {
            $stmt = db()->prepare('SELECT id FROM chapters WHERE id = :id AND is_active = 1 LIMIT 1');
            $stmt->execute(['id' => $data['chapter_id']]);
            if (!$stmt->fetch()) {
                $errors[] = 'The selected chapter is not available.';
            }
        }
    } else {
        $data['chapter_id'] = 0;   // Super Admin works across all chapters
    }

    return [$data, $errors];
}

function user_status_badge(array $u): string
{
    if ((int)$u['is_active'] !== 1) {
        return '<span class="badge text-bg-secondary">Inactive</span>';
    }
    if (!empty($u['is_locked'])) {
        return '<span class="badge text-bg-warning">Locked</span>';
    }
    return '<span class="badge text-bg-success">Active</span>';
}

/* ------------------------------------------------------------------ */
/* Chapters & categories                                               */
/* ------------------------------------------------------------------ */

function fetch_all_chapters(): array
{
    return db()->query(
        'SELECT c.*,
                (SELECT COUNT(*) FROM members m WHERE m.chapter_id = c.id) AS member_count,
                (SELECT COUNT(*) FROM users u WHERE u.chapter_id = c.id) AS user_count
         FROM chapters c
         ORDER BY c.is_active DESC, c.name'
    )->fetchAll();
}

function fetch_all_categories(): array
{
    return db()->query(
        'SELECT cat.*,
                (SELECT COUNT(*) FROM members m WHERE m.category_id = cat.id) AS member_count
         FROM categories cat
         ORDER BY cat.is_active DESC, cat.name'
    )->fetchAll();
}

/** Uppercase letters, digits and dashes only, e.g. "MUM-C". */
function normalize_chapter_code(string $code): string
{
    return strtoupper(preg_replace('/[^A-Za-z0-9-]+/', '', trim($code)) ?? '');
}

/** Short readable text for the log, e.g. "Role: Super Admin -> Chapter Coordinator". */
function change_note(string $label, mixed $old, mixed $new): ?string
{
    return (string)$old === (string)$new ? null : $label . ': ' . ($old === '' || $old === null ? '(none)' : $old) . ' -> ' . ($new === '' || $new === null ? '(none)' : $new);
}
