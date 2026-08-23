<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Enums;

enum IntegrationProvider: string
{
    case ANTHROPIC = 'anthropic';
    case OPENAI = 'openai';
    case GEMINI = 'gemini';
    case APIFY = 'apify';
    case META = 'meta';
    case GOOGLE = 'google';
    case YOUTUBE = 'youtube';
    case TIKTOK = 'tiktok';

    public function kind(): IntegrationKind
    {
        return match ($this) {
            self::ANTHROPIC, self::OPENAI, self::GEMINI, self::APIFY => IntegrationKind::API_KEY,
            self::META, self::GOOGLE, self::YOUTUBE, self::TIKTOK => IntegrationKind::OAUTH,
        };
    }

    /**
     * Google issues one grant per OAuth client, so Drive and YouTube travel the same
     * authorisation, token and refresh endpoints — only the requested scopes differ.
     */
    public function usesGoogleOAuth(): bool
    {
        return $this === self::GOOGLE || $this === self::YOUTUBE;
    }

    public function label(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'Anthropic',
            self::OPENAI => 'OpenAI',
            self::GEMINI => 'Gemini',
            self::APIFY => 'Apify',
            self::META => 'Meta',
            self::GOOGLE => 'Google',
            self::YOUTUBE => 'YouTube',
            self::TIKTOK => 'TikTok',
        };
    }
}
