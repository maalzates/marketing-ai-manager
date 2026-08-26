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

    public function group(): IntegrationGroup
    {
        return match ($this) {
            self::ANTHROPIC, self::OPENAI, self::GEMINI => IntegrationGroup::MODELS,
            self::META => IntegrationGroup::ADS,
            self::GOOGLE => IntegrationGroup::STORAGE,
            self::APIFY => IntegrationGroup::RESEARCH,
            self::YOUTUBE, self::TIKTOK => IntegrationGroup::CHANNELS,
        };
    }

    /** One line, in the second person, on what this provider does for the account. */
    public function purpose(): string
    {
        return match ($this) {
            self::ANTHROPIC => 'Claude. El más caro de los tres y el que mejor escribe en castellano.',
            self::OPENAI => 'GPT. El catálogo más amplio, con opciones muy baratas para tareas mecánicas.',
            self::GEMINI => 'Gemini. El más barato en contextos largos.',
            self::META => 'Campañas de Facebook e Instagram, métricas de anuncios y publicación en Instagram.',
            self::GOOGLE => 'Drive, para guardar y servir las piezas de contenido.',
            self::APIFY => 'Anuncios y comentarios públicos de la competencia.',
            self::YOUTUBE => 'Métricas de YouTube. Requiere revisión manual de Google y aún no está en uso.',
            self::TIKTOK => 'Publicación en TikTok. Fuera de alcance por ahora.',
        };
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
