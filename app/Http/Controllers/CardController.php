<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Card;
use Illuminate\Support\Facades\Auth;

class CardController extends Controller
{
    public function index(Request $request)
    {
        $cards = Card::where('user_id', auth()->user()->id)->get();

        return response()->json([
            'success' => true,
            'data' => $cards,
        ]);
    }
    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'attachment' => 'required|string', // base64 of url
        ]);

        $card = Card::create([
            'user_id' => Auth::id(),
            'title' => $request->title,
            'attachment' => $request->attachment,
        ]);

        return response()->json([
            'success' => true,
            'data' => $card,
        ]);
    }

    public function delete(Card $card)
    {
        if ($card->user_id !== Auth::id()) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized',
            ], 403);
        }

        $card->delete();

        return response()->json([
            'success' => true,
            'message' => 'Card deleted successfully',
        ]);
    }
}
