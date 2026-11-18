<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminEmailIndexRequest;
use App\Http\Resources\PageMeta;
use App\Http\Resources\SentEmailResource;
use App\Queries\Mail\SentEmailLogQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AdminEmailController extends Controller
{
    public function index(AdminEmailIndexRequest $request, SentEmailLogQuery $log): AnonymousResourceCollection
    {
        $query = $log->query($request->status(), $request->type(), $request->dateFrom(), $request->dateTo());

        if ($request->limit() !== null) {
            return SentEmailResource::collection($query->limit($request->limit())->get());
        }

        $paginator = $query->paginate($request->perPage());

        // The longer paging shape the website's mail log reads: also from/to and the page links
        return SentEmailResource::collection($paginator->getCollection())->additional([
            'meta' => [
                ...PageMeta::of($paginator),
                'from' => $paginator->firstItem(),
                'to' => $paginator->lastItem(),
            ],
            'links' => [
                'first' => $paginator->url(1),
                'last' => $paginator->url($paginator->lastPage()),
                'prev' => $paginator->previousPageUrl(),
                'next' => $paginator->nextPageUrl(),
            ],
        ]);
    }
}
