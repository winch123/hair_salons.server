<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
//use App\Http\Requests\Api\Auth\RegisterFormRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Models\User;
use Validator;
use Carbon\Carbon;

class AuthController extends Controller
{
    function Register(Request $request) {
      dump($request->all());
      
      $validation = Validator::make($request->all(), [
	    'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:6', 
		'confirmed'
	    ],
      ], ['password' => 'эти пароли не совпадают.']);
      
      if ($validation->fails()) {
	  return $validation->errors()->toJson();
      }
      
      
      $user = User::create(array_merge(
            $request->only('name', 'email'),
            ['password' => bcrypt($request->password)],
      ));

      return response()->json([
            'message' => 'You were successfully registered. Use your email and password to sign in.'
      ], 200);
    }

    function Login(Request $request) {
        $credentials = $request->only('email', 'password');

        if (!Auth::attempt($credentials)) {
            return response()->json([
                'message' => 'You cannot sign with those credentials',
                'errors' => 'Unauthorised'
            ], 401);
        }

        $token = Auth::user()->createToken(config('app.name'));
        $token->token->expires_at = $request->remember_me ? Carbon::now()->addMonth() : Carbon::now()->addDay();

        $token->token->save();

        return response()->json([
            'token_type' => 'Bearer',
            'token' => $token->accessToken,
            'expires_at' => Carbon::parse($token->token->expires_at)->toDateTimeString()
        ], 200);
    }

    function Logout(Request $request) {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'You are successfully logged out',
        ]);
    }

    function test() {
        return $_GET + $_POST;
    }

}
