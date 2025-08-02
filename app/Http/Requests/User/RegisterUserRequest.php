<?php

namespace App\Http\Requests\User;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterUserRequest extends FormRequest
{

    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, mixed>
     */
    public function rules()
    {
//        return [
//            'username'  => 'required|string|max:255|unique:users',
//            'password'  => 'required|string|min:8',
//            'firstname' => 'required|string',
//            'prefix'    => 'string',
//            'lastname'  => 'required|string',
//            'email'     => 'required|string|email|max:255|unique:users',
//        ];
    }

    public function failedValidation(Validator $validator)
    {
//        throw new HttpResponseException(response()->json([
//            'success'   => false,
//            'message'   => 'Validation errors',
//            'data'      => $validator->errors()
//        ]));
    }

    /**
     * Custom message for validation
     *
     * @return array
     */
    public function messages()
    {
//        return [
//            'username.required' => 'username is required!',
//            'password.required' => 'Password is required!',
//            'firstname.required' => 'Firstname is required!',
//            'lastname.required' => 'Lastname is required!',
//            'email.required' => 'Email is required!'
//        ];
    }
}
