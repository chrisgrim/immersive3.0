<?php

namespace Tests\Support;

/**
 * A pretend password manager for passkey tests: makes a real P-256 key
 * pair and answers registration and sign-in exactly as a browser would
 * hand them to the page (base64url JSON), with "none" attestation like
 * every synced passkey. So the tests run the real webauthn-lib checks
 * (challenge, origin, RP ID hash, signature, counter), nothing mocked.
 */
class FakePasskeyAuthenticator
{
    /** Google Password Manager, so the provider name and logo lookups have something to find. */
    public const AAGUID = 'ea9b8d66-4d01-1d21-3ce4-b6b48cb575d4';

    public readonly string $credentialId;

    private \OpenSSLAsymmetricKey $key;

    private int $counter = 0;

    private ?string $userHandle = null;

    public function __construct(
        private string $rpId = 'ei.test',
        private string $origin = 'https://ei.test',
    ) {
        $this->key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_EC, 'curve_name' => 'prime256v1']);
        $this->credentialId = random_bytes(16);
    }

    public function credentialIdBase64Url(): string
    {
        return self::b64u($this->credentialId);
    }

    /** The JSON a browser posts back from navigator.credentials.create(). */
    public function register(array $options): array
    {
        $this->userHandle = self::unb64u($options['user']['id']);

        $clientData = $this->clientData('webauthn.create', $options['challenge']);

        $authData = $this->authenticatorData(attested: true);

        $attestation = self::cbor(['fmt' => 'none', 'attStmt' => [], 'authData' => new CborBytes($authData)]);

        return [
            'id' => $this->credentialIdBase64Url(),
            'rawId' => $this->credentialIdBase64Url(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64u($clientData),
                'attestationObject' => self::b64u($attestation),
                'transports' => ['internal', 'hybrid'],
            ],
            'clientExtensionResults' => (object) [],
            'authenticatorAttachment' => 'platform',
        ];
    }

    /** The JSON a browser posts back from navigator.credentials.get(). */
    public function authenticate(array $options): array
    {
        $clientData = $this->clientData('webauthn.get', $options['challenge']);

        $authData = $this->authenticatorData(attested: false);

        openssl_sign($authData.hash('sha256', $clientData, true), $signature, $this->key, OPENSSL_ALGO_SHA256);

        return [
            'id' => $this->credentialIdBase64Url(),
            'rawId' => $this->credentialIdBase64Url(),
            'type' => 'public-key',
            'response' => [
                'clientDataJSON' => self::b64u($clientData),
                'authenticatorData' => self::b64u($authData),
                'signature' => self::b64u($signature),
                'userHandle' => self::b64u((string) $this->userHandle),
            ],
            'clientExtensionResults' => (object) [],
            'authenticatorAttachment' => 'platform',
        ];
    }

    private function clientData(string $type, string $challenge): string
    {
        return json_encode([
            'type' => $type,
            'challenge' => $challenge,
            'origin' => $this->origin,
            'crossOrigin' => false,
        ], JSON_UNESCAPED_SLASHES);
    }

    private function authenticatorData(bool $attested): string
    {
        // User present, user verified, backup eligible + backed up (a
        // synced passkey, which keeps its sign counter at 0).
        $flags = 0x01 | 0x04 | 0x08 | 0x10 | ($attested ? 0x40 : 0);

        $data = hash('sha256', $this->rpId, true).chr($flags).pack('N', $this->counter);

        if ($attested) {
            $data .= hex2bin(str_replace('-', '', self::AAGUID))
                .pack('n', strlen($this->credentialId))
                .$this->credentialId
                .$this->coseKey();
        }

        return $data;
    }

    private function coseKey(): string
    {
        $ec = openssl_pkey_get_details($this->key)['ec'];

        // COSE EC2 key: kty 2, alg -7 (ES256), crv 1 (P-256), x, y.
        return self::cbor([
            1 => 2,
            3 => -7,
            -1 => 1,
            -2 => new CborBytes(str_pad($ec['x'], 32, "\0", STR_PAD_LEFT)),
            -3 => new CborBytes(str_pad($ec['y'], 32, "\0", STR_PAD_LEFT)),
        ]);
    }

    /** Just enough CBOR for the two maps above. */
    private static function cbor(mixed $value): string
    {
        return match (true) {
            $value instanceof CborBytes => self::cborHead(2, strlen($value->bytes)).$value->bytes,
            is_int($value) && $value >= 0 => self::cborHead(0, $value),
            is_int($value) => self::cborHead(1, -1 - $value),
            is_string($value) => self::cborHead(3, strlen($value)).$value,
            is_array($value) => self::cborHead(5, count($value)).implode('', array_map(
                fn ($k, $v) => self::cbor($k).self::cbor($v),
                array_keys($value),
                $value,
            )),
        };
    }

    private static function cborHead(int $major, int $length): string
    {
        $type = $major << 5;

        return match (true) {
            $length < 24 => chr($type | $length),
            $length < 256 => chr($type | 24).chr($length),
            default => chr($type | 25).pack('n', $length),
        };
    }

    private static function b64u(string $bytes): string
    {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    private static function unb64u(string $text): string
    {
        return base64_decode(strtr($text, '-_', '+/'));
    }
}
