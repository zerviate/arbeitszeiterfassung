<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;

class AuthenticatedSessionController extends Controller
{
    private const LOGIN_MAX_ATTEMPTS = 5;

    private const LOGIN_DECAY_SECONDS = 60;

    public function create(): View
    {
        return view('auth.login');
    }

    public function store(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        if ($this->hasTooManyLoginAttempts($request)) {
            return $this->sendLockoutResponse($request);
        }

        if (! Auth::attempt($credentials, $request->boolean('remember'))) {
            RateLimiter::hit($this->throttleKey($request), self::LOGIN_DECAY_SECONDS);

            return back()
                ->withErrors(['email' => 'Die Anmeldedaten sind ungueltig.'])
                ->onlyInput('email');
        }

        RateLimiter::clear($this->throttleKey($request));

        $request->session()->regenerate();

        return redirect()->intended(route('time.index'));
    }

    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }

    private function hasTooManyLoginAttempts(Request $request): bool
    {
        return RateLimiter::tooManyAttempts($this->throttleKey($request), self::LOGIN_MAX_ATTEMPTS);
    }

    private function sendLockoutResponse(Request $request): RedirectResponse
    {
        $seconds = max(1, RateLimiter::availableIn($this->throttleKey($request)));

        return back()
            ->withErrors([
                'email' => "Zu viele fehlgeschlagene Anmeldungen. Bitte in {$seconds} Sekunden erneut versuchen.",
            ])
            ->onlyInput('email')
            ->setStatusCode(429)
            ->header('Retry-After', (string) $seconds);
    }

    private function throttleKey(Request $request): string
    {
        $email = Str::lower(trim($request->string('email')->toString()));

        return $email.'|'.$request->ip();
    }
}
