<?php

namespace Tygh\Addons\RvNetopia\Encryption;

class NetopiaEncryption
{
    const ERROR_CONFIRM_INVALID_POST = 0x300000f0;
    const ERROR_CONFIRM_INVALID_ACTION = 0x300000f4;

    /**
     * Encrypt XML data using Netopia's public certificate.
     *
     * @param string $xmlData        The XML string to encrypt
     * @param string $publicCertPem  PEM content of the public certificate
     *
     * @return array [env_key, data, cipher, iv] all base64-encoded
     *
     * @throws \RuntimeException If encryption fails
     */
    public static function encrypt($xmlData, $publicCertPem)
    {
        $publicKey = openssl_pkey_get_public($publicCertPem);
        if ($publicKey === false) {
            throw new \RuntimeException('Failed to load public certificate: ' . openssl_error_string());
        }

        // Select cipher based on PHP/OpenSSL version
        $cipher = self::selectCipher();

        $srcData = $xmlData;
        $encData = null;
        $envKeys = null;
        $iv = null;

        if ($cipher === 'aes-256-cbc') {
            $ivLen = openssl_cipher_iv_length($cipher);
            $iv = openssl_random_pseudo_bytes($ivLen);
            $result = openssl_seal($srcData, $encData, $envKeys, [$publicKey], $cipher, $iv);
        } else {
            // RC4 fallback
            $result = openssl_seal($srcData, $encData, $envKeys, [$publicKey], $cipher);
        }

        if ($result === false) {
            throw new \RuntimeException('openssl_seal() failed: ' . openssl_error_string());
        }

        return [
            'env_key' => base64_encode($envKeys[0]),
            'data'    => base64_encode($encData),
            'cipher'  => $cipher,
            'iv'      => $iv !== null ? base64_encode($iv) : null,
        ];
    }

    /**
     * Decrypt IPN data using the merchant's private key.
     *
     * @param string $envKeyB64      Base64-encoded envelope key
     * @param string $dataB64        Base64-encoded encrypted data
     * @param string $cipher         Cipher algorithm used (aes-256-cbc or rc4)
     * @param string|null $ivB64     Base64-encoded IV (for AES only)
     * @param string $privateKeyPem  PEM content of the private key
     *
     * @return string Decrypted XML string
     *
     * @throws \RuntimeException If decryption fails
     */
    public static function decrypt($envKeyB64, $dataB64, $cipher, $ivB64, $privateKeyPem)
    {
        $privateKey = openssl_pkey_get_private($privateKeyPem);
        if ($privateKey === false) {
            throw new \RuntimeException('Failed to load private key: ' . openssl_error_string());
        }

        $srcData = base64_decode($dataB64);
        $srcEnvKey = base64_decode($envKeyB64);
        $decrypted = null;

        if ($cipher === 'aes-256-cbc' && !empty($ivB64)) {
            $srcIv = base64_decode($ivB64);
            $result = openssl_open($srcData, $decrypted, $srcEnvKey, $privateKey, $cipher, $srcIv);
        } else {
            // RC4 or no IV
            $result = openssl_open($srcData, $decrypted, $srcEnvKey, $privateKey, 'rc4');
        }

        if ($result === false || $decrypted === null) {
            throw new \RuntimeException('openssl_open() failed: ' . openssl_error_string());
        }

        return $decrypted;
    }

    /**
     * Select the best cipher algorithm based on PHP and OpenSSL version.
     *
     * @return string 'aes-256-cbc' or 'rc4'
     */
    private static function selectCipher()
    {
        if (PHP_MAJOR_VERSION >= 7) {
            $opensslVersion = OPENSSL_VERSION_NUMBER;
            if ($opensslVersion > 0x10000000) { // OpenSSL > 1.0
                return 'aes-256-cbc';
            }
            return 'rc4';
        }

        return 'rc4';
    }
}
