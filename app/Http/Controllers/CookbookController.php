<?php

namespace App\Http\Controllers;

use App\Http\Requests\Cookbook\CookbookFavoriteRequest;
use App\Http\Requests\Cookbook\CookbookInviteStatusRequest;
use App\Http\Requests\Cookbook\CookbookRequest;
use App\Http\Requests\Cookbook\CookbookShareRequest;
use App\Jobs\SendPushNotification;
use App\Mail\CookbookInviteMail;
use App\Models\Cookbook;
use App\Models\CookbookInvite;
use App\Models\GroceryListInvitesStatus;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Cookbooks, like grocery lists: a user makes one or more (there's none until they do), and each can be shared by email. Everyone in it sees and
 * edits its recipes, but only the creator renames it, decides who is in it, and deletes it (for everyone). A cookbook
 * the user can't see answers 404, like one that doesn't exist.
 */
class CookbookController extends Controller
{
    // The cookbooks the user may see (oldest first), with who is in them and how many recipes
    public function index(Request $request): JsonResponse
    {
        $cookbooks = Cookbook::visibleTo($request->user())
            ->with($this->relations())
            ->withCount('recipes')
            ->orderBy('id')
            ->get();

        return response()->json([
            'data' => $cookbooks->map(fn (Cookbook $cookbook) => $this->present($request, $cookbook))->values(),
        ]);
    }

    public function store(CookbookRequest $request): JsonResponse
    {
        $cookbook = Cookbook::create([
            'name' => $request->validated('name'),
            'created_by' => $request->user()->id,
        ]);

        return response()->json(['data' => $this->present($request, $this->fresh($cookbook))], 201);
    }

    public function update(CookbookRequest $request, int $id): JsonResponse
    {
        $cookbook = $this->createdCookbook($request, $id);
        $cookbook->update(['name' => $request->validated('name')]);

        return response()->json(['data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // The creator deletes it for everyone, with its recipes; someone it was shared with leaves it, like a shared list
    public function delete(Request $request, int $id): JsonResponse
    {
        $user = $request->user();
        $cookbook = $this->visibleCookbook($request, $id);

        if (!$cookbook->isCreatedBy($user)) {
            $cookbook->acceptedInvites()->where('user_id', $user->id)->delete();
            $this->forgetFavorite($cookbook, $user->id);

            return response()->json(['message' => __('recipes.left', [], $this->language($request))]);
        }

        // Collected before the invites are gone
        $memberIds = array_diff($cookbook->memberIds(), [$user->id]);
        $cookbook->deleteWithRecipes();

        foreach ($memberIds as $memberId) {
            SendPushNotification::dispatch(
                $memberId,
                'cookbook_deleted',
                ['user' => $user->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
            );
        }

        return response()->json(['message' => __('recipes.cookbook_deleted', [], $this->language($request))]);
    }

    // The creator invites someone by email, like sharing a list: someone without an account gets an email to register
    public function share(CookbookShareRequest $request, int $id): JsonResponse
    {
        $cookbook = $this->createdCookbook($request, $id);
        $email = trim($request->validated('email'));
        $language = $this->language($request);

        // Emails are kept as typed and the database compares them ignoring case, so this does too
        if (strcasecmp($email, $request->user()->email) === 0) {
            return response()->json(['message' => __('recipes.share_self', [], $language)], 400);
        }

        $invitedUser = User::where('email', $email)->first();

        // The existing invite for this person. Without an account only the email can match: matching on an empty
        // user_id would pick up (and overwrite) another invite to an address without an account.
        $existingInvites = $cookbook->invites()->where(function ($query) use ($invitedUser, $email) {
            $query->where('email', $email);
            if ($invitedUser) {
                $query->orWhere('user_id', $invitedUser->id);
            }
        });

        // Already in it: nothing to send, and no second invite that would show up as pending
        if ((clone $existingInvites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists()) {
            return response()->json(['message' => __('recipes.already_member', [], $language), 'data' => $this->present($request, $this->fresh($cookbook))]);
        }

        // A declined or still pending invite is sent again
        $invite = $existingInvites->first() ?? new CookbookInvite(['cookbook_id' => $cookbook->id]);
        $invite->fill([
            'user_id' => $invitedUser?->id,
            'email' => $email,
            'status' => GroceryListInvitesStatus::PENDING,
        ])->save();

        // Someone with an account gets a push (the invite shows on the recipes screen), and the email only when the push
        // reaches none of their devices; someone without an account gets the email to register
        $mail = new CookbookInviteMail($request->user(), $cookbook, $invitedUser, $email);
        if ($invitedUser) {
            SendPushNotification::dispatch(
                $invitedUser->id,
                'cookbook_shared',
                ['user' => $request->user()->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
                $mail,
                $email,
            );
        } else {
            app(\App\Services\MailService::class)->send($mail, $email);
        }

        return response()->json(['message' => __('recipes.shared', [], $language), 'data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // The creator removes someone, or withdraws an invite; the cookbook and its recipes stay
    public function unshare(Request $request, int $id, int $userId): JsonResponse
    {
        $user = $request->user();
        $cookbook = $this->createdCookbook($request, $id);

        $invites = $cookbook->invites()->where('user_id', $userId);
        // Only someone who actually joined is told; a pending invite that is withdrawn needs no push
        $wasMember = (clone $invites)->where('status', GroceryListInvitesStatus::ACCEPTED)->exists();
        $invites->delete();
        $this->forgetFavorite($cookbook, $userId);

        if ($wasMember && $userId !== $user->id) {
            SendPushNotification::dispatch(
                $userId,
                'cookbook_removed',
                ['user' => $user->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
            );
        }

        return response()->json(['message' => __('recipes.member_removed', [], $this->language($request)), 'data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // The cookbook new recipes go into from "all recipes", like the favorite list; null clears it
    public function favorite(CookbookFavoriteRequest $request): JsonResponse
    {
        $user = $request->user();
        $cookbookId = $request->validated('cookbook_id');
        if ($cookbookId !== null) {
            $this->visibleCookbook($request, (int) $cookbookId);
        }

        $user->favorite_cookbook_id = $cookbookId;
        $user->save();

        return response()->json(['message' => __('recipes.favorite_updated', [], $this->language($request))]);
    }

    // The creator withdraws an invite that wasn't accepted yet, also one to an address without an account
    public function withdrawInvite(Request $request, int $id, int $inviteId): JsonResponse
    {
        $cookbook = $this->createdCookbook($request, $id);
        $cookbook->pendingInvites()->findOrFail($inviteId)->delete();

        return response()->json(['message' => __('recipes.invite_withdrawn', [], $this->language($request)), 'data' => $this->present($request, $this->fresh($cookbook))]);
    }

    // Invites waiting for the user: to their account, or to their address from before they had one
    public function pending(Request $request): JsonResponse
    {
        $invites = $this->openInvites($request)->with('cookbook.createdBy:id,name')->latest()->get();

        return response()->json([
            'data' => $invites->map(fn (CookbookInvite $invite) => [
                'id' => $invite->id,
                'cookbook' => ['id' => $invite->cookbook->id, 'name' => $invite->cookbook->name],
                'created_by' => $invite->cookbook->createdBy?->only(['id', 'name']),
                'created_at' => $invite->created_at,
            ])->values(),
        ]);
    }

    public function updateInviteStatus(CookbookInviteStatusRequest $request, int $inviteId): JsonResponse
    {
        $user = $request->user();
        $invite = $this->openInvites($request)->findOrFail($inviteId);
        $status = $request->validated('status');

        $invite->update(['status' => $status, 'user_id' => $user->id]);
        $cookbook = $invite->cookbook;

        if ($status === GroceryListInvitesStatus::ACCEPTED && !$cookbook->isCreatedBy($user)) {
            SendPushNotification::dispatch(
                (int) $cookbook->created_by,
                'cookbook_invite_accepted',
                ['user' => $user->name, 'cookbook' => $cookbook->name],
                ['url' => '/recipes'],
            );
        }

        return response()->json(['message' => __('recipes.invite_updated', [], $this->language($request))]);
    }

    private function openInvites(Request $request)
    {
        $user = $request->user();

        return CookbookInvite::where('status', GroceryListInvitesStatus::PENDING)
            ->where(fn ($query) => $query->where('user_id', $user->id)->orWhere('email', $user->email));
    }

    // Someone who is no longer in the cookbook doesn't keep it as their favorite
    private function forgetFavorite(Cookbook $cookbook, int $userId): void
    {
        User::whereKey($userId)->where('favorite_cookbook_id', $cookbook->id)->update(['favorite_cookbook_id' => null]);
    }

    private function visibleCookbook(Request $request, int $id): Cookbook
    {
        return Cookbook::visibleTo($request->user())->findOrFail($id);
    }

    // Renaming it and deciding who is in it is up to the creator only
    private function createdCookbook(Request $request, int $id): Cookbook
    {
        $cookbook = $this->visibleCookbook($request, $id);
        abort_unless($cookbook->isCreatedBy($request->user()), 403, __('recipes.only_owner', [], $this->language($request)));

        return $cookbook;
    }

    private function relations(): array
    {
        return ['createdBy:id,name', 'invites' => fn ($query) => $query->whereIn('status', [GroceryListInvitesStatus::PENDING, GroceryListInvitesStatus::ACCEPTED])->with('user:id,name')];
    }

    private function fresh(Cookbook $cookbook): Cookbook
    {
        return $cookbook->fresh()->load($this->relations())->loadCount('recipes');
    }

    private function present(Request $request, Cookbook $cookbook): array
    {
        $accepted = $cookbook->invites->where('status', GroceryListInvitesStatus::ACCEPTED)->filter(fn (CookbookInvite $invite) => $invite->user);

        return [
            'id' => $cookbook->id,
            'name' => $cookbook->name,
            'favorite' => (int) $request->user()->favorite_cookbook_id === (int) $cookbook->id,
            'created_by' => $cookbook->createdBy?->only(['id', 'name']),
            'recipes_count' => $cookbook->recipes_count,
            // The creator first, then who joined
            'members' => collect([$cookbook->createdBy?->only(['id', 'name'])])
                ->merge($accepted->map(fn (CookbookInvite $invite) => $invite->user->only(['id', 'name'])))
                ->filter()
                ->values(),
            // Invited but not joined yet; the creator can withdraw these
            'pending_invites' => $cookbook->invites
                ->where('status', GroceryListInvitesStatus::PENDING)
                ->map(fn (CookbookInvite $invite) => ['id' => $invite->id, 'email' => $invite->email])
                ->values(),
        ];
    }
}
