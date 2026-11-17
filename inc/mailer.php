<?php
declare(strict_types=1);

/**
 * Изпращане на имейли: първо SMTP (настройки от админа), при неуспех – PHP mail().
 * Всеки опит се записва в mail_log.
 *
 * $attachments: [['name' => 'preview.svg', 'type' => 'image/svg+xml', 'data' => '...'], ...]
 */
function send_mail(string $to, string $subject, string $text, ?string $html = null, array $attachments = [], ?int $inquiryId = null): bool
{
    $s = settings_all();
    $host = trim((string)($s['smtp_host'] ?? ''));
    $from = trim((string)($s['mail_from'] ?? ''));
    if ($from === '') {
        $from = filter_var($s['smtp_user'] ?? '', FILTER_VALIDATE_EMAIL) ? $s['smtp_user'] : 'noreply@' . preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
    }
    $fromName = site_name(); // подателят е името на сайта (едно име навсякъде)

    $msg = mail_build($from, $fromName, $to, $subject, $text, $html, $attachments);

    if ($host !== '') {
        try {
            smtp_send([
                'host' => $host,
                'port' => (int)($s['smtp_port'] ?? 587),
                'secure' => (string)($s['smtp_secure'] ?? 'tls'),
                'user' => (string)($s['smtp_user'] ?? ''),
                'pass' => secret_decrypt((string)($s['smtp_pass'] ?? '')),
            ], $from, $to, $msg['headers'] . "\r\n\r\n" . $msg['body']);
            mail_log($inquiryId, $to, $subject, 'smtp', true, null);
            return true;
        } catch (Throwable $e) {
            mail_log($inquiryId, $to, $subject, 'smtp', false, $e->getMessage());
        }
    }

    // Резервен вариант: mail()
    $err = null;
    set_error_handler(function ($no, $str) use (&$err) {
        $err = $str;
        return true;
    });
    $ok = false;
    try {
        $params = filter_var($from, FILTER_VALIDATE_EMAIL) ? '-f' . $from : '';
        $ok = mail($to, mail_encode_header($subject), $msg['body'], $msg['headers_mail'], $params);
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
    restore_error_handler();
    mail_log($inquiryId, $to, $subject, 'mail', $ok, $ok ? null : ($err ?: 'mail() върна false'));
    return $ok;
}

function mail_log(?int $inquiryId, string $to, string $subject, string $transport, bool $ok, ?string $error): void
{
    try {
        $st = db()->prepare('INSERT INTO mail_log (inquiry_id, recipient, subject, transport, ok, error) VALUES (?, ?, ?, ?, ?, ?)');
        if ($error !== null) {
            $error = trim(html_entity_decode(strip_tags($error), ENT_QUOTES, 'UTF-8'));
        }
        $st->execute([$inquiryId, mb_substr($to, 0, 190), mb_substr($subject, 0, 255), $transport, $ok ? 1 : 0, $error !== null ? mb_substr($error, 0, 2000) : null]);
    } catch (Throwable $e) {
        error_log('mail_log: ' . $e->getMessage());
    }
}

function mail_encode_header(string $s): string
{
    return preg_match('/[^\x20-\x7E]/', $s) ? '=?UTF-8?B?' . base64_encode($s) . '?=' : $s;
}

function mail_build(string $from, string $fromName, string $to, string $subject, string $text, ?string $html, array $attachments): array
{
    $eol = "\r\n";
    $host = preg_replace('/^.*@/', '', $from) ?: 'localhost';
    $common = [
        'From: ' . ($fromName !== '' ? mail_encode_header($fromName) . ' ' : '') . '<' . $from . '>',
        'Reply-To: ' . $from,
        'MIME-Version: 1.0',
        'Message-ID: <' . bin2hex(random_bytes(12)) . '@' . $host . '>',
        'Date: ' . date('r'),
        'X-Mailer: PHP',
    ];

    $alt = 'alt_' . bin2hex(random_bytes(8));
    $altBody = '--' . $alt . $eol
        . 'Content-Type: text/plain; charset=UTF-8' . $eol
        . 'Content-Transfer-Encoding: base64' . $eol . $eol
        . chunk_split(base64_encode($text), 76, $eol);
    if ($html !== null) {
        $altBody .= '--' . $alt . $eol
            . 'Content-Type: text/html; charset=UTF-8' . $eol
            . 'Content-Transfer-Encoding: base64' . $eol . $eol
            . chunk_split(base64_encode($html), 76, $eol);
    }
    $altBody .= '--' . $alt . '--' . $eol;

    if ($attachments) {
        $mix = 'mix_' . bin2hex(random_bytes(8));
        $ctype = 'Content-Type: multipart/mixed; boundary="' . $mix . '"';
        $body = '--' . $mix . $eol . 'Content-Type: multipart/alternative; boundary="' . $alt . '"' . $eol . $eol . $altBody;
        foreach ($attachments as $a) {
            $name = preg_replace('/[^\w.\-]/', '_', $a['name']);
            $body .= '--' . $mix . $eol
                . 'Content-Type: ' . $a['type'] . '; name="' . $name . '"' . $eol
                . 'Content-Transfer-Encoding: base64' . $eol
                . 'Content-Disposition: attachment; filename="' . $name . '"' . $eol . $eol
                . chunk_split(base64_encode($a['data']), 76, $eol);
        }
        $body .= '--' . $mix . '--' . $eol;
    } else {
        $ctype = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
        $body = $altBody;
    }

    $headersMail = implode($eol, array_merge($common, [$ctype]));
    $headersSmtp = implode($eol, array_merge($common, [
        'To: <' . $to . '>',
        'Subject: ' . mail_encode_header($subject),
        $ctype,
    ]));
    return ['headers' => $headersSmtp, 'headers_mail' => $headersMail, 'body' => $body];
}

/** Минимален SMTP клиент: SSL (465), STARTTLS (587) или без криптиране; AUTH LOGIN */
function smtp_send(array $c, string $from, string $to, string $data): void
{
    $secure = $c['secure'];
    $remote = ($secure === 'ssl' ? 'ssl://' : 'tcp://') . $c['host'] . ':' . $c['port'];
    $ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
    $fp = @stream_socket_client($remote, $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) {
        throw new RuntimeException("Няма връзка със SMTP сървъра {$c['host']}:{$c['port']} – $errstr ($errno)");
    }
    stream_set_timeout($fp, 20);
    try {
        $read = function () use ($fp): array {
            $out = '';
            while (($line = fgets($fp, 1024)) !== false) {
                $out .= $line;
                if (strlen($line) < 4 || $line[3] === ' ') {
                    break;
                }
            }
            if ($out === '') {
                throw new RuntimeException('SMTP сървърът не отговори (таймаут).');
            }
            return [(int)substr($out, 0, 3), trim($out)];
        };
        $cmd = function (string $line, array $expect, ?string $logAs = null) use ($fp, $read): string {
            fwrite($fp, $line . "\r\n");
            [$code, $resp] = $read();
            if (!in_array($code, $expect, true)) {
                throw new RuntimeException('SMTP ' . ($logAs ?? $line) . ' → ' . $resp);
            }
            return $resp;
        };

        [$code, $resp] = $read();
        if ($code !== 220) {
            throw new RuntimeException('SMTP поздрав: ' . $resp);
        }
        $ehloHost = preg_replace('/:\d+$/', '', $_SERVER['HTTP_HOST'] ?? 'localhost');
        $cmd('EHLO ' . $ehloHost, [250]);
        if ($secure === 'tls') {
            $cmd('STARTTLS', [220]);
            $ok = stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT);
            if (!$ok) {
                throw new RuntimeException('Неуспешен STARTTLS.');
            }
            $cmd('EHLO ' . $ehloHost, [250]);
        }
        if ($c['user'] !== '') {
            $cmd('AUTH LOGIN', [334]);
            $cmd(base64_encode($c['user']), [334], '(потребител)');
            $cmd(base64_encode($c['pass']), [235], '(парола)');
        }
        $cmd('MAIL FROM:<' . $from . '>', [250]);
        $cmd('RCPT TO:<' . $to . '>', [250, 251]);
        $cmd('DATA', [354]);
        $data = str_replace(["\r\n", "\r"], "\n", $data);
        $data = preg_replace('/^\./m', '..', $data);
        $data = str_replace("\n", "\r\n", $data);
        $cmd($data . "\r\n.", [250], 'DATA (съобщение)');
        fwrite($fp, "QUIT\r\n");
    } finally {
        fclose($fp);
    }
}
