<?php
declare(strict_types=1);

/**
 * Small SMTP client (no Composer / PHPMailer needed).
 * Supports STARTTLS (port 587) and implicit SSL (port 465) with AUTH LOGIN,
 * which is what Gmail uses. One connection is re-used for many emails.
 */

/** fatal = true means "the connection / login / quota is the problem, stop sending more". */
class SmtpException extends RuntimeException
{
    public bool $fatal;

    public function __construct(string $message, bool $fatal = false)
    {
        parent::__construct($message);
        $this->fatal = $fatal;
    }
}

final class SmtpMailer
{
    /** @var resource|null */
    private $conn = null;

    public function __construct(
        private string $host,
        private int $port,
        private string $secure,
        private string $username,
        private string $password,
        private int $timeout = 20,
        private bool $verifyPeer = true,
        private string $caFile = ''
    ) {
    }

    public function __destruct()
    {
        $this->close();
    }

    /** Send one email. Throws SmtpException on any problem. */
    public function send(
        string $fromAddress,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo = null
    ): void {
        $fromAddress = self::cleanHeader($fromAddress);
        $to          = self::cleanHeader($to);

        if (!filter_var($fromAddress, FILTER_VALIDATE_EMAIL) || !filter_var($to, FILTER_VALIDATE_EMAIL)) {
            throw new SmtpException('Invalid email address.');
        }

        $message = $this->buildMessage($fromAddress, $fromName, $to, $subject, $html, $text, $replyTo);

        $fresh = false;
        if ($this->conn === null) {
            $this->connect();
            $fresh = true;
        }

        try {
            $this->transmit($fromAddress, $to, $message);
        } catch (SmtpException $ex) {
            // A re-used connection may have timed out while idle: reconnect once and try again.
            if (!$fresh && $this->conn === null) {
                $this->connect();
                $this->transmit($fromAddress, $to, $message);
                return;
            }

            // Put the connection back in a clean state; if that fails, drop it.
            try {
                $this->command('RSET', [250]);
            } catch (Throwable $ignore) {
                $this->close();
            }
            throw $ex;
        }
    }

    private function transmit(string $fromAddress, string $to, string $message): void
    {
        $this->command('MAIL FROM:<' . $fromAddress . '>', [250]);
        $this->command('RCPT TO:<' . $to . '>', [250, 251]);
        $this->command('DATA', [354]);

        // Dot-stuffing: a line that starts with "." must be doubled.
        $payload = preg_replace('/^\./m', '..', $message) ?? $message;
        $this->write($payload . "\r\n.");
        $this->expect([250]);
    }

    public function close(): void
    {
        if (is_resource($this->conn)) {
            @fwrite($this->conn, "QUIT\r\n");
            @fclose($this->conn);
        }
        $this->conn = null;
    }

    // ---------------------------------------------------------------------

