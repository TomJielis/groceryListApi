<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Jobs\Users\LoginJob;
use App\Jobs\Users\StoreUserJob;
use App\Jobs\Users\UpdateUserJob;
use App\Models\User;
use App\Transformers\Users\UserRequestTransformer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $user = (new StoreUserJob($request->all()))->handle();
        return response()->json($user);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $user = (new LoginJob($request))->handle();
        return response()->json($user);
    }

    /**
     * @param Request $request
     *
     * @return mixed
     */
    public function me(Request $request)
    {
       $user = Auth::user();

        return response()->json([
            'user' => $user
        ]);
    }
}
