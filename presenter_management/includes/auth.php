<?php
declare(strict_types=1);

/** Start the session with safe cookie settings. */
function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isHttps = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');

    session_name('PMSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => '/',
        'secure'   => $isHttps,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();
}

/** Keep only the safe fields in the session (never the password hash). */
function user_session_data(array $row): array
{
    return [
        'id'         => (int)$row['id'],
        'full_name'  => (string)$row['full_name'],
        'email'      => (string)$row['email'],
        'role'       => (string)$row['role'],
        'chapter_id' => $row['chapter_id'] !== null ? (int)$row['chapter_id'] : null,
    ];
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']['id']);
}

function is_super_admin(): bool
{
    return (current_user()['role'] ?? '') === 'super_admin';
}

/**
 * Try to log in. Returns [true|false, message].
 */
function attempt_login(string $email, string $password): array
{
    $pdo = db();

    $stmt = $pdo->prepare(
        'SELECT *,
                (locked_until IS NOT NULL AND locked_until > NOW()) AS is_locked,
                CEIL(TIMESTAMPDIFF(SECOND, NOW(), locked_until) / 60) AS lock_minutes
         FROM users
         WHERE email = :email
         LIMIT 1'
    );
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch();

    if (!$user) {
        static $dummyHash = null;
        $dummyHash ??= password_hash(bin2hex(random_bytes(8)), PASSWORD_DEFAULT);
        password_verify($password, $dummyHash);
        return [false, 'Invalid email or password.'];
    }

    if ((int)$user['is_locked'] === 1) {
        return [false, 'Too many failed attempts. Please try again in ' . (int)$user['lock_minutes'] . ' minute(s).'];
    }

    if (!password_verify($password, $user['password_hash'])) {
        $attempts = (int)$user['failed_attempts'] + 1;

        if ($attempts >= MAX_LOGIN_ATTEMPTS) {
            $pdo->prepare(
                'UPDATE users
                 SET failed_attempts = 0,
                     locked_until = DATE_ADD(NOW(), INTERVAL ' . (int)LOCKOUT_MINUTES . ' MINUTE)
                 WHERE id = :id'
            )->execute(['id' => $user['id']]);
            return [false, 'Too many failed attempts. Your account is locked for ' . LOCKOUT_MINUTES . ' minutes.'];
        }

        $pdo->prepare('UPDATE users SET failed_attempts = :a WHERE id = :id')
            ->execute(['a' => $attempts, 'id' => $user['id']]);
        return [false, 'Invalid email or password.'];
    }

    if ((int)$user['is_active'] !== 1) {
        return [false, 'This account is deactivated. Please contact the Super Admin.'];
    }

    $pdo->prepare('UPDATE users SET failed_attempts = 0, locked_until = NULL, last_login_at = NOW() WHERE id = :id')
        ->execute(['id' => $user['id']]);

    if (password_needs_rehash($user['password_hash'], PASSWORD_DEFAULT)) {
        $pdo->prepare('UPDATE users SET password_hash = :h WHERE id = :id')
            ->execute(['h' => password_hash($password, PASSWORD_DEFAULT), 'id' => $user['id']]);
    }

    session_regenerate_id(true);
    unset($_SESSION['csrf_token']);
    $_SESSION['user'] = user_session_data($user);
    $_SESSION['last_activity'] = time();

    return [true, 'Login successful.'];
}

/** Completely end the session. */
function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'domain'   => $p['domain'],
            'secure'   => $p['secure'],
            'httponly' => $p['httponly'],
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }

    session_destroy();
}

/** Put at the top of every private page. */
function require_login(): void
{
    if (!is_logged_in()) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');
    }

    $timeout = SESSION_TIMEOUT_MINUTES * 60;
    if (isset($_SESSION['last_activity']) && (time() - (int)$_SESSION['last_activity']) > $timeout) {
        logout_user();
        start_secure_session();
        flash('warning', 'You were logged out because of inactivity.');
        redirect('login.php');
    }
    $_SESSION['last_activity'] = time();

    $stmt = db()->prepare(
        'SELECT id, full_name, email, role, chapter_id, is_active FROM users WHERE id = :id LIMIT 1'
    );
    $stmt->execute(['id' => $_SESSION['user']['id']]);
    $fresh = $stmt->fetch();

    if (!$fresh || (int)$fresh['is_active'] !== 1) {
        logout_user();
        start_secure_session();
        flash('danger', 'Your account is no longer active.');
        redirect('login.php');
    }

    $_SESSION['user'] = user_session_data($fresh);
}

/** Example: require_role(['super_admin']); */
function require_role(array $roles): void
{
    require_login();

    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('403 - You do not have permission to open this page.');
    }
}
