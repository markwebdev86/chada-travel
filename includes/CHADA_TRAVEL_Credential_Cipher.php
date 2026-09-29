<?php
/**
 * Authenticated encryption for plugin-managed secrets and queue context.
 *
 * @package Chada_Travel
 */

namespace CHADA_TRAVEL;

defined('ABSPATH') || exit;

final class CHADA_TRAVEL_Credential_Cipher {
    private const ENVELOPE_VERSION = '1';
    private const CIPHER_SODIUM = 'sodium';
    private const CIPHER_OPENSSL = 'openssl';
    private const OPENSSL_METHOD = 'aes-256-gcm';
    private const OPENSSL_TAG_LENGTH = 16;
    private const OPENSSL_NONCE_LENGTH = 12;

    public static function is_available(): bool {
        return self::sodium_available() || self::openssl_available();
    }

    /** Encrypts a non-empty secret; null means authenticated encryption is unavailable. */
    public static function encrypt(string $chada_travel_plaintext): ?string {
        return self::encrypt_for_purpose($chada_travel_plaintext, 'paypal_credentials');
    }

    /** Decrypts a secret using the established PayPal purpose by default. */
    public static function decrypt(?string $chada_travel_envelope): ?string {
        return self::decrypt_for_purpose($chada_travel_envelope, 'paypal_credentials');
    }

    /** Encrypts data with a purpose-specific key so queue context cannot be reused as a credential. */
    public static function encrypt_for_purpose(string $chada_travel_plaintext, string $chada_travel_purpose): ?string {
        if ($chada_travel_plaintext === '') {
            return '';
        }
        $chada_travel_key = self::derive_key($chada_travel_purpose);
        if (self::sodium_available()) {
            $chada_travel_nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            return self::encode_envelope(
                self::CIPHER_SODIUM,
                $chada_travel_nonce,
                sodium_crypto_secretbox($chada_travel_plaintext, $chada_travel_nonce, $chada_travel_key)
            );
        }
        if (!self::openssl_available()) {
            return null;
        }
        $chada_travel_nonce = random_bytes(self::OPENSSL_NONCE_LENGTH);
        $chada_travel_tag = '';
        $chada_travel_ciphertext = openssl_encrypt(
            $chada_travel_plaintext,
            self::OPENSSL_METHOD,
            $chada_travel_key,
            OPENSSL_RAW_DATA,
            $chada_travel_nonce,
            $chada_travel_tag
        );
        return $chada_travel_ciphertext === false ? null : self::encode_envelope(
            self::CIPHER_OPENSSL,
            $chada_travel_nonce,
            $chada_travel_ciphertext . $chada_travel_tag
        );
    }

    /** Returns null for malformed, tampered, or undecryptable data. */
    public static function decrypt_for_purpose(?string $chada_travel_envelope, string $chada_travel_purpose): ?string {
        if ($chada_travel_envelope === null || $chada_travel_envelope === '') {
            return '';
        }
        $chada_travel_parts = explode('$', $chada_travel_envelope, 4);
        if (count($chada_travel_parts) !== 4 || $chada_travel_parts[0] !== self::ENVELOPE_VERSION) {
            return null;
        }
        [$chada_travel_version, $chada_travel_cipher, $chada_travel_nonce_b64, $chada_travel_ciphertext_b64] = $chada_travel_parts;
        $chada_travel_nonce = base64_decode($chada_travel_nonce_b64, true);
        $chada_travel_ciphertext = base64_decode($chada_travel_ciphertext_b64, true);
        if ($chada_travel_nonce === false || $chada_travel_ciphertext === false) {
            return null;
        }
        $chada_travel_key = self::derive_key($chada_travel_purpose);
        if ($chada_travel_cipher === self::CIPHER_SODIUM && self::sodium_available()) {
            $chada_travel_plaintext = sodium_crypto_secretbox_open($chada_travel_ciphertext, $chada_travel_nonce, $chada_travel_key);
            return $chada_travel_plaintext === false ? null : $chada_travel_plaintext;
        }
        if ($chada_travel_cipher !== self::CIPHER_OPENSSL || !self::openssl_available()
            || strlen($chada_travel_ciphertext) <= self::OPENSSL_TAG_LENGTH) {
            return null;
        }
        $chada_travel_tag = substr($chada_travel_ciphertext, -self::OPENSSL_TAG_LENGTH);
        $chada_travel_body = substr($chada_travel_ciphertext, 0, -self::OPENSSL_TAG_LENGTH);
        $chada_travel_plaintext = openssl_decrypt(
            $chada_travel_body,
            self::OPENSSL_METHOD,
            $chada_travel_key,
            OPENSSL_RAW_DATA,
            $chada_travel_nonce,
            $chada_travel_tag
        );
        return $chada_travel_plaintext === false ? null : $chada_travel_plaintext;
    }

    private static function sodium_available(): bool {
        return function_exists('sodium_crypto_secretbox') && function_exists('sodium_crypto_secretbox_open');
    }

    private static function openssl_available(): bool {
        return function_exists('openssl_encrypt') && function_exists('openssl_decrypt')
            && in_array(self::OPENSSL_METHOD, openssl_get_cipher_methods(), true);
    }

    private static function encode_envelope(string $chada_travel_cipher, string $chada_travel_nonce, string $chada_travel_ciphertext): string {
        return implode('$', [
            self::ENVELOPE_VERSION, $chada_travel_cipher, base64_encode($chada_travel_nonce), base64_encode($chada_travel_ciphertext),
        ]);
    }

    private static function derive_key(string $chada_travel_purpose): string {
        return hash('sha256', self::key_material($chada_travel_purpose), true);
    }

    private static function key_material(string $chada_travel_purpose): string {
        $chada_travel_purpose = preg_replace('/[^a-z0-9_\-]/i', '', $chada_travel_purpose) ?: 'default';
        if (function_exists('wp_salt')) {
            $chada_travel_salt = wp_salt('auth');
            if ($chada_travel_salt !== '') {
                return $chada_travel_salt . '|chada_travel_' . $chada_travel_purpose;
            }
        }
        if (defined('AUTH_KEY') && AUTH_KEY !== '') {
            return AUTH_KEY . '|chada_travel_' . $chada_travel_purpose;
        }
        return 'chada-travel-development-fallback-key|chada_travel_' . $chada_travel_purpose;
    }
}
