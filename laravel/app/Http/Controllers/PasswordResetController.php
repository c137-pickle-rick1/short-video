<?php

namespace App\Http\Controllers;

use App\Auth\AuthEmailCodePurpose;
use App\Auth\AuthEmailCodeService;
use App\Auth\LocalAccountService;
use App\Http\Requests\Auth\ResetPasswordWithCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class PasswordResetController extends Controller
{
    public function store(
        ResetPasswordWithCodeRequest $request,
        AuthEmailCodeService $authEmailCodeService,
        LocalAccountService $localAccountService
    ): JsonResponse {
        $payload = $request->validated();
        $email = (string) $payload['email'];
        $user = $localAccountService->findLocalUserByEmail($email);

        if ($user === null) {
            throw ValidationException::withMessages([
                'email' => ['未找到可重置密码的本地账号。'],
            ]);
        }

        $authEmailCodeService->consume($email, AuthEmailCodePurpose::PasswordReset, (string) $payload['code']);
        $localAccountService->resetPassword($user, (string) $payload['password']);

        return response()->json([
            'message' => '密码已重置，请使用新密码登录。',
        ]);
    }
}
