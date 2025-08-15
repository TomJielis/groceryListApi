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
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function register(Request $request): JsonResponse
    {
        $user = dispatch_sync(new StoreUserJob($request->all()));

        return response()->json([
            $user,
        ]);
    }

    /**
     * @param Request $request
     *
     * @return JsonResponse
     */
    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        return response()->json([
            'data' => [
                'access_token' => $token,
                'token_type' => 'bearer',
                'expires_in' => auth('api')->factory()->getTTL() * 60,
                'auth_user' => auth()->user(),
            ]
        ]);
    }

    public function update(UpdateUserRequest $request, UserRequestTransformer $transformer, User $user){
       $userData = $transformer->transform($request);
       $user = (new UpdateUserJob($userData, $user))->handle();

       return response()->json([
           'user' => $user
       ]);
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
