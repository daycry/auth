<?php

declare(strict_types=1);

/**
 * This file is part of Daycry Auth.
 *
 * (c) Daycry <daycry9@proton.me>
 *
 * For the full copyright and license information, please view
 * the LICENSE file that was distributed with this source code.
 */

namespace Tests\Support\WebAuthn;

use CBOR\ByteStringObject;
use CBOR\MapObject;
use CBOR\NegativeIntegerObject;
use CBOR\TextStringObject;
use CBOR\UnsignedIntegerObject;
use JsonException;
use RuntimeException;

/**
 * Test-only software authenticator. Produces attestation ("none" fmt) and
 * assertion responses with a real ES256 (P-256) key pair so the genuine
 * web-auth/webauthn-lib validators accept them — no hardware, no fixtures.
 *
 * Correctness is asserted by VirtualAuthenticatorTest: the real validator
 * must accept the output.
 *
 * Byte layout reference (verified against the v5 loaders/validators):
 *   - authenticatorData = rpIdHash[32] ‖ flags[1] ‖ signCount[4 BE]
 *     [‖ aaguid[16] ‖ credIdLen[2 BE] ‖ credId ‖ COSE-key] when AT is set.
 *   - flags: registration = 0x45 (AT|UV|UP), assertion = 0x05 (UV|UP).
 *   - COSE EC2/ES256 key map: {1:2, 3:-7, -1:1, -2:x[32], -3:y[32]}.
 *   - attestationObject = CBOR map {fmt:"none", attStmt:{}, authData:bytes}.
 *   - assertion signature = ECDSA over authData ‖ SHA256(clientDataJSON),
 *     emitted in ASN.1/DER form (the library's CoseSignatureFixer converts it
 *     to raw R‖S before verification).
 */
final class VirtualAuthenticator
{
    /**
     * PEM-encoded EC private key (prime256v1).
     */
    private readonly string $privateKeyPem;

    /**
     * Raw 32-byte X coordinate of the public key.
     */
    private readonly string $x;

    /**
     * Raw 32-byte Y coordinate of the public key.
     */
    private readonly string $y;

    /**
     * Raw credential id bytes.
     */
    private readonly string $credentialIdRaw;

    /**
     * 16-byte AAGUID (zeros for "none").
     */
    private readonly string $aaguid;

    private int $signCount = 0;

    public function __construct(private readonly string $rpId, private readonly string $origin)
    {
        $config = $this->opensslConfig();
        $args   = [
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ];
        if ($config !== null) {
            $args['config'] = $config;
        }

        $key = openssl_pkey_new($args);
        if ($key === false) {
            throw new RuntimeException('Unable to generate an EC key pair: ' . (openssl_error_string() ?: 'unknown error'));
        }

        $exportArgs    = $config !== null ? ['config' => $config] : null;
        $privateKeyPem = '';
        if (! openssl_pkey_export($key, $privateKeyPem, null, $exportArgs)) {
            throw new RuntimeException('Unable to export the EC private key: ' . (openssl_error_string() ?: 'unknown error'));
        }
        $this->privateKeyPem = $privateKeyPem;

        $details = openssl_pkey_get_details($key);
        if ($details === false || ! isset($details['ec']['x'], $details['ec']['y'])) {
            throw new RuntimeException('Unable to read the EC public key coordinates.');
        }

        // ec.x / ec.y are the raw big-endian coordinates; pad to 32 bytes.
        $this->x = str_pad($details['ec']['x'], 32, "\0", STR_PAD_LEFT);
        $this->y = str_pad($details['ec']['y'], 32, "\0", STR_PAD_LEFT);

        $this->credentialIdRaw = random_bytes(32);
        $this->aaguid          = str_repeat("\0", 16);
    }

    public function credentialIdBase64Url(): string
    {
        return self::b64url($this->credentialIdRaw);
    }

    /**
     * Raw credential id bytes (useful when building allowCredentials).
     */
    public function credentialIdRaw(): string
    {
        return $this->credentialIdRaw;
    }

