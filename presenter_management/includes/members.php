<?php
declare(strict_types=1);

/**
 * Member helpers used by the list, form, view, and action pages.
 */

function fetch_active_chapters(): array
{
    return db()->query(
        'SELECT id, name, code FROM chapters WHERE is_active = 1 ORDER BY name'
    )->fetchAll();
}

function fetch_active_categories(): array
{
    return db()->query(
        'SELECT id, name FROM categories WHERE is_active = 1 ORDER BY name'
    )->fetchAll();
}

/** Active admin users who can be assigned as coordinators. */
function fetch_coordinators(?int $chapterId = null): array
{
    $sql = 'SELECT id, full_name, role, chapter_id
            FROM users
            WHERE is_active = 1';
    $params = [];

    if ($chapterId !== null) {
        $sql .= ' AND (chapter_id = :cid OR chapter_id IS NULL OR role = :role)';
        $params['cid'] = $chapterId;
        $params['role'] = 'super_admin';
    }

    $sql .= ' ORDER BY full_name';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function next_member_code(): string
{
    $stmt = db()->query(
        "SELECT member_code FROM members WHERE member_code LIKE 'MEM-%' ORDER BY id DESC LIMIT 1"
    );
    $last = $stmt->fetchColumn();
    $n = 1;

    if (is_string($last) && preg_match('/MEM-(\d+)/', $last, $m)) {
        $n = (int)$m[1] + 1;
    }

    do {
        $code = 'MEM-' . str_pad((string)$n, 4, '0', STR_PAD_LEFT);
        $check = db()->prepare('SELECT id FROM members WHERE member_code = :c LIMIT 1');
        $check->execute(['c' => $code]);
        $exists = (bool)$check->fetch();
        $n++;
    } while ($exists);

    return $code;
}

function member_status_badge(string $status): string
{
    $map = [
        'active'   => 'success',
        'inactive' => 'secondary',
    ];
    $color = $map[$status] ?? 'secondary';
    return '<span class="badge text-bg-' . e($color) . '">' . e(ucfirst($status)) . '</span>';
}

function can_access_chapter(?int $chapterId): bool
{
    if (is_super_admin()) {
        return true;
    }

    $mine = current_user()['chapter_id'] ?? null;
    return $mine !== null && $chapterId !== null && (int)$mine === $chapterId;
}

function load_member(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT m.*,
                c.name AS chapter_name,
                cat.name AS category_name,
                lc.name AS last_chapter_name,
                u.full_name AS coordinator_name,
                creator.full_name AS created_by_name
         FROM members m
         JOIN chapters c ON c.id = m.chapter_id
         LEFT JOIN chapters lc ON lc.id = m.last_presentation_chapter_id
         JOIN categories cat ON cat.id = m.category_id
         LEFT JOIN users u ON u.id = m.coordinator_id
         LEFT JOIN users creator ON creator.id = m.created_by
         WHERE m.id = :id
         LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();

    if (!$row) {
        return null;
    }
    if (!can_access_chapter((int)$row['chapter_id'])) {
        return null;
    }

    return $row;
}

function member_code_taken(string $code, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM members WHERE member_code = :code';
    $params = ['code' => $code];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

function member_email_taken(string $email, ?int $ignoreId = null): bool
{
    $sql = 'SELECT id FROM members WHERE email = :email';
    $params = ['email' => $email];
    if ($ignoreId !== null) {
        $sql .= ' AND id <> :id';
        $params['id'] = $ignoreId;
    }
    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (bool)$stmt->fetch();
}

function normalize_mobile(string $mobile): string
{
    return preg_replace('/[^\d+]/', '', $mobile) ?? '';
}

/**
 * Validate and clean member form data.
 * Returns [cleaned array, list of error messages].
 */
function validate_member_input(array $post, ?int $ignoreId = null): array
{
    $errors = [];

    $name     = trim((string)($post['full_name'] ?? ''));
    $mobile   = normalize_mobile((string)($post['mobile'] ?? ''));
    $email    = strtolower(trim((string)($post['email'] ?? '')));
    $category = (int)($post['category_id'] ?? 0);
    $chapter  = (int)($post['chapter_id'] ?? 0);
    $lastDate = trim((string)($post['last_presentation_date'] ?? ''));
    $venue    = trim((string)($post['venue'] ?? ''));
    $status   = (string)($post['status'] ?? 'active');
    $remarks  = trim((string)($post['remarks'] ?? ''));
    $lastChRaw = trim((string)($post['last_presentation_chapter_id'] ?? ''));
    $lastChId  = $lastChRaw === '' ? null : (int)$lastChRaw;

    if (!is_super_admin()) {
        $mine = current_user()['chapter_id'] ?? null;
        $chapter = $mine !== null ? (int)$mine : 0;
    }

    // Member ID is never typed by hand: new members get the next free ID, existing members keep theirs.
    if ($ignoreId === null) {
        $code = next_member_code();
    } else {
        $cs = db()->prepare('SELECT member_code FROM members WHERE id = :id LIMIT 1');
        $cs->execute(['id' => $ignoreId]);
        $code = (string)$cs->fetchColumn();
    }

    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Full name must be 2 to 150 characters.';
    }

    if (!preg_match('/^\+?[0-9]{10,15}$/', $mobile)) {
        $errors[] = 'Enter a valid mobile number (10–15 digits, optional +).';
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    } elseif (member_email_taken($email, $ignoreId)) {
        $errors[] = 'That email is already used by another member.';
    }

    $catStmt = db()->prepare('SELECT id FROM categories WHERE id = :id AND is_active = 1 LIMIT 1');
    $catStmt->execute(['id' => $category]);
    if (!$catStmt->fetch()) {
        $errors[] = 'Please choose a valid category.';
    }

    $chStmt = db()->prepare('SELECT id FROM chapters WHERE id = :id AND is_active = 1 LIMIT 1');
    $chStmt->execute(['id' => $chapter]);
    if (!$chStmt->fetch()) {
        $errors[] = 'Please choose a valid chapter.';
    } elseif (!can_access_chapter($chapter)) {
        $errors[] = 'You cannot save a member in another chapter.';
    }

    if ($lastDate === '') {
        $lastDate = null;
    } elseif (!is_valid_date($lastDate)) {
        $errors[] = 'Last presentation date must be a valid date.';
        $lastDate = null;
    } elseif ($lastDate > date('Y-m-d')) {
        $errors[] = 'Last presentation date cannot be in the future.';
    }

    if (mb_strlen($venue) > 190) {
        $errors[] = 'Venue must be 190 characters or fewer.';
    }

    if (!in_array($status, ['active', 'inactive'], true)) {
        $errors[] = 'Please choose a valid status.';
        $status = 'active';
    }

    if (mb_strlen($remarks) > 2000) {
        $errors[] = 'Remarks must be 2000 characters or fewer.';
    }

    if ($lastChId !== null) {
        $lc = db()->prepare('SELECT 1 FROM chapters WHERE id = :id AND is_active = 1 LIMIT 1');
        $lc->execute(['id' => $lastChId]);
        if (!$lc->fetchColumn()) {
            $errors[] = 'Please choose a valid last presentation chapter.';
            $lastChId = null;
        }
    }

    $data = [
        'member_code'            => $code,
        'full_name'              => $name,
        'mobile'                 => $mobile,
        'email'                  => $email,
        'category_id'            => $category,
        'chapter_id'             => $chapter,
        'last_presentation_date' => $lastDate,
        'next_due_date'          => add_calendar_days($lastDate),
        'venue'                  => $venue === '' ? null : $venue,
        'last_presentation_chapter_id' => $lastChId,
        'status'                 => $status,
        'remarks'                => $remarks === '' ? null : $remarks,
        // Coordinator is no longer on the form: new members of a Chapter Coordinator go to that coordinator.
        'coordinator_id'         => (!is_super_admin() && $ignoreId === null) ? (int)current_user()['id'] : null,
    ];

    return [$data, $errors];
}

function save_member(array $data, ?int $id = null): int
{
    $pdo = db();

    if ($id === null) {
        $sql = 'INSERT INTO members (
                    member_code, full_name, mobile, email, category_id, chapter_id,
                    last_presentation_date, next_due_date, venue, last_presentation_chapter_id, status, remarks,
                    coordinator_id, created_by
                ) VALUES (
                    :member_code, :full_name, :mobile, :email, :category_id, :chapter_id,
                    :last_presentation_date, :next_due_date, :venue, :last_presentation_chapter_id, :status, :remarks,
                    :coordinator_id, :created_by
                )';
        $params = $data + ['last_presentation_chapter_id' => null];
        $params['created_by'] = current_user()['id'];
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $newId = (int)$pdo->lastInsertId();
        sync_member_history($newId, $data['last_presentation_date'], $data['venue'], $data['last_presentation_chapter_id'] ?? null);
        return $newId;
    }

    // Once a member has presentation history, the last/next dates are controlled
    // ONLY by the Presentations module (so history is never overwritten).
    if (member_has_history($id)) {
        $cur = $pdo->prepare('SELECT last_presentation_date, next_due_date FROM members WHERE id = :id');
        $cur->execute(['id' => $id]);
        $row = $cur->fetch();
        $data['last_presentation_date'] = $row['last_presentation_date'] ?? null;
        $data['next_due_date']          = $row['next_due_date'] ?? null;
    }

    $sql = 'UPDATE members SET
                member_code = :member_code,
                full_name = :full_name,
                mobile = :mobile,
                email = :email,
                category_id = :category_id,
                chapter_id = :chapter_id,
                last_presentation_date = :last_presentation_date,
                next_due_date = :next_due_date,
                venue = :venue,
                last_presentation_chapter_id = :last_presentation_chapter_id,
                status = :status,
                remarks = :remarks
            WHERE id = :id';
    $params = $data + ['last_presentation_chapter_id' => null];
    unset($params['coordinator_id']);
    $params['id'] = $id;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    sync_member_history($id, $data['last_presentation_date'], $data['venue'], $data['last_presentation_chapter_id'] ?? null);
    return $id;
}
