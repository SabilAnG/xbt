<?php

namespace App\Support;

/**
 * Kode 6 digit berbasis waktu (RFC 6238) — sama dengan Google Authenticator,
 * Microsoft Authenticator, Authy, dll. Tanpa paket tambahan.
 */
class Totp
{
    private const ALPHABET = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    private const PERIOD = 30;

    private const DIGITS = 6;

    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** Kode untuk detik ini (dipakai test & tampilan bantu). */
    public static function code(string $secret, ?int $timestamp = null): string
    {
        return self::codeAt($secret, intdiv($timestamp ?? time(), self::PERIOD));
    }

    /**
     * Langkah waktu yang cocok dengan kode ini (toleransi ±1 langkah = ±30 detik
     * untuk jam HP yang sedikit meleset), atau null bila salah.
     */
    public static function match(string $secret, string $code, ?int $timestamp = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code);

        if (! preg_match('/^\d{'.self::DIGITS.'}$/', $code)) {
            return null;
        }

        $step = intdiv($timestamp ?? time(), self::PERIOD);

        foreach ([0, -1, 1] as $offset) {
            if (hash_equals(self::codeAt($secret, $step + $offset), $code)) {
                return $step + $offset;
            }
        }

        return null;
    }

    /** Tautan otpauth:// — dibuka di HP langsung masuk ke aplikasi authenticator. */
    public static function uri(string $secret, string $account, string $issuer): string
    {
        return 'otpauth://totp/'.rawurlencode($issuer.':'.$account)
            .'?secret='.$secret.'&issuer='.rawurlencode($issuer).'&digits='.self::DIGITS.'&period='.self::PERIOD;
    }

    private static function codeAt(string $secret, int $step): string
    {
        $hash = hash_hmac('sha1', pack('N*', 0, $step), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $value = ((ord($hash[$offset]) & 0x7F) << 24) | (ord($hash[$offset + 1]) << 16) | (ord($hash[$offset + 2]) << 8) | ord($hash[$offset + 3]);

        return str_pad((string) ($value % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $bytes): string
    {
        $bits = '';
        foreach (str_split($bytes) as $byte) {
            $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::ALPHABET[bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    private static function base32Decode(string $secret): string
    {
        $bits = '';
        foreach (str_split(strtoupper(rtrim($secret, '='))) as $char) {
            $index = strpos(self::ALPHABET, $char);
            if ($index !== false) {
                $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
            }
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr(bindec($byte));
            }
        }

        return $out;
    }
}
