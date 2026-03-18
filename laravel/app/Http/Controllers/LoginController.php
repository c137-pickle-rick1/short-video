<?php

namespace App\Http\Controllers;

use App\Auth\LocalAccountService;
use App\Http\Requests\Auth\LoginRequest;
use App\ShortVideo\View\AuthViewDataFactory;
use App\ShortVideo\View\ShellViewDataFactory;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class LoginController extends Controller
{
    public function create(
        Request $request,
        ShellViewDataFactory $shellViewDataFactory,
        AuthViewDataFactory $authViewDataFactory
    ): View|RedirectResponse {
        if (Auth::guard(config('auth.defaults.guard'))->check()) {
            return redirect()->to($this->redirectUrl($request));
        }

        return view('shortvideo.login', [
            'shell' => $shellViewDataFactory->makeStandaloneShell('登录 | Lagos Explore Feed'),
            'auth' => $authViewDataFactory->makeModalData(
                initialPanel: 'login',
                open: true,
                standalone: true,
                closeUrl: url('/'),
                loginFormAction: route('login.store'),
                registerFormAction: route('register.store'),
                resetPasswordFormAction: route('password.reset.store'),
                sendCodeAction: route('auth.email-codes.store'),
                loginEmailValue: (string) old('email'),
                loginEmailError: $this->validationError('email'),
                passwordError: $this->validationError('password'),
                statusMessage: session('status'),
                errorMessage: $this->validationError('auth'),
            ),
        ]);
    }

    public function store(LoginRequest $request, LocalAccountService $localAccountService): RedirectResponse|JsonResponse
    {
        $payload = $request->validated();

        $user = $localAccountService->findLocalUserByEmail((string) $payload['email']);

        if (! $user || ! is_string($user->password) || $user->password === '' || ! Hash::check((string) $payload['password'], $user->password)) {
            if ($request->expectsJson()) {
                return response()->json([
                    'message' => '邮箱或密码错误。',
                    'errors' => [
                        'auth' => ['邮箱或密码错误。'],
                    ],
                ], 422);
            }

            return back()
                ->withErrors([
                    'auth' => '邮箱或密码错误。',
                ])
                ->withInput($request->only('email'));
        }

        Auth::guard(config('auth.defaults.guard'))->login($user);
        $request->session()->regenerate();

        $localAccountService->markLoggedIn($user);

        if ($request->expectsJson()) {
            return response()->json([
                'message' => '登录成功。',
            ]);
        }

        return redirect()->to($this->redirectUrl($request));
    }

    public function destroy(Request $request): RedirectResponse
    {
        $guard = Auth::guard(config('auth.defaults.guard'));
        if ($guard->check()) {
            $guard->logout();
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login')->with('status', '你已退出登录。');
    }

    private function redirectUrl(Request $request): string
    {
        return $request->session()->pull('url.intended', url('/'));
    }

    private function validationError(string $key): ?string
    {
        $errors = session('errors');
        if (! $errors) {
            return null;
        }

        $message = $errors->first($key);

        return is_string($message) && trim($message) !== '' ? $message : null;
    }
}
