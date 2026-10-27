<?php
declare(strict_types=1);

/**
 * Public member registration (no login needed to SUBMIT).
 * Submissions wait in member_registrations until an admin approves them.
 */

const REG_MAX_PER_IP_PER_HOUR = 5;

function registration_email_taken(string $email): bool
{
    $stmt = db()->prepare(
        "SELECT 1 FROM members WHERE email = :e1
         UNION ALL
         SELECT 1 FROM member_registrations WHERE email = :e2 AND status = 'pending'
         LIMIT 1"
    );
    $stmt->execute(['e1' => $email, 'e2' => $email]);
    return (bool)$stmt->fetchColumn();
}

/** Next free Member ID, counting both real members and registrations still waiting. */
function next_registration_code(): string
{
    $max = 0;
    $sqls = [
        'SELECT member_code FROM members',
        'SELECT member_code FROM member_registrations WHERE member_code IS NOT NULL',
    ];
    foreach ($sqls as $sql) {
        foreach (db()->query($sql)->fetchAll(PDO::FETCH_COLUMN) as $c) {
            if (preg_match('/^MEM-(\d+)$/', (string)$c, $m)) {
                $max = max($max, (int)$m[1]);
            }
        }
    }
    return 'MEM-' . str_pad((string)($max + 1), 4, '0', STR_PAD_LEFT);
}

/** Too many sign-ups from one IP address in the last hour? */
function registration_rate_limited(string $ip): bool
{
    $stmt = db()->prepare(
        'SELECT COUNT(*) FROM member_registrations
         WHERE ip_address = :ip AND created_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)'
    );
    $stmt->execute(['ip' => $ip]);
    return (int)$stmt->fetchColumn() >= REG_MAX_PER_IP_PER_HOUR;
}

/** Validate the public form. Returns [clean data, errors]. */
function validate_registration_input(array $post): array
{
    $errors = [];

    $name     = trim((string)($post['full_name'] ?? ''));
    $mobile   = normalize_mobile((string)($post['mobile'] ?? ''));
    $email    = strtolower(trim((string)($post['email'] ?? '')));
    $category = (int)($post['category_id'] ?? 0);
    $chapter  = (int)($post['chapter_id'] ?? 0);
    $lastDate = trim((string)($post['last_presentation_date'] ?? ''));
    $venue    = trim((string)($post['venue'] ?? ''));
    $lastChRaw = trim((string)($post['last_presentation_chapter_id'] ?? ''));
    $lastChId  = $lastChRaw === '' ? null : (int)$lastChRaw;
    $remarks  = trim((string)($post['remarks'] ?? ''));
    $mStatus  = (string)($post['member_status'] ?? 'active');

    if (!in_array($mStatus, ['active', 'inactive'], true)) {
        $errors[] = 'Please choose a valid member status.';
        $mStatus = 'active';
    }
    if (mb_strlen($name) < 2 || mb_strlen($name) > 150) {
        $errors[] = 'Full name must be 2 to 150 characters.';
    }
    if (!preg_match('/^\+?[0-9]{10,15}$/', $mobile)) {
        $errors[] = 'Enter a valid mobile number (10–15 digits, optional +).';
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) {
        $errors[] = 'Enter a valid email address.';
    } elseif (registration_email_taken($email)) {
        $errors[] = 'This email is already registered or is waiting for approval.';
    }

    $c = db()->prepare('SELECT 1 FROM categories WHERE id = :id AND is_active = 1');
    $c->execute(['id' => $category]);
    if (!$c->fetchColumn()) {
        $errors[] = 'Please choose a category.';
    }
    $h = db()->prepare('SELECT 1 FROM chapters WHERE id = :id AND is_active = 1');
    $h->execute(['id' => $chapter]);
    if (!$h->fetchColumn()) {
        $errors[] = 'Please choose a chapter.';
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
    if ($lastChId !== null) {
        $lc = db()->prepare('SELECT 1 FROM chapters WHERE id = :id AND is_active = 1');
        $lc->execute(['id' => $lastChId]);
        if (!$lc->fetchColumn()) {
            $errors[] = 'Please choose a valid last presentation chapter.';
            $lastChId = null;
        }
    }
    if (mb_strlen($remarks) > 1000) {
        $errors[] = 'Remarks must be 1000 characters or fewer.';
    }

    return [[
        'full_name' => $name, 'mobile' => $mobile, 'email' => $email,
        'category_id' => $category, 'chapter_id' => $chapter,
        'last_presentation_date' => $lastDate,
        'venue' => $venue === '' ? null : $venue,
        'last_presentation_chapter_id' => $lastChId,
        'member_status' => $mStatus,
        'remarks' => $remarks === '' ? null : $remarks,
    ], $errors];
}

/** Saves the registration and returns the Member ID that was reserved for it. */
function save_registration(array $data, string $ip): string
{
    $code = next_registration_code();
    $stmt = db()->prepare(
        'INSERT INTO member_registrations
            (member_code, full_name, mobile, email, category_id, chapter_id, last_presentation_date, venue, last_presentation_chapter_id, member_status, remarks, ip_address)
         VALUES
            (:member_code, :full_name, :mobile, :email, :category_id, :chapter_id, :last_presentation_date, :venue, :last_presentation_chapter_id, :member_status, :remarks, :ip)'
    );
    $stmt->execute($data + ['ip' => $ip, 'member_code' => $code]);
    return $code;
}

/** Load a registration the logged-in admin is allowed to see. */
function load_registration(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT r.*, c.name AS chapter_name, cat.name AS category_name
         FROM member_registrations r
         JOIN chapters c ON c.id = r.chapter_id
         JOIN categories cat ON cat.id = r.category_id
         WHERE r.id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $id]);
    $row = $stmt->fetch();
    return ($row && can_access_chapter((int)$row['chapter_id'])) ? $row : null;
}

