<?php

declare(strict_types=1);

namespace App\Modules\Core\Presentation\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

/**
 * The OAuth callback is the only route under `/api` a browser reaches by a top-level
 * navigation: the provider redirects there, not the SPA. So it is the only route whose
 * answer is a redirect back into the application instead of the JSON envelope — landing
 * the user on raw JSON, with the authorisation code left in the address bar, is the
 * failure this exists to prevent.
 */
readonly class OAuthCallbackRedirect
{
    private const string PATH = '/settings';

    public static function connected(string $provider): RedirectResponse
    {
        return self::to(['integration' => $provider, 'status' => 'connected']);
    }

    public static function failed(string $message): RedirectResponse
    {
        return self::to(['status' => 'error', 'message' => $message]);
    }

    /**
     * @param  array<string, string>  $query
     */
    private static function to(array $query): RedirectResponse
    {
        return Redirect::to(self::PATH.'?'.http_build_query($query));
    }
}
