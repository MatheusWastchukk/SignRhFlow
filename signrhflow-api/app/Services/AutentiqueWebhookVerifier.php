<?php

namespace App\Services;

use Illuminate\Http\Request;

/**
 * Validação conforme documentação Autentique: HMAC-SHA256 do corpo bruto no header X-Autentique-Signature.
 *
 * @see https://docs.autentique.com.br/api/integration-basics/webhooks
 */
class AutentiqueWebhookVerifier
{
    public const SIGNATURE_HEADER = 'X-Autentique-Signature';

    public function shouldVerify(): bool
    {
        $secret = (string) config('services.autentique.webhook_secret');

        return $secret !== '';
    }

    public function verify(Request $request): bool
    {
        if (! $this->shouldVerify()) {
            return true;
        }

        $secret = (string) config('services.autentique.webhook_secret');
        $signature = (string) $request->header(self::SIGNATURE_HEADER, '');

        if ($signature === '') {
            return false;
        }

        $payload = $request->getContent();
        $calculated = hash_hmac('sha256', $payload, $secret);

        return hash_equals($calculated, $signature);
    }
}
