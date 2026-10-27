<?php
declare(strict_types=1);

/*
 * Every page starts with:
 *   require_once __DIR__ . '/includes/bootstrap.php';
 */

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/mail.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/members.php';
require_once __DIR__ . '/presentations.php';
require_once __DIR__ . '/reports.php';
require_once __DIR__ . '/registrations.php';
require_once __DIR__ . '/emails.php';
require_once __DIR__ . '/users.php';

// The scheduled job (cron_reminders.php) runs from the command line and has no browser session.
if (PHP_SAPI !== 'cli') {
    start_secure_session();
}
