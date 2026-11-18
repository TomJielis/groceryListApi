<?php

namespace App\Http\Controllers;

use App\Http\Requests\Card\CardStoreRequest;
use App\Http\Resources\CardResource;
use App\Models\Card;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// The user's loyalty cards. The answers carry success besides the card(s), and their messages are Dutch only.
class CardController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $cards = Card::where('user_id', $request->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => CardResource::collection($cards),
        ]);
    }

    public function store(CardStoreRequest $request): JsonResponse
    {
        $card = Card::create([
            'user_id' => $request->user()->id,
            'title' => $request->title(),
            'attachment' => $request->attachment(),
        ]);

        return response()->json([
            'success' => true,
            'data' => new CardResource($card),
        ]);
    }

    public function delete(Request $request, Card $card): JsonResponse
    {
        if ($card->user_id !== $request->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'Onbevoegd',
            ], 403);
        }

        $card->delete();

        return response()->json([
            'success' => true,
            'message' => 'Kaart is succesvol verwijderd',
        ]);
    }
}
