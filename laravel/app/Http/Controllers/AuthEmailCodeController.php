<?php

namespace App\Http\Controllers;

use App\Auth\AuthEmailCodePurpose;
use App\Auth\AuthEmailCodeService;
use App\Auth\LocalAccountService;
use App\Http\Requests\Auth\SendAuthEmailCodeRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;

final class AuthEmailCodeController extends Controller
{
    public function store(
        SendAuthEmailCodeRequest $request,
        AuthEmailCodeService $authEmailCodeService,
        LocalAccountService $localAccountService
    ): JsonResponse {
        $purpose = AuthEmailCodePurpose::from((string) $request->validated('purpose'));
        $email = (string) $request->validated('email');

        if ($purpose === AuthEmailCodePurpose::Register && $localAccountService->emailExists($email)) {
            throw ValidationException::withMessages([
                'email' => ['该邮箱已被使用，请直接登录。'],
            ]);
        }

        if ($purpose === AuthEmailCodePurpose::PasswordReset && $localAccountService->findLocalUserByEmail($email) === null) {
            throw ValidationException::withMessages([
                'email' => ['未找到可重置密码的本地账号。'],
            ]);
        }

        $authEmailCodeService->send($email, $purpose);

        return response()->json([
            'message' => '验证码已发送，请查看邮箱。',
            'cooldownSeconds' => AuthEmailCodeService::RESEND_COOLDOWN_SECONDS,
            'expiresInSeconds' => AuthEmailCodeService::EXPIRATION_MINUTES * 60,
        ]);
    }
}
