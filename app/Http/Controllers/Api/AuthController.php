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
    public $users = 'laravel_system.users';

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

        if (!Auth::guard('web')->attempt($credentials)) {
            return response()->json([
                'message' => 'You cannot sign with those credentials',
                'errors' => 'Unauthorised'
            ], 401);
        }

        return response()->json($this->createToken($request->remember_me ? Carbon::now()->addMonth() : Carbon::now()->addDay()), 200);
    }

    private function createToken($expires_at = null) {
        $token = Auth::guard('web')->user()->createToken(config('app.name'));
        //$token = $request->user()->createToken(config('app.name'));

        $token->token->expires_at = $expires_at ?: Carbon::now()->addMonth();
        $token->token->save();
        return [
            'token_type' => 'Bearer',
            'token' => $token->accessToken,
            'expires_at' => Carbon::parse($token->token->expires_at)->toDateTimeString()
        ];
    }

    function Logout(Request $request) {
        $request->user()->token()->revoke();

        return response()->json([
            'message' => 'You are successfully logged out',
        ]);
    }

    function SendSmsCode (Request $request) {
        //$code = (string) rand(100000, 999999);
        $code = '123';

        $u = query("SELECT id FROM $this->users WHERE email=? AND auth_type='phone'", [$request->phone]);
        if (empty($u)) {
            $id = query("INSERT INTO $this->users (name,password,auth_type,email) VALUES ('','','phone',?)", [$request->phone]);
        }
        else {
            $id = $u[0]->id;
        }
        setExtra($id, ['smsCode' => $code, 'smsCodeTime' => date('Y-m-d H:i:s')], $this->users);

        // отправка смс

        return 'смс отправлен на номер ' . $request->phone;
    }

    function VerifySmsCode (Request $request) {
        $ud = query("SELECT id, extra FROM $this->users WHERE email=? AND auth_type='phone'", [$request->phone]);
        if (!empty($ud)) {
            $extra = json_decode($ud[0]->extra);
            if ($extra->smsCode === $request->smsCode) {
                $user = User::find($ud[0]->id);
                Auth::guard('web')->login($user);
                //return Auth::guard('web')->user();
                return $this->createToken();
            }
        }
        return 'Код не верен';
    }

    function SetPassword (Request $request) {
        if ($request->user()) {
            $count = query("UPDATE $this->users SET password=? WHERE id=?", [
                bcrypt($request->new_password),
                $request->user()->id,
            ]);
            return $count;
        }
    }

    function test() {
        if (Auth::check()) {
            // user authenticate
        }

        /*
        $p = \App\Models\Person::create([
            'user_id' => 223,
            'name' => 'new person',
        ]);
        */
        return Auth::guard('api')->user();

        //$res = query("SELECT * FROM persons WHERE id<:id", ['id'=>4]);
        //return $res;

        return ['aaa' => 111] + $_GET + $_POST;
    }
}
