<?php
declare(strict_types=1);

/*
 * Email (SMTP) settings for Module 6.
 * This folder is blocked from the web by config/.htaccess.
 *
 * SENDER ACCOUNT:  360sociale@gmail.com  (Gmail)
 *
 * Gmail does NOT accept your normal Gmail password for SMTP. You need an "App Password":
 *   1. Sign in to 360sociale@gmail.com -> Google Account -> Security.
 *   2. Turn ON "2-Step Verification" (required).
 *   3. Open https://myaccount.google.com/apppasswords  -> create one named "Presenter Manager".
 *   4. Google shows a 16-character code. Paste it into MAIL_PASSWORD below (spaces are ignored).
 *
 * Never share this file or commit it to GitHub.
 */

const MAIL_HOST     = 'smtp.gmail.com';
const MAIL_PORT     = 587;                       // 587 + 'tls'  (or 465 + 'ssl')
const MAIL_SECURE   = 'tls';                     // 'tls' = STARTTLS, 'ssl' = implicit SSL
const MAIL_USERNAME = '360sociale@gmail.com';    // also used as the From address
const MAIL_PASSWORD = 'lxax mmki vrqc ixqg
';                        // <-- paste the 16-character App Password here

// Gmail allows about 500 emails per day. The reminder run stops after this many per run;
// anything left is picked up automatically on the next run.
const MAIL_MAX_PER_RUN = 100;

// Certificate check. Keep true. If XAMPP says "certificate verify failed", set MAIL_CA_FILE to
// the bundle that ships with XAMPP, e.g.  'C:/xampp/apache/bin/curl-ca-bundle.crt'
// (or set openssl.cafile in php.ini). Only use false for a quick local test, never on a live server.
const MAIL_VERIFY_PEER = true;
const MAIL_CA_FILE     = '';
