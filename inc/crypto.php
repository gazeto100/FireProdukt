<?php
declare(strict_types=1);

/** Криптира чувствителни настройки (напр. SMTP паролата) с ключа от config.php */
function secret_encrypt(string $plain): string
{
    if ($plain === '') {
        return '';
    }
    $key = hash('sha256', (string)app_config('app_key', ''), true);
    $iv = random_bytes(12);
    $tag = '';
    $ct = openssl_encrypt($plain, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    return 'enc:' . base64_encode($iv . $tag . $ct);
}

function secret_decrypt(string $stored): string
{
    if ($stored === '' || strncmp($stored, 'enc:', 4) !== 0) {
        return $stored;
    }
    $raw = base64_decode(substr($stored, 4), true);
    if ($raw === false || strlen($raw) < 29) {
        return '';
    }
    $key = hash('sha256', (string)app_config('app_key', ''), true);
    $plain = openssl_decrypt(substr($raw, 28), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, substr($raw, 0, 12), substr($raw, 12, 16));
    return $plain === false ? '' : $plain;
}
