<?php

namespace App\Http\Requests\InviteLink;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Opening an invite link (also without being logged in). Week menu links only work for apps and websites that know
 * week menus: they ask with ?supports=week_menu. An older app (which opens every /auth/ link) gets "not found" for one,
 * so it neither breaks nor joins a menu it can't show.
 */
class InviteLinkRequest extends FormRequest
{
    /**
     * Anyone may open a link: what it invites to is checked by the controller
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * ?supports isn't validated: an odd value (or an array) just means "doesn't know week menus", never an error
     *
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }

    // Whether the app or website asking knows week menu links (?supports=week_menu, possibly among others)
    public function supportsWeekMenu(): bool
    {
        $supports = $this->query('supports');

        return is_string($supports) && in_array('week_menu', explode(',', $supports), true);
    }

    /**
     * Custom message for validation, in the user's language like the push notifications
     *
     * @return array
     */
    public function messages(): array
    {
        return __('recipes.validation.rules', [], $this->user()?->language ?: 'nl');
    }
}
