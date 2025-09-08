<?php

namespace MillionDollarScript\Classes\Extension;

/**
 * LicenseCrypto: Encrypts/decrypts license keys at rest using AES-256-GCM.
 *
 * Storage format (compact):
 *   E1:BASE64(iv(12) || tag(16) || ciphertext(n))
 *
 * - Key derivation uses site-specific salts.
 * - If decryptFromCompact() receives a non-prefixed value, it returns it as-is for backward compatibility.
 */
class LicenseCrypto {
    private static function getKey(): string {
        $material = (defined('AUTH_KEY') ? AUTH_KEY : '') . (defined('SECURE_AUTH_KEY') ? SECURE_AUTH_KEY : '') . site_url('/');
        // 32 bytes for AES-256
        return hash_hmac('sha256', $material, 'mds-license-crypto', true);
    }

    public static function encryptToCompact(string $plaintext): string {
        if ($plaintext === '') {
            return $plaintext;
        }
        $key = self::getKey();
        $iv = random_bytes(12); // 96-bit IV for GCM
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '', 16);
        if ($ciphertext === false || $tag === '') {
            // Fallback: return plaintext if encryption fails
            return $plaintext;
        }
        $packed = $iv . $tag . $ciphertext; // 12 + 16 + n
        return 'E1:' . base64_encode($packed);
    }

    public static function decryptFromCompact(?string $stored): string {
        if (!is_string($stored) || $stored === '') {
            return '';
        }
        if (strpos($stored, 'E1:') !== 0) {
            // Not encrypted in our compact format; return as-is for backward compatibility
            return $stored;
        }
        $b64 = substr($stored, 3);
        $packed = base64_decode($b64, true);
        if ($packed === false || strlen($packed) < 28) { // iv(12) + tag(16)
            return '';
        }
        $iv  = substr($packed, 0, 12);
        $tag = substr($packed, 12, 16);
        $ct  = substr($packed, 28);
        $key = self::getKey();
        $pt = openssl_decrypt($ct, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag, '');
        return $pt === false ? '' : $pt;
    }
}

