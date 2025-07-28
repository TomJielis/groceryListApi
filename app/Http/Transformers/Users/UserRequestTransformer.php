<?php
namespace App\Transformers\Users;

use App\Data\RecipeData;
use App\Data\UserData;
use App\Models\User;
use App\Transformers\Transformer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class UserRequestTransformer extends Transformer
{

    /**
     * Transform an item.
     *
     * @param    $item
     *
     * @return  RecipeData
     */
    public function transform(&$item)
    {
        /**
         * @var  Request $item
         */

        /** @var User $user */
        $userData = new UserData();
        $userData->firstname = $item->input('firstname');
        $userData->prefix = $item->input('prefix') ?? null;
        $userData->lastname = $item->input('lastname');
        $userData->email = $item->input('email');

        return $userData;
    }
}
