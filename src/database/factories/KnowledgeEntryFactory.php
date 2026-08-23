<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Modules\Knowledge\Domain\Enums\KnowledgeType;
use App\Modules\Knowledge\Infrastructure\Persistence\KnowledgeEntry;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<KnowledgeEntry>
 */
class KnowledgeEntryFactory extends Factory
{
    protected $model = KnowledgeEntry::class;

    public function definition(): array
    {
        return [
            'type' => KnowledgeType::DomainRule,
            'key' => $this->faker->unique()->slug(3),
            'locale' => 'es',
            'title' => $this->faker->sentence(4),
            'body' => $this->faker->paragraphs(3, true),
            'metadata' => [],
            'version' => 1,
            'is_published' => true,
        ];
    }

    public function ofType(KnowledgeType $type): self
    {
        return $this->state(fn (): array => ['type' => $type]);
    }

    public function unpublished(): self
    {
        return $this->state(fn (): array => ['is_published' => false]);
    }

    public function version(int $version): self
    {
        return $this->state(fn (): array => ['version' => $version]);
    }
}