    /**
     * Build the navigator.credentials.create() PublicKeyCredential JSON.
     *
     * @param string $creationOptionsJson serialized PublicKeyCredentialCreationOptions
     *
     * @throws JsonException
     */
    public function register(string $creationOptionsJson): string
    {
        $options   = json_decode($creationOptionsJson, true, 512, JSON_THROW_ON_ERROR);
        $challenge = self::b64urlDecode($options['challenge']);

        $clientData = self::b64url($this->clientDataJSON('webauthn.create', $challenge));

        $authData          = $this->authenticatorData(true);
        $attestationObject = (string) MapObject::create()
            ->add(TextStringObject::create('fmt'), TextStringObject::create('none'))
            ->add(TextStringObject::create('attStmt'), MapObject::create())
            ->add(TextStringObject::create('authData'), ByteStringObject::create($authData));

        return json_encode([
            'id'       => $this->credentialIdBase64Url(),
            'rawId'    => $this->credentialIdBase64Url(),
            'type'     => 'public-key',
            'response' => [
                'clientDataJSON'    => $clientData,
                'attestationObject' => self::b64url($attestationObject),
            ],
            'clientExtensionResults' => (object) [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * Build the navigator.credentials.get() PublicKeyCredential JSON.
     *
     * @param string      $requestOptionsJson serialized PublicKeyCredentialRequestOptions
     * @param string|null $userHandleRaw      raw user-handle bytes to return (discoverable)
     *
     * @throws JsonException
     */
    public function login(string $requestOptionsJson, ?string $userHandleRaw = null): string
    {
        $options   = json_decode($requestOptionsJson, true, 512, JSON_THROW_ON_ERROR);
        $challenge = self::b64urlDecode($options['challenge']);

        $this->signCount++;

        $clientDataRaw = $this->clientDataJSON('webauthn.get', $challenge);
        $authData      = $this->authenticatorData(false);

        $signature = $this->sign($authData . hash('sha256', $clientDataRaw, true));

        $response = [
            'clientDataJSON'    => self::b64url($clientDataRaw),
            'authenticatorData' => self::b64url($authData),
            'signature'         => self::b64url($signature),
        ];
        if ($userHandleRaw !== null) {
            $response['userHandle'] = self::b64url($userHandleRaw);
        }

        return json_encode([
            'id'                     => $this->credentialIdBase64Url(),
            'rawId'                  => $this->credentialIdBase64Url(),
            'type'                   => 'public-key',
            'response'               => $response,
            'clientExtensionResults' => (object) [],
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * @throws JsonException
     */
    private function clientDataJSON(string $type, string $challenge): string
    {
        return json_encode([
            'type'      => $type,
            'challenge' => self::b64url($challenge),
            'origin'    => $this->origin,
        ], JSON_THROW_ON_ERROR);
    }

    /**
     * rpIdHash(32) ‖ flags(1) ‖ signCount(4) [‖ attestedCredentialData].
     */
    private function authenticatorData(bool $includeAttestedData): string
    {
        $rpIdHash = hash('sha256', $this->rpId, true);
        $flags    = $includeAttestedData ? 0x45 : 0x05; // UP|UV(|AT)
        $data     = $rpIdHash . chr($flags) . pack('N', $this->signCount);

        if ($includeAttestedData) {
            $coseKey = $this->coseKey();
            $credLen = pack('n', strlen($this->credentialIdRaw));
            $data .= $this->aaguid . $credLen . $this->credentialIdRaw . $coseKey;
        }

        return $data;
    }

    /**
     * COSE_Key (EC2, P-256, ES256) as CBOR.
     */
    private function coseKey(): string
    {
        return (string) MapObject::create()
            ->add(UnsignedIntegerObject::create(1), UnsignedIntegerObject::create(2))     // kty: EC2
            ->add(UnsignedIntegerObject::create(3), NegativeIntegerObject::create(-7))    // alg: ES256
            ->add(NegativeIntegerObject::create(-1), UnsignedIntegerObject::create(1))    // crv: P-256
            ->add(NegativeIntegerObject::create(-2), ByteStringObject::create($this->x)) // x
            ->add(NegativeIntegerObject::create(-3), ByteStringObject::create($this->y)); // y
    }

    /**
     * ES256 signature in ASN.1/DER (CoseSignatureFixer converts it to raw R‖S).
     */
    private function sign(string $data): string
    {
        $signature = '';
        if (! openssl_sign($data, $signature, $this->privateKeyPem, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign the assertion: ' . (openssl_error_string() ?: 'unknown error'));
        }

        return $signature;
    }

    /**
     * Locate an OpenSSL config file when the default cannot be found.
     *
     * On a correctly configured (CI) environment OpenSSL finds its config
     * automatically and this returns null. On some Windows installs the
     * default lookup fails ("configuration file routines::no such file"),
     * which makes openssl_pkey_new() return false; in that case we fall back
     * to an openssl.cnf shipped alongside the PHP binary.
     */
    private function opensslConfig(): ?string
    {
        // Allow an explicit override (e.g. CI) to win.
        $env = getenv('OPENSSL_CONF');
        if (is_string($env) && $env !== '' && is_file($env)) {
            return $env;
        }

        // Probe the default behaviour with a throwaway key; if it works, no
        // explicit config is needed.
        $probe = @openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name'       => 'prime256v1',
        ]);
        if ($probe !== false) {
            return null;
        }

        // Drain stale OpenSSL errors from the failed probe.
        while (openssl_error_string() !== false) {
            // no-op
        }

        $phpDir     = dirname(PHP_BINARY);
        $candidates = [
            $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'ssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            $phpDir . DIRECTORY_SEPARATOR . 'extras' . DIRECTORY_SEPARATOR . 'openssl' . DIRECTORY_SEPARATOR . 'openssl.cnf',
            $phpDir . DIRECTORY_SEPARATOR . 'openssl.cnf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private static function b64url(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function b64urlDecode(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/'), true) ?: '';
    }
}