    private function connect(): void
    {
        $ssl = [
            'verify_peer'      => $this->verifyPeer,
            'verify_peer_name' => $this->verifyPeer,
            'peer_name'        => $this->host,
            'SNI_enabled'      => true,
        ];
        if ($this->caFile !== '') {
            $ssl['cafile'] = $this->caFile;
        }
        $context = stream_context_create(['ssl' => $ssl]);

        $scheme = $this->secure === 'ssl' ? 'ssl' : 'tcp';
        $errno  = 0;
        $errstr = '';
        $conn   = @stream_socket_client(
            $scheme . '://' . $this->host . ':' . $this->port,
            $errno,
            $errstr,
            $this->timeout,
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (!is_resource($conn)) {
            throw new SmtpException(
                'Could not connect to ' . $this->host . ':' . $this->port . ' (' . trim($errstr) . '). '
                . 'Check your internet connection and that the firewall allows this port.',
                true
            );
        }

        stream_set_timeout($conn, $this->timeout);
        $this->conn = $conn;

        try {
            $this->expect([220], true);
            $this->command('EHLO ' . $this->helloName(), [250], false, true);

            if ($this->secure === 'tls') {
                $this->command('STARTTLS', [220], false, true);
                $ok = @stream_socket_enable_crypto($conn, true, STREAM_CRYPTO_METHOD_TLS_CLIENT);
                if ($ok !== true) {
                    throw new SmtpException(
                        'Could not start a secure (TLS) connection. If XAMPP reports "certificate verify failed", '
                        . 'set MAIL_CA_FILE in config/mail.php (see the comment there).',
                        true
                    );
                }
                $this->command('EHLO ' . $this->helloName(), [250], false, true);
            }

            $this->command('AUTH LOGIN', [334], false, true);
            $this->command(base64_encode($this->username), [334], true, true);
            $this->command(base64_encode($this->password), [235], true, true);
        } catch (SmtpException $ex) {
            $this->close();
            throw $ex;
        }
    }

    private function helloName(): string
    {
        $name = (string)($_SERVER['SERVER_NAME'] ?? gethostname() ?: 'localhost');
        $name = preg_replace('/[^A-Za-z0-9.\-]/', '', $name) ?? '';
        return $name !== '' ? $name : 'localhost';
    }

    private function write(string $data): void
    {
        if (!is_resource($this->conn) || @fwrite($this->conn, $data . "\r\n") === false) {
            $this->conn = null;
            throw new SmtpException('The connection to the mail server was lost.', true);
        }
    }

    /**
     * @param int[] $okCodes
     * @param bool  $sensitive  true for login steps (so a rejected login gets a helpful message)
     * @param bool  $fatal      true if a failure here means the whole connection is unusable
     */
    private function command(string $cmd, array $okCodes, bool $sensitive = false, bool $fatal = false): void
    {
        $this->write($cmd);
        $this->expect($okCodes, $fatal, $sensitive);
    }

    /** @param int[] $okCodes */
    private function expect(array $okCodes, bool $fatal = false, bool $login = false): void
    {
        $code = 0;
        $last = '';

        while (is_resource($this->conn) && ($line = fgets($this->conn, 1024)) !== false) {
            $last = rtrim($line);
            $code = (int)substr($last, 0, 3);
            // "250-..." means more lines follow; "250 ..." (or a bare "250") is the last line.
            if (strlen($last) < 4 || $last[3] !== '-') {
                break;
            }
        }

        if ($last === '') {
            $this->conn = null;
            throw new SmtpException('No response from the mail server (connection closed or timed out).', true);
        }

        if (in_array($code, $okCodes, true)) {
            return;
        }

        if ($login || $code === 535 || $code === 534) {
            throw new SmtpException(
                'Gmail rejected the login (' . $last . '). Use a 16-character App Password for MAIL_PASSWORD in '
                . 'config/mail.php (2-Step Verification must be ON); the normal Gmail password does not work.',
                true
            );
        }

        // Daily sending limit / account blocked: stop the whole run.
        if (preg_match('/quota|limit exceeded|5\.4\.5|too many/i', $last) === 1 || $code === 421 || $code === 454) {
            $fatal = true;
        }

        throw new SmtpException('Mail server said: ' . $last, $fatal);
    }

    // ---------------------------------------------------------------------

    /** Remove line breaks so nobody can inject extra headers. */
    private static function cleanHeader(string $value): string
    {
        return trim(preg_replace('/[\r\n]+/', ' ', $value) ?? '');
    }

    private static function encodeHeader(string $value): string
    {
        $value = self::cleanHeader($value);
        if (preg_match('/[^\x20-\x7E]/', $value) === 1) {
            return mb_encode_mimeheader($value, 'UTF-8', 'B', "\r\n");
        }
        return $value;
    }

    private function buildMessage(
        string $from,
        string $fromName,
        string $to,
        string $subject,
        string $html,
        string $text,
        ?string $replyTo
    ): string {
        $boundary = 'pm_' . bin2hex(random_bytes(12));
        $domain   = substr(strrchr($from, '@') ?: '@localhost', 1);

        $name = str_replace(['"', '\\'], '', self::cleanHeader($fromName));
        $fromHeader = $name !== ''
            ? (preg_match('/[^\x20-\x7E]/', $name) === 1 ? self::encodeHeader($name) : '"' . $name . '"') . ' <' . $from . '>'
            : $from;

        $headers = [
            'Date: ' . date('r'),
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . self::encodeHeader($subject),
            'Message-ID: <' . bin2hex(random_bytes(10)) . '@' . $domain . '>',
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary="' . $boundary . '"',
        ];

        $reply = $replyTo !== null ? self::cleanHeader($replyTo) : '';
        if ($reply !== '' && filter_var($reply, FILTER_VALIDATE_EMAIL)) {
            $headers[] = 'Reply-To: <' . $reply . '>';
        }

        $part = static function (string $type, string $body) use ($boundary): string {
            return '--' . $boundary . "\r\n"
                . 'Content-Type: ' . $type . "; charset=UTF-8\r\n"
                . "Content-Transfer-Encoding: base64\r\n\r\n"
                . chunk_split(base64_encode($body), 76, "\r\n");
        };

        return implode("\r\n", $headers) . "\r\n\r\n"
            . $part('text/plain', $text)
            . $part('text/html', $html)
            . '--' . $boundary . '--';
    }
}
