<?php

declare(strict_types=1);

namespace App\Modules\Integrations\Domain\Enums;

/**
 * What a provider is *for*, which is not the same question as how it authenticates. Settings
 * lists eight providers and, ungrouped, they read as eight equally required steps — when in
 * truth one language model is enough and two of the eight are not wired up yet.
 */
enum IntegrationGroup: string
{
    case MODELS = 'models';
    case ADS = 'ads';
    case STORAGE = 'storage';
    case RESEARCH = 'research';
    case CHANNELS = 'channels';

    /** @return list<self> the order Settings shows them in: what the application needs first, first */
    public static function ordered(): array
    {
        return [self::MODELS, self::ADS, self::STORAGE, self::RESEARCH, self::CHANNELS];
    }

    public function position(): int
    {
        return array_search($this, self::ordered(), true) ?: 0;
    }

    public function label(): string
    {
        return match ($this) {
            self::MODELS => 'Modelos de lenguaje',
            self::ADS => 'Anuncios y publicación',
            self::STORAGE => 'Almacenamiento',
            self::RESEARCH => 'Investigación de la competencia',
            self::CHANNELS => 'Otros canales',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::MODELS => 'Escriben los guiones, proponen las campañas y resumen los comentarios. '
                .'Con uno conectado la aplicación funciona; los tres solo sirven para elegir por precio o por calidad.',
            self::ADS => 'Crea y gestiona las campañas de Meta, y publica en la cuenta de Instagram '
                .'vinculada a tu página. Sin esto no hay campañas ni publicación.',
            self::STORAGE => 'Guarda las piezas de contenido en tu propio Drive y las sirve a Instagram '
                .'cuando toca publicar. Los ficheros son tuyos y siguen ahí si dejas de usar la aplicación.',
            self::RESEARCH => 'Lee los anuncios y los comentarios públicos de la competencia. '
                .'Es lo que alimenta el análisis de competidores y la minería de comentarios.',
            self::CHANNELS => 'Todavía no están conectados a ninguna funcionalidad. '
                .'Aparecen aquí para que se vea qué falta, no para configurarlos hoy.',
        };
    }
}
