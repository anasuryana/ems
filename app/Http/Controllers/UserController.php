<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;


class UserController extends Controller
{
    public function __construct()
    {
        date_default_timezone_set('Asia/Jakarta');
    }

    public function login(Request $request)
    {
        $dataReq = $request->json()->all();
        $data = [
            'nick_name' => $dataReq['username'],
            'password' => $dataReq['password'],
            'active' => '1',
        ];

        if (Auth::attempt($data)) {
            $user = User::where('nick_name', $dataReq['username'])->first();
            $user->token = $user->createToken($dataReq['password'] . 'bebas')->plainTextToken;
            return $user;
        } else {
            throw new HttpResponseException(response([
                'errors' => [
                    'message' => [
                        'username or password wrong'
                    ], 'data' => $data
                ]
            ], 401));
        }
    }

    public function logout(Request $request)
    {
        $data = $request->user('sanctum')->currentAccessToken()->delete();
        return ['message' => 'Log out successfully', $data];
    }
}