/** Turn a pending registration into a real member (+ history). Returns [ok, message]. */
function approve_registration(int $id): array
{
    try {
        $msg = with_transaction(function (PDO $pdo) use ($id) {
            $stmt = $pdo->prepare('SELECT * FROM member_registrations WHERE id = :id FOR UPDATE');
            $stmt->execute(['id' => $id]);
            $r = $stmt->fetch();

            if (!$r || $r['status'] !== 'pending') {
                throw new DomainException('This registration has already been reviewed.');
            }
            if (!can_access_chapter((int)$r['chapter_id'])) {
                throw new DomainException('You cannot approve registrations for another chapter.');
            }

            $dup = $pdo->prepare('SELECT 1 FROM members WHERE email = :e');
            $dup->execute(['e' => $r['email']]);
            if ($dup->fetchColumn()) {
                throw new DomainException('A member with this email already exists. Reject this registration instead.');
            }

            $memberId = save_member([
                'member_code'            => (!empty($r['member_code']) && !member_code_taken((string)$r['member_code']))
                                                ? (string)$r['member_code'] : next_member_code(),
                'full_name'              => $r['full_name'],
                'mobile'                 => $r['mobile'],
                'email'                  => $r['email'],
                'category_id'            => (int)$r['category_id'],
                'chapter_id'             => (int)$r['chapter_id'],
                'last_presentation_date' => $r['last_presentation_date'],
                'next_due_date'          => add_calendar_days($r['last_presentation_date']),
                'venue'                  => $r['venue'],
                'last_presentation_chapter_id' => $r['last_presentation_chapter_id'] !== null ? (int)$r['last_presentation_chapter_id'] : null,
                'status'                 => in_array($r['member_status'] ?? 'active', ['active', 'inactive'], true) ? $r['member_status'] : 'active',
                'remarks'                => $r['remarks'],
                'coordinator_id'         => is_super_admin() ? null : (int)current_user()['id'],
            ]);

            $pdo->prepare(
                "UPDATE member_registrations
                 SET status = 'approved', member_id = :mid, reviewed_by = :uid, reviewed_at = NOW()
                 WHERE id = :id"
            )->execute(['mid' => $memberId, 'uid' => current_user()['id'], 'id' => $id]);

            return 'Approved. ' . $r['full_name'] . ' is now a member.';
        });
        return [true, (string)$msg];
    } catch (DomainException $ex) {
        return [false, $ex->getMessage()];
    } catch (PDOException $ex) {
        error_log('approve_registration: ' . $ex->getMessage());
        return [false, 'Could not approve this registration. Please try again.'];
    }
}

function reject_registration(int $id, string $reason): array
{
    $reason = trim($reason);
    if (mb_strlen($reason) > 255) {
        return [false, 'Reason must be 255 characters or fewer.'];
    }

    $stmt = db()->prepare(
        "UPDATE member_registrations
         SET status = 'rejected', rejection_reason = :reason, reviewed_by = :uid, reviewed_at = NOW()
         WHERE id = :id AND status = 'pending'"
    );
    $stmt->execute(['reason' => $reason === '' ? null : $reason, 'uid' => current_user()['id'], 'id' => $id]);

    return $stmt->rowCount() === 1
        ? [true, 'Registration rejected.']
        : [false, 'This registration has already been reviewed.'];
}
