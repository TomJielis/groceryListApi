<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use App\Http\Requests\User\RegisterUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Jobs\Users\LoginJob;
use App\Jobs\Users\StoreUserJob;
use App\Jobs\Users\UpdateUserJob;
use App\Models\User;
use App\Transformers\Users\UserRequestTransformer;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;

class AuthController extends Controller
{

    public function createCsrfToken(Request $request)
    {
        $token = csrf_token();
        return response()->json([
            'csrf_token' => $token,
        ]);
    }

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
        ray($request->all());
        $user = (new LoginJob($request))->handle();

        return response()->json($user);
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

    /**
     * @param Request $request
     *
     * @return void
     */
    public function passwordReset(Request $request)
    {
        $user = User::where('email', '=', $request->get('email'))->first();

        if($user){
            /** @var User $user */
            $email = $user->email;

            if($email){
                ray(Carbon::now());
                $code = Crypt::encryptString($user->email . '-' . Carbon::now() . '-' .$user->id);

                $temporaryPasswordCode = new TemporaryPasswordCode();
                $temporaryPasswordCode->user_id = $user->id;
                $temporaryPasswordCode->code = $code;
                $temporaryPasswordCode->is_used = false;
                $temporaryPasswordCode->save();

                $url =  env('URL') . '/auth/reset-password/' . $code;
                $content = '<br />Beste ' . $user->firstname . ' ' . $user->prefix . ' ' . $user->lastname . ' , <br/><br/> Klik op de onderstaande knop om een nieuw wachtwoord in te stellen';
                $content .= '<br /><br/> <a target="_blank" rel="noopener noreferrer" href="' . $url . '" class="button button-blue" style="font-family: Avenir, Helvetica, sans-serif; box-sizing: border-box; border-radius: 3px; box-shadow: 0 2px 3px rgba(0, 0, 0, 0.16); color: #ffffff; display: inline-block; text-decoration: none; -webkit-text-size-adjust: none; background-color: #d0393b; border-top: 10px solid #d0393b; border-right: 18px solid #d0393b; border-bottom: 10px solid #d0393b; border-left: 18px solid #d0393b;"> Klik hier om een wachtwoord in te stellen</a>';
                $content .= '<br /><br/> Met vriendelijke groet,<br>'.  env('company_name')  .'<br />';
                $title = "Nieuw wachtwoord instellen";

                $mail = new ResetPassword($url, $user, $content, $title);

                Config::set('mail.from', [
                    'address' => 'noreply@cookbookapp.com',
                    'name'    => 'cookbookApp',
                ]);

                try{
                    Mail::to($email)
                        ->send($mail);
                }catch (\Exception $exception){
                    ray($exception);
                }
            }
        }else{

        }
    }

    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function validCode(Request $request)
    {
       $code = $request->get('code') . '==';

       $passwordCode = TemporaryPasswordCode::where('code', '=', $code)->first();
        return response()->json([
            $passwordCode,
        ]);
    }

    /**
     * @param RegisterUserRequest $request
     *
     * @return JsonResponse
     */
    public function setNewPassword(Request $request)
    {
        /** @var User $user */
      $user = User::find($request->get('userId'));
      if(isset($user)){
          $user->password = Hash::make($request->get('password'));
          $user->save();

          $temperaryCode = TemporaryPasswordCode::where('code', '=', $request->get('code') . '==')->first();
          $temperaryCode->is_used = true;
          $temperaryCode->save();
      }

        return response()->json([
            $user,
        ]);

    }
}
