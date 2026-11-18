<?php

namespace App\Http\Requests\InviteLink;

/**
 * Accepting an invite link. For a week menu link, someone with a menu of their own can bring its dishes along: the menu
 * the screen named (bring_menu_id), or with only bring_dishes the one the link's preview named. Lists and cookbooks
 * take neither.
 */
class InviteLinkAcceptRequest extends InviteLinkRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'bring_menu_id' => 'nullable|integer|min:1',
            'bring_dishes' => 'nullable|boolean',
        ];
    }

    public function bringMenuId(): ?int
    {
        return $this->validated('bring_menu_id') !== null ? (int) $this->validated('bring_menu_id') : null;
    }

    public function bringDishes(): bool
    {
        return (bool) $this->validated('bring_dishes');
    }
}
