<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Enums;

/**
 * Why a verification call did not succeed. Every provider signals this differently and
 * none of them signal it reliably through the HTTP status — Gemini rejects a bad key with
 * 400 and Apify reports a missing Actor with 400 too. Telling a user to regenerate a key
 * that was fine is the worst thing this module can do, so the classification is per
 * provider, read from the error body, and never inferred from the status alone.
 */
enum VerificationFailure: string
{
    case CREDENTIAL_REJECTED = 'credential_rejected';

    case PERMISSION_DENIED = 'permission_denied';

    case PROVIDER_UNAVAILABLE = 'provider_unavailable';
}
