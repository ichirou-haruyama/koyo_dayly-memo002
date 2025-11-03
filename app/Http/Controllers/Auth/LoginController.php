<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Contracts\View\View as ViewContract;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class LoginController
{
    public function create(): View|ViewContract|RedirectResponse
    {
        if (Auth::check()) {
            return redirect()->intended('/reports');
        }

        return view('livewire.auth.login');
    }

    public function store(LoginRequest $request): RedirectResponse
    {
        $credentials = [
            'email' => $request->string('email')->toString(),
            'password' => $request->string('password')->toString(),
        ];

        $user = User::query()->where('email', $credentials['email'])->first();

        if ($user === null || $user->is_active !== true) {
            return back()
                ->withErrors(['email' => __('このアカウントは無効化されています。')])
                ->withInput($request->except('password'));
        }

        if (! Hash::check($credentials['password'], $user->password)) {
            return back()
                ->withErrors(['email' => __('メールアドレスまたはパスワードが正しくありません。')])
                ->withInput($request->except('password'));
        }

        $remember = $request->boolean('remember');

        if (! Auth::attempt(['email' => $credentials['email'], 'password' => $credentials['password']], $remember)) {
            return back()
                ->withErrors(['email' => __('ログインに失敗しました。')])
                ->withInput($request->except('password'));
        }

        $request->session()->regenerate();

        return redirect()->intended('/reports');
    }
}
