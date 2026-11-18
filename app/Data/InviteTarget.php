<?php

namespace App\Data;

/**
 * What an invite link invites to: a list, a cookbook or a week menu, with its id and name (for "you're in X now", so
 * the app needs no other request first)
 */
final readonly class InviteTarget
{
    public const LIST = 'list';
    public const COOKBOOK = 'cookbook';
    public const WEEK_MENU = 'week_menu';

    public function __construct(
        public string $type,
        public int $id,
        public string $name,
    ) {
    }

    /**
     * @return array{type: string, id: int, name: string}
     */
    public function toArray(): array
    {
        return ['type' => $this->type, 'id' => $this->id, 'name' => $this->name];
    }
}
