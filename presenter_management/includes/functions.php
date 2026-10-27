<?php
declare(strict_types=1);

/** Escape text before printing in HTML (stops XSS attacks). */
function e(?string $value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Build a full URL path:  url('login.php') => /presenter_management/login.php */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

/** Redirect to another page of our app and stop the script. */
function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/** Store a one-time message (shown on the next page). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

/** Read and clear the stored messages. */
function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

/** Print the messages as Bootstrap alerts. */
function render_flashes(): void
{
    foreach (get_flashes() as $f) {
        echo '<div class="alert alert-' . e($f['type']) . ' alert-dismissible fade show" role="alert">'
           . e($f['message'])
           . '<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>'
           . '</div>';
    }
}

/** "chapter_coordinator" => "Chapter Coordinator" */
function role_label(string $role): string
{
    return ucwords(str_replace('_', ' ', $role));
}

/** "Hasina Shaikh" => "HS" */
function initials(string $name): string
{
    $parts = preg_split('/\s+/', trim($name)) ?: [];
    $letters = '';
    foreach (array_slice($parts, 0, 2) as $part) {
        $letters .= mb_strtoupper(mb_substr($part, 0, 1));
    }
    return $letters !== '' ? $letters : '?';
}

/** True if $date is a real calendar date in Y-m-d format. */
function is_valid_date(string $date): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt instanceof DateTime && $dt->format('Y-m-d') === $date;
}

/**
 * Add calendar days (weekends and holidays included).
 * Example: 2026-01-01 + 90 days => 2026-04-01
 */
function add_calendar_days(?string $date, int $days = PRESENTATION_INTERVAL_DAYS): ?string
{
    if ($date === null || $date === '' || !is_valid_date($date)) {
        return null;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    $dt->modify('+' . $days . ' days');
    return $dt->format('Y-m-d');
}

/** Format Y-m-d for display, or an em dash if empty. */
function format_date(?string $date): string
{
    if ($date === null || $date === '' || !is_valid_date($date)) {
        return '—';
    }
    return DateTime::createFromFormat('Y-m-d', $date)->format('d M Y');
}

/** Keep extra characters out of LIKE searches (% and _). */
function like_contains(string $search): string
{
    $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
    return '%' . $escaped . '%';
}

/** Rebuild a query string and replace/remove one key. */
function query_with(array $params, string $key, mixed $value): string
{
    $merged = array_merge($params, [$key => $value]);
    if ($value === null || $value === '') {
        unset($merged[$key]);
    }
    return http_build_query($merged);
}
