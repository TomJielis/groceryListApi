<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminUserIndexRequest;
use App\Http\Resources\AdminUserOverviewResource;
use App\Http\Resources\PageMeta;
use App\Queries\Users\UserOverviewQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The admin users overview: search, filters, sorting and paging on the server, with counts of all
 * users on top. (GET admin/users keeps its old shape for the website.)
 */
class AdminUserController extends Controller
{
    public function index(AdminUserIndexRequest $request, UserOverviewQuery $overview): AnonymousResourceCollection
    {
        $paginator = $overview->page($request->search(), $request->status(), $request->terms(), $request->sort(), $request->perPage());

        return AdminUserOverviewResource::collection($paginator->getCollection())->additional([
            'meta' => PageMeta::of($paginator),
            'summary' => $overview->summary(),
        ]);
    }
}
