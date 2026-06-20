<?php
/**
 * TOTP (RFC 6238) autocontenido — compatible con Google Authenticator / Authy.
 * HMAC-SHA1, paso de 30s, 6 dígitos, secreto en Base32 (RFC 4648).
 * Sin dependencias externas.
 */

const TOTP_ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

function totp_base32_encode(string $data): string
{
    $out = '';
    $bits = 0;
    $val = 0;
    for ($i = 0, $n = strlen($data); $i < $n; $i++) {
        $val = ($val << 8) | ord($data[$i]);
        $bits += 8;
        while ($bits >= 5) {
            $bits -= 5;
            $out .= TOTP_ALPHABET[($val >> $bits) & 31];
        }
    }
    if ($bits > 0) {
        $out .= TOTP_ALPHABET[($val << (5 - $bits)) & 31];
    }
    return $out;
}

function totp_base32_decode(string $b32): string
{
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $out = '';
    $bits = 0;
    $val = 0;
    for ($i = 0, $n = strlen($b32); $i < $n; $i++) {
        $val = ($val << 5) | strpos(TOTP_ALPHABET, $b32[$i]);
        $bits += 5;
        if ($bits >= 8) {
            $bits -= 8;
            $out .= chr(($val >> $bits) & 0xFF);
        }
    }
    return $out;
}

/** Secreto nuevo (20 bytes = 160 bits → 32 caracteres Base32). */
function totp_generate_secret(int $bytes = 20): string
{
    return totp_base32_encode(random_bytes($bytes));
}

/** Código de 6 dígitos para un secreto en un paso de tiempo dado. */
function totp_code(string $secret, ?int $timeSlice = null): string
{
    $key = totp_base32_decode($secret);
    if ($key === '') {
        return '';
    }
    if ($timeSlice === null) {
        $timeSlice = (int) floor(time() / 30);
    }
    // Contador de 8 bytes big-endian.
    $bin  = pack('N', 0) . pack('N', $timeSlice);
    $hash = hash_hmac('sha1', $bin, $key, true);
    $offset = ord($hash[19]) & 0x0F;
    $part = ((ord($hash[$offset]) & 0x7F) << 24)
          | ((ord($hash[$offset + 1]) & 0xFF) << 16)
          | ((ord($hash[$offset + 2]) & 0xFF) << 8)
          | (ord($hash[$offset + 3]) & 0xFF);
    return str_pad((string) ($part % 1000000), 6, '0', STR_PAD_LEFT);
}

/** Verifica un código permitiendo ±$window pasos (tolerancia de reloj). Timing-safe. */
function totp_verify(string $secret, string $code, int $window = 1): bool
{
    $code = preg_replace('/\D/', '', $code);
    if (strlen($code) !== 6 || $secret === '') {
        return false;
    }
    $now = (int) floor(time() / 30);
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secret, $now + $i), $code)) {
            return true;
        }
    }
    return false;
}

/** URI otpauth:// para el QR de inscripción. */
function totp_provisioning_uri(string $secret, string $label, string $issuer): string
{
    return 'otpauth://totp/' . rawurlencode($issuer . ':' . $label)
        . '?secret=' . $secret
        . '&issuer=' . rawurlencode($issuer)
        . '&algorithm=SHA1&digits=6&period=30';
}
