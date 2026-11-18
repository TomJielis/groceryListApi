<?php

namespace App\Http\Resources;

use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Queries\Cookbook\CookbookShownElsewhereQuery;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A cookbook with who is in it and how many recipes (load Cookbook::PRESENTED_WITH and count
 * Cookbook::PRESENTED_COUNTS). Answers 200 also for one just made (the controller says 201 where it is one).
 *
 * @mixin Cookbook
 */
class CookbookResource extends JsonResource
{
    /**
     * @param array<int, array{recipes: int, cookbooks: list<string>}>|null $shownElsewhereMap From
     *        CookbookShownElsewhereQuery for all cookbooks of the overview at once; looked up for this one when null
     */
    public function __construct(Cookbook $resource, private ?array $shownElsewhereMap = null)
    {
        parent::__construct($resource);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $isCreator = $this->isCreatedBy($request->user());
        $accepted = $this->acceptedInvites->filter(fn (CookbookInvite $invite) => $invite->user);

        return [
            'id' => $this->id,
            'name' => $this->name,
            'favorite' => (int) $request->user()->favorite_cookbook_id === (int) $this->id,
            'created_by' => $this->createdBy?->only(['id', 'name']),
            // Its own recipes and the ones of other cookbooks shown in it
            'recipes_count' => $this->recipes_count + ($this->shown_recipes_count ?? 0),
            // For the creator, who deletes it: its own recipes (they go with it), and how many of them other cookbooks
            // show, and which (they disappear there too)
            'own_recipes_count' => $isCreator ? (int) $this->recipes_count : null,
            'shown_elsewhere' => $isCreator ? $this->shownElsewhere() : null,
            // The creator first, then who joined
            'members' => collect([$this->createdBy?->only(['id', 'name'])])
                ->merge($accepted->map(fn (CookbookInvite $invite) => $invite->user->only(['id', 'name'])))
                ->filter()
                ->values(),
        ];
    }

    /**
     * @return array{recipes: int, cookbooks: list<string>}
     */
    private function shownElsewhere(): array
    {
        $map = $this->shownElsewhereMap ?? app(CookbookShownElsewhereQuery::class)->forCookbooks([$this->id]);

        return $map[$this->id] ?? ['recipes' => 0, 'cookbooks' => []];
    }
}
