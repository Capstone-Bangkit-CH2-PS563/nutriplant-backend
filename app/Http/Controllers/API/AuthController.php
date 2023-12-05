<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Mail\VerifyEmail;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\UserToRole;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Auth\Events\Registered;
use Illuminate\Foundation\Auth\EmailVerificationRequest;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $rules = [
            'email' => 'required|email',
            'password' => 'required',
        ];

        $customMessages = [
            'required' => 'Kolom :attribute tidak boleh kosong.',
            'email' => 'Format email tidak valid.',
        ];

        try {
            $validator = Validator::make($request->all(), $rules, $customMessages);

            if ($validator->fails()) {
                $errorMessages = [];
                foreach ($validator->errors()->messages() as $field => $messages) {
                    $errorMessages[] = "$field : " . implode(', ', $messages);
                }
                throw ValidationException::withMessages(['message' => $errorMessages]);
            }

            $credentials = $request->only('email', 'password');
            if (Auth::attempt($credentials)) {
                $user = Auth::user();
                $token = $user->createToken('')->plainTextToken;

                return response()->json(
                    [
                        'success' => true,
                        'message' => 'Berhasil masuk.',
                        'data' => [
                            'token' => $token,
                        ],
                    ],
                    Response::HTTP_OK,
                );
            }

            $errorMessages = ['Akun Tidak Terdaftar'];
            throw ValidationException::withMessages(['message' => $errorMessages]);
        } catch (ValidationException $e) {
            $errorResponse = [
                'success' => false,
                'message' => $e->errors(),
            ];
            return response()->json($errorResponse, Response::HTTP_BAD_REQUEST);
        }
    }

    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|string|email|unique:users',
            'password' => ['required', 'string', 'min:8'],
        ]);

        $user = User::create([
            'name' => $request->input('name'),
            'email' => $request->input('email'),
            'password' => bcrypt($request->input('password')),
            'email_verified_at' => now(),
        ]);

        $messageBerhasil = 'Registrasi berhasil.';

        return response()->json(
            [
                'status' => true,
                'message' => $messageBerhasil,
            ],
            Response::HTTP_CREATED
        );
    }

    public function logout(Request $request)
    {
        try {
            auth()
                ->user()
                ->tokens()
                ->delete();
            return response()->json([
                'success' => true,
                'message' => 'Berhasil keluar.',
            ]);
        } catch (\Exception $e) {
            return response()->json(
                [
                    'success' => false,
                    'message' => 'Gagal keluar. Silakan coba lagi.',
                ],
                Response::HTTP_BAD_REQUEST,
            );
        }
    }
}
