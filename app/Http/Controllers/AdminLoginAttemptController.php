<?php

namespace App\Http\Controllers;

use App\Http\Requests\Admin\AdminLoginAttemptIndexRequest;
use App\Http\Resources\FailedLoginResource;
use App\Http\Resources\PageMeta;
use App\Queries\Security\FailedLoginsQuery;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Failed logins for the admin overview: the attempts themselves (paginated) plus a summary of
 * the same selection, so an attack on one account or from one address stands out.
 */
class AdminLoginAttemptController extends Controller
{
    public function index(AdminLoginAttemptIndexRequest $request, FailedLoginsQuery $failedLogins): AnonymousResourceCollection
    {
        $filter = $request->filter();
        $paginator = $failedLogins->page($filter, $request->perPage());

        return FailedLoginResource::collection($paginator->getCollection())->additional([
            'meta' => PageMeta::of($paginator),
            'summary' => $failedLogins->summary($filter, $paginator->total()),
        ]);
    }
}
