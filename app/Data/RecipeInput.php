<?php

namespace App\Data;

/**
 * A recipe as the form sends it (RecipeRequest): its own fields, its parts, and the user's other cookbooks it's also
 * shown in (null: left out by older apps, nothing changes there)
 */
final readonly class RecipeInput
{
    /**
     * @param array<string, mixed> $fields
     * @param list<array{name: string, amount?: float|int|null, unit?: string|null}> $ingredients
     * @param list<string> $steps
     * @param list<int>|null $shownIn
     */
    public function __construct(
        public int $cookbookId,
        public array $fields,
        public array $ingredients,
        public array $steps,
        public ?array $shownIn,
    ) {
    }
}
