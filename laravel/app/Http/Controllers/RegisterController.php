<?php

namespace App\Http\Controllers;

use App\Auth\AuthEmailCodePurpose;
use App\Auth\AuthEmailCodeService;
use App\Auth\LocalAccountService;
use App\Http\Requests\Auth\RegisterRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class RegisterController extends Controller
{
    public function store(
        RegisterRequest $request,
        AuthEmailCodeService $authEmailCodeService,
        LocalAccountService $localAccountService
    ): JsonResponse {
        $payload = $request->validated();
        $email = (string) $payload['email'];

        if ($localAccountService->emailExists($email)) {
            throw ValidationException::withMessages([
                'email' => ['该邮箱已被使用，请直接登录。'],
            ]);
        }

        $authEmailCodeService->consume($email, AuthEmailCodePurpose::Register, (string) $payload['code']);

        $user = $localAccountService->createLocalUser($email, (string) $payload['password']);

        Auth::guard(config('auth.defaults.guard'))->login($user);
        $request->session()->regenerate();
        $localAccountService->markLoggedIn($user);

        return response()->json([
            'message' => '注册成功，已自动登录。',
        ], 201);
    }
}
