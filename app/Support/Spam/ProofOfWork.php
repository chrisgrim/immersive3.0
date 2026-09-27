<?php

namespace App\Support\Spam;

use AltchaOrg\Altcha\Algorithm\Pbkdf2;
use AltchaOrg\Altcha\Altcha;
use AltchaOrg\Altcha\CreateChallengeOptions;
use AltchaOrg\Altcha\VerifySolutionOptions;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Self-hosted ALTCHA proof-of-work for public forms (no third-party service).
 *
 * The browser fetches a signed challenge, spends a second or so solving it in
 * the background, and sends the solution with the form. Cheap for one person,
 * expensive for a bot posting thousands. Three extra checks on top of the
 * library's own signature/expiry verification:
 *  - replay: each solved challenge is accepted once (keyed by its nonce);
 *  - too fast: the challenge carries its signed issue time, and a form sent
 *    back within MIN_SECONDS of opening is a script, not a person typing;
 *  - anything malformed is simply "not verified", never an exception.
 */
class ProofOfWork
{
    /** PBKDF2 iterations per attempt, and the attempt range the browser must search. */
    private const COST = 1000;

    private const COUNTER_MIN = 2000;

    private const COUNTER_MAX = 5000;

    private const TTL_SECONDS = 1800;

    public const MIN_SECONDS = 3;

    public function challenge(): array
    {
        return $this->altcha()->createChallenge(new CreateChallengeOptions(
            algorithm: new Pbkdf2,
            cost: self::COST,
            counter: random_int(self::COUNTER_MIN, self::COUNTER_MAX),
            expiresAt: now()->timestamp + self::TTL_SECONDS,
            data: ['issued' => now()->timestamp],
        ))->toArray();
    }

    public function verify(?string $payload): bool
    {
        if (! is_string($payload) || $payload === '' || strlen($payload) > 8192) {
            return false;
        }

        try {
            $options = new VerifySolutionOptions(payload: $payload, algorithm: new Pbkdf2);
            $result = $this->altcha()->verifySolution($options);
        } catch (Throwable) {
            return false;
        }

        if (! $result->verified) {
            return false;
        }

        $params = $options->payload->challenge->parameters;
        $issued = (int) ($params->data['issued'] ?? 0);
        if (now()->timestamp - $issued < self::MIN_SECONDS) {
            return false;
        }

        return Cache::add('pow:used:'.$params->nonce, true, self::TTL_SECONDS);
    }

    private function altcha(): Altcha
    {
        // Derived from APP_KEY so there's no extra secret to manage per server.
        $key = (string) config('app.key');

        return new Altcha(
            hmacSignatureSecret: hash_hmac('sha256', 'altcha-signature', $key),
            hmacKeySignatureSecret: hash_hmac('sha256', 'altcha-key-signature', $key),
        );
    }
}
