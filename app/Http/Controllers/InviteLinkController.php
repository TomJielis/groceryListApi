<?php

namespace App\Http\Controllers;

use App\Data\InviteTarget;
use App\Http\Requests\InviteLink\InviteLinkAcceptRequest;
use App\Http\Requests\InviteLink\InviteLinkRequest;
use App\Http\Resources\InviteLinkResource;
use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\InviteLink;
use App\Models\User;
use App\Models\WeekMenu;
use App\Services\WeekMenuJoiner;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invite links, the only way to share a list, cookbook or week menu: a link that's shared over WhatsApp and the like.
 * Whoever opens it sees what it invites to and accepts it with one tap, after which they're a member (a row in
 * grocery_list_invites / cookbook_invites / week_menu_members). Only the creator of a list, cookbook or week menu
 * makes a link. A link works for several people until it expires (after InviteLink::DAYS_VALID days) or its
 * maker withdraws it. Week menu links only work for apps that know them (see InviteLinkRequest).
 */
class InviteLinkController extends Controller
{
    public function __construct(private WeekMenuJoiner $weekMenus)
    {
    }

    public function storeForList(Request $request, int $id): InviteLinkResource
    {
        // The global scope only finds lists the user is in; who is in it is up to its creator, like a cookbook
        $list = GroceryList::find($id);
        abort_unless($list, 404);
        abort_unless((int) $list->created_by === (int) $request->user()->id, 403, __('lists.only_owner_invites', [], $this->language($request)));

        return new InviteLinkResource(InviteLink::forTarget(['grocery_list_id' => $list->id], $request->user()));
    }

    public function storeForCookbook(Request $request, int $id): InviteLinkResource
    {
        $cookbook = Cookbook::visibleTo($request->user())->findOrFail($id);
        abort_unless($cookbook->isCreatedBy($request->user()), 403, __('recipes.only_owner', [], $this->language($request)));

        return new InviteLinkResource(InviteLink::forTarget(['cookbook_id' => $cookbook->id], $request->user()));
    }

    public function storeForWeekMenu(Request $request, int $id): InviteLinkResource
    {
        $menu = WeekMenu::visibleTo($request->user())->findOrFail($id);
        abort_unless($menu->isCreatedBy($request->user()), 403, __('week-menus.only_owner', [], $this->language($request)));

        return new InviteLinkResource(InviteLink::forTarget(['week_menu_id' => $menu->id], $request->user()));
    }

    // The maker withdraws a link: opening it then shows that it no longer works
    public function delete(Request $request, string $token): JsonResponse
    {
        $link = InviteLink::where('token', $token)->where('created_by', $request->user()->id)->firstOrFail();
        $link->update(['revoked_at' => now()]);

        return response()->json(['message' => __('invite-links.withdrawn', [], $this->language($request))]);
    }

    /**
     * What a link invites to, for the screen that opens it (the app, or the website for someone without the app):
     * also without being logged in, so that screen can say what it's about before logging in. status is valid,
     * expired (also withdrawn) or not_found.
     */
    public function show(InviteLinkRequest $request, string $token): JsonResponse
    {
        $link = $this->findLink($request, $token, ['createdBy:id,name']);
        $target = $link?->target();
        if (!$target) {
            return response()->json(['data' => ['status' => 'not_found']]);
        }
        if (!$link->isUsable()) {
            return response()->json(['data' => ['status' => 'expired', 'type' => $target->type]]);
        }

        $user = $request->user('sanctum');

        return response()->json(['data' => [
            'status' => 'valid',
            ...$target->toArray(),
            'invited_by' => $link->createdBy?->name,
            'expires_at' => $link->expires_at,
            // Known only when logged in: whoever is already in it opens it instead of accepting again
            'already_member' => $user ? $link->hasMember($user->id) : null,
            // A week menu link, for someone with an own menu with dishes: the screen asks whether to bring them along
            ...($target->type === InviteTarget::WEEK_MENU && $user
                ? ['own_menu' => $this->weekMenus->ownMenuToBring($user, $target->id)]
                : []),
        ]]);
    }

    // Joins the list, cookbook or week menu the link invites to
    public function accept(InviteLinkAcceptRequest $request, string $token): JsonResponse
    {
        $user = $request->user();
        $language = $this->language($request);
        $link = $this->findLink($request, $token);
        $target = $link?->target();
        if (!$target) {
            return response()->json(['message' => __('invite-links.not_found', [], $language)], 404);
        }
        if (!$link->isUsable()) {
            return response()->json(['message' => __('invite-links.expired', [], $language)], 410);
        }
        if ($link->hasMember($user->id)) {
            return $this->joined($target, true, $language);
        }

        switch ($target->type) {
            case InviteTarget::WEEK_MENU:
                try {
                    $result = $this->weekMenus->join($link, $user, $request->bringMenuId(), $request->bringDishes());
                } catch (LockTimeoutException) {
                    return response()->json(['message' => __('invite-links.busy', [], $language)], 429);
                }
                if ($result === WeekMenuJoiner::GONE) {
                    return response()->json(['message' => __('invite-links.not_found', [], $language)], 404);
                }
                if ($result === WeekMenuJoiner::MEMBER) {
                    return $this->joined($target, true, $language);
                }
                // To the recipes screen, which every app version has (a newer one opens the week menu from there)
                $this->notifyMaker($user, (int) $link->weekMenu->created_by, 'week_menu_invite_accepted', ['menu' => $target->name], '/recipes?view=week-menu');
                break;

            case InviteTarget::COOKBOOK:
                CookbookInvite::updateOrCreate(
                    ['cookbook_id' => $target->id, 'user_id' => $user->id],
                    ['status' => GroceryListInvitesStatus::ACCEPTED],
                );
                $this->notifyMaker($user, (int) $link->cookbook->created_by, 'cookbook_invite_accepted', ['cookbook' => $target->name], '/recipes');
                break;

            default:
                GroceryListInvites::updateOrCreate(
                    ['grocery_list_id' => $target->id, 'user_id' => $user->id],
                    ['status' => GroceryListInvitesStatus::ACCEPTED],
                );
                $this->notifyMaker($user, (int) $link->groceryList->created_by, 'invite_accepted', ['list' => $target->name], '/list/' . $target->id);
        }

        return $this->joined($target, false, $language);
    }

    /**
     * The link with what it invites to; null when there's no such link, or it's a week menu link and the app asking
     * doesn't know week menus (it then reads as not found)
     *
     * @param array<int, string> $with
     */
    private function findLink(InviteLinkRequest $request, string $token, array $with = []): ?InviteLink
    {
        $link = InviteLink::where('token', $token)->with(['groceryList', 'cookbook', 'weekMenu', ...$with])->first();

        return $link && ($link->week_menu_id === null || $request->supportsWeekMenu()) ? $link : null;
    }

    // "You're in X now" (or were already), with what the app needs to open it
    private function joined(InviteTarget $target, bool $alreadyMember, string $language): JsonResponse
    {
        return response()->json([
            'message' => __($alreadyMember ? 'invite-links.already_member' : 'invite-links.accepted', [], $language),
            'data' => [...$target->toArray(), 'already_member' => $alreadyMember],
        ]);
    }

    // The owner hears someone joined
    private function notifyMaker(User $user, int $ownerId, string $type, array $replace, string $url): void
    {
        if ($ownerId !== $user->id) {
            SendPushNotification::dispatch($ownerId, $type, ['user' => $user->name, ...$replace], ['url' => $url]);
        }
    }
}
