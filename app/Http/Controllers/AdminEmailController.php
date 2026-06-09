<?php

namespace App\Http\Controllers;

use App\Models\SentEmail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AdminEmailController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = SentEmail::with('triggeredBy:id,name,email')
            ->orderBy('created_at', 'desc')
            ->when($request->filled('status'), fn($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('type'), fn($q) => $q->where('type', $request->query('type')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('sent_at', '>=', $request->query('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('sent_at', '<=', $request->query('date_to')));

        if ($request->filled('limit')) {
            $emails = $query->limit((int) $request->query('limit'))->get();
            return response()->json(['data' => $emails]);
        }

        $perPage = min((int) $request->query('per_page', 20), 100);
        $paginator = $query->paginate($perPage);

        return response()->json([
            'data'  => $paginator->items(),
            'meta'  => [
                'current_page' => $paginator->currentPage(),
                'last_page'    => $paginator->lastPage(),
                'per_page'     => $paginator->perPage(),
                'total'        => $paginator->total(),
                'from'         => $paginator->firstItem(),
                'to'           => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last'  => $paginator->url($paginator->lastPage()),
                'prev'  => $paginator->previousPageUrl(),
                'next'  => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
