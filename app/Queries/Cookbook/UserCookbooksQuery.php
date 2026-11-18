<?php

namespace App\Queries\Cookbook;

use App\Models\Cookbook;
use Illuminate\Http\Request;

/**
 * The cookbooks of the signed-in user (their own and the ones shared with them), looked up once per request: the
 * recipe overview asks for every recipe whether the user may change it
 */
final class UserCookbooksQuery
{
    /**
     * @return list<int>
     */
    public static function ids(Request $request): array
    {
        if (!$request->attributes->has('cookbook_ids')) {
            $request->attributes->set('cookbook_ids', Cookbook::visibleTo($request->user())->pluck('id')->map(fn ($id) => (int) $id)->all());
        }

        return $request->attributes->get('cookbook_ids');
    }

    public static function has(Request $request, int $cookbookId): bool
    {
        return in_array($cookbookId, self::ids($request), true);
    }
}
