<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\ShortVideo\View\LoginPageRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

final class LoginController extends Controller
{
    public function create(Request $request, LoginPageRenderer $renderer): View|RedirectResponse
    {
        if (Auth::guard(config('auth.defaults.guard'))->check()) {
            return redirect()->to($this->redirectUrl($request));
        }

        return view('shortvideo.login', [
            'documentHead' => $renderer->renderDocumentHead('登录 | Lagos Explore Feed'),
            'loginCard' => $renderer->renderLoginCard(
                formAction: route('login.store'),
                identifierValue: (string) old('login'),
                identifierError: $this->validationError('login'),
                passwordError: $this->validationError('password'),
                statusMessage: session('status'),
                errorMessage: $this->validationError('auth'),
            ),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $payload = $request->validate([
            'login' => ['required', 'string'],
            'password' => ['required', 'string'],
        ], [
            'login.required' => '请输入用户名、邮箱或手机号。',
            'password.required' => '请输入密码。',
        ]);

        $user = $this->findLoginUser((string) $payload['login']);

        if (! $user || ! is_string($user->password) || $user->password === '' || ! Hash::check((string) $payload['password'], $user->password)) {
            return back()
                ->withErrors([
                    'auth' => '账号或密码错误。',
                ])
                ->withInput($request->only('login'));
        }

        Auth::guard(config('auth.defaults.guard'))->login($user);
        $request->session()->regenerate();

        $user->forceFill([
            'last_login_at' => now(),
        ])->save();

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

    private function findLoginUser(string $identifier): ?User
    {
        $normalizedIdentifier = trim($identifier);
        if ($normalizedIdentifier === '') {
            return null;
        }

        $loweredIdentifier = mb_strtolower($normalizedIdentifier);

        return User::query()
            ->where('account_type', 'local')
            ->where(function ($query) use ($normalizedIdentifier, $loweredIdentifier): void {
                $query->whereRaw('LOWER(username) = ?', [$loweredIdentifier])
                    ->orWhereRaw('LOWER(email) = ?', [$loweredIdentifier])
                    ->orWhere('phone', $normalizedIdentifier);
            })
            ->first();
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
