<?php

namespace App\Services\Updates;

/**
 * Semnătură Ed25519 (libsodium) peste conținutul exact al update.json.
 * update.sig = base64(semnătură detașată, 64 octeți).
 */
class SignatureVerifier
{
    /**
     * @return bool|null true = validă, false = invalidă, null = nesemnat / fără cheie configurată
     */
    public static function verify(string $manifestJson, ?string $signatureB64): ?bool
    {
        $publicKey = (string) config('update.public_key', '');
        if ($signatureB64 === null || trim($signatureB64) === '') {
            return null;
        }
        if ($publicKey === '' || ! function_exists('sodium_crypto_sign_verify_detached')) {
            return null;
        }
        $sig = base64_decode(trim($signatureB64), true);
        $pk = base64_decode($publicKey, true);
        if ($sig === false || $pk === false || strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || strlen($pk) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            return false;
        }

        return sodium_crypto_sign_verify_detached($sig, $manifestJson, $pk);
    }

    public static function sign(string $manifestJson, string $secretKeyB64): string
    {
        $sk = base64_decode($secretKeyB64, true);
        if ($sk === false || strlen($sk) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new UpdateException('Cheie secretă de semnare invalidă.');
        }

        return base64_encode(sodium_crypto_sign_detached($manifestJson, $sk));
    }
}
