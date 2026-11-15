<?php
// Проверка на връзката със SMTP сървър БЕЗ изпращане и без парола:
// свързване, EHLO, STARTTLS/SSL с проверка на сертификата и списък с поддържаните методи за вход.
//   php tools/smtp_probe.php mail.example.bg 465 ssl
//   php tools/smtp_probe.php mail.example.bg 587 tls
declare(strict_types=1);
if (PHP_SAPI !== 'cli') {
    exit;
}
[$host, $port, $secure] = [$argv[1] ?? '', (int)($argv[2] ?? 587), $argv[3] ?? 'tls'];
if ($host === '') {
    exit("Използване: php tools/smtp_probe.php ХОСТ ПОРТ ssl|tls|none\n");
}
$cafile = ini_get('openssl.cafile') ?: '(системно хранилище)';
echo "OpenSSL: " . OPENSSL_VERSION_TEXT . "; CA: $cafile\n";
$ctx = stream_context_create(['ssl' => ['verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true]]);
$fp = @stream_socket_client(($secure === 'ssl' ? 'ssl://' : 'tcp://') . "$host:$port", $errno, $errstr, 15, STREAM_CLIENT_CONNECT, $ctx);
if (!$fp) {
    exit("✗ Няма връзка: $errstr ($errno)\n");
}
stream_set_timeout($fp, 15);
$read = function () use ($fp) {
    $out = '';
    while (($l = fgets($fp, 1024)) !== false) {
        $out .= $l;
        if (strlen($l) < 4 || $l[3] === ' ') break;
    }
    return trim($out);
};
echo "< " . $read() . "\n";
fwrite($fp, "EHLO probe.local\r\n");
$ehlo = $read();
echo "< " . str_replace("\n", "\n< ", $ehlo) . "\n";
if ($secure === 'tls') {
    fwrite($fp, "STARTTLS\r\n");
    echo "< " . $read() . "\n";
    if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT | STREAM_CRYPTO_METHOD_TLSv1_3_CLIENT)) {
        exit("✗ STARTTLS не успя (сертификат/CA?)\n");
    }
    echo "✓ TLS е установен\n";
    fwrite($fp, "EHLO probe.local\r\n");
    $ehlo = $read();
    echo "< " . str_replace("\n", "\n< ", $ehlo) . "\n";
} elseif ($secure === 'ssl') {
    echo "✓ SSL е установен\n";
}
echo preg_match('/AUTH[ =][^\r\n]*LOGIN/i', $ehlo) ? "✓ Сървърът поддържа AUTH LOGIN\n" : "⚠ AUTH LOGIN не е обявен\n";
fwrite($fp, "QUIT\r\n");
fclose($fp);
