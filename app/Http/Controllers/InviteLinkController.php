<?php

namespace App\Http\Controllers;

use App\Jobs\SendPushNotification;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryList;
use App\Models\GroceryListInvites;
use App\Models\GroceryListInvitesStatus;
use App\Models\InviteLink;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Invite links: instead of an email, a link that's shared over WhatsApp and the like. Whoever opens it sees what it
 * invites to and accepts it with one tap, after which they're in like after an accepted email invite. Who may share
 * by email may make a link (lists: anyone in it, cookbooks: their creator). A link works for several people until it
 * expires (after InviteLink::DAYS_VALID days) or its maker withdraws it.
 */
class InviteLinkController extends Controller
{
    public function storeForList(Request $request, int $id): JsonResponse
    {
        // The global scope only finds lists the user is in
        $list = GroceryList::find($id);
        abort_unless($list, 404);

        return $this->link($request, InviteLink::forTarget(['grocery_list_id' => $list->id], $request->user()));
    }

    public function storeForCookbook(Request $request, int $id): JsonResponse
    {
        $cookbook = Cookbook::visibleTo($request->user())->findOrFail($id);
        abort_unless($cookbook->isCreatedBy($request->user()), 403, __('recipes.only_owner', [], $this->language($request)));

        return $this->link($request, InviteLink::forTarget(['cookbook_id' => $cookbook->id], $request->user()));
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
    public function show(Request $request, string $token): JsonResponse
    {
        $link = InviteLink::where('token', $token)->with(['groceryList', 'cookbook', 'createdBy:id,name'])->first();
        if (!$link || (!$link->groceryList && !$link->cookbook)) {
            return response()->json(['data' => ['status' => 'not_found']]);
        }
        if (!$link->isUsable()) {
            return response()->json(['data' => ['status' => 'expired', 'type' => $link->cookbook_id ? 'cookbook' : 'list']]);
        }

        $user = $request->user('sanctum');

        return response()->json(['data' => [
            'status' => 'valid',
            'type' => $link->cookbook_id ? 'cookbook' : 'list',
            'id' => $link->cookbook_id ?? $link->grocery_list_id,
            'name' => $link->cookbook?->name ?? $link->groceryList->name,
            'invited_by' => $link->createdBy?->name,
            'expires_at' => $link->expires_at,
            // Known only when logged in: whoever is already in it opens it instead of accepting again
            'already_member' => $user ? $this->isMember($link, $user->id) : null,
        ]]);
    }

    // Joins the list or cookbook the link invites to, like accepting an email invite
    public function accept(Request $request, string $token): JsonResponse
    {
        $user = $request->user();
        $language = $this->language($request);
        $link = InviteLink::where('token', $token)->with(['groceryList', 'cookbook'])->first();
        if (!$link || (!$link->groceryList && !$link->cookbook)) {
            return response()->json(['message' => __('invite-links.not_found', [], $language)], 404);
        }
        if (!$link->isUsable()) {
            return response()->json(['message' => __('invite-links.expired', [], $language)], 410);
        }

        $type = $link->cookbook_id ? 'cookbook' : 'list';
        $id = $link->cookbook_id ?? $link->grocery_list_id;
        if ($this->isMember($link, $user->id)) {
            return response()->json(['message' => __('invite-links.already_member', [], $language), 'data' => ['type' => $type, 'id' => $id]]);
        }

        if ($link->cookbook) {
            // An earlier email invite to this person becomes the accepted one, so there's never a second pending
            CookbookInvite::updateOrCreate(
                ['cookbook_id' => $link->cookbook_id, 'user_id' => $user->id],
                ['email' => $user->email, 'status' => GroceryListInvitesStatus::ACCEPTED],
            );
            CookbookInvite::where('cookbook_id', $link->cookbook_id)->whereNull('user_id')->where('email', $user->email)->delete();
            $this->notifyMaker($link, $user, (int) $link->cookbook->created_by, 'cookbook_invite_accepted', ['cookbook' => $link->cookbook->name], '/recipes');
        } else {
            GroceryListInvites::updateOrCreate(
                ['grocery_list_id' => $link->grocery_list_id, 'user_id' => $user->id],
                ['email' => $user->email, 'status' => GroceryListInvitesStatus::ACCEPTED],
            );
            GroceryListInvites::where('grocery_list_id', $link->grocery_list_id)->whereNull('user_id')->where('email', $user->email)->delete();
            $this->notifyMaker($link, $user, (int) $link->groceryList->created_by, 'invite_accepted', ['list' => $link->groceryList->name], '/list/' . $link->grocery_list_id);
        }

        return response()->json(['message' => __('invite-links.accepted', [], $language), 'data' => ['type' => $type, 'id' => $id]]);
    }

    private function isMember(InviteLink $link, int $userId): bool
    {
        if ($link->cookbook) {
            return in_array($userId, $link->cookbook->memberIds(), true);
        }

        return $link->groceryList && in_array($userId, $link->groceryList->memberIds(), true);
    }

    // The owner hears someone joined, like when an email invite is accepted
    private function notifyMaker(InviteLink $link, $user, int $ownerId, string $type, array $replace, string $url): void
    {
        if ($ownerId !== $user->id) {
            SendPushNotification::dispatch($ownerId, $type, ['user' => $user->name, ...$replace], ['url' => $url]);
        }
    }

    private function link(Request $request, InviteLink $link): JsonResponse
    {
        return response()->json(['data' => [
            'token' => $link->token,
            'url' => $link->url(),
            'expires_at' => $link->expires_at,
        ]]);
    }
}
