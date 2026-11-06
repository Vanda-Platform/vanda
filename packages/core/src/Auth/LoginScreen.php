<?php

declare(strict_types=1);

namespace Vanda\Auth;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Vanda\Actions\Button;
use Vanda\Attributes\Action;
use Vanda\Fields\CheckBox;
use Vanda\Fields\Input;
use Vanda\Fields\InputType;
use Vanda\Fields\Password;
use Vanda\Screen\Chrome;
use Vanda\Screen\Result;
use Vanda\Screen\Screen;
use Vanda\Panel;

/**
 * The sign-in page, itself an ordinary Vanda screen. Registered by Route::vanda().
 */
final class LoginScreen extends Screen
{
    public LoginForm $form;

    public function query(): array
    {
        $this->form = new LoginForm;

        return [];
    }

    public function chrome(): Chrome
    {
        return Chrome::Card;
    }

    public function title(): string
    {
        return __('vanda::auth.title');
    }

    public function description(): string
    {
        return __('vanda::auth.description');
    }

    public function layout(): iterable
    {
        return [
            Input::make('form.email')->inputType(InputType::Email)->label(__('vanda::auth.email'))->placeholder('you@company.com'),
            Password::make('form.password')->label(__('vanda::auth.password')),
            CheckBox::make('form.remember')->label(__('vanda::auth.remember')),
            Button::make(__('vanda::auth.submit'))->primary()->block()->action($this->login(...)),
        ];
    }

    #[Action]
    public function login(Request $request): Result
    {
        $key = 'vanda-login:'.Str::lower($this->form->email).'|'.$request->ip();

        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw ValidationException::withMessages([
                'form.email' => __('vanda::auth.throttle', ['seconds' => RateLimiter::availableIn($key)]),
            ]);
        }

        $credentials = ['email' => $this->form->email, 'password' => $this->form->password];

        if (! Auth::guard(config('vanda.guard'))->attempt($credentials, $this->form->remember)) {
            RateLimiter::hit($key);

            throw ValidationException::withMessages(['form.email' => __('vanda::auth.failed')]);
        }

        RateLimiter::clear($key);

        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // The only moment the password is known: a weak one turns on the red strip (production).
        WeakPassword::remember($request, Auth::guard(config('vanda.guard'))->user(), $this->form->password);

        // Only the path of the page asked for: the stored URL may carry another host (an old port in
        // development, a proxy's name), and signing in must never lead off this panel.
        $target = parse_url(redirect()->intended(Panel::home())->getTargetUrl());
        $path = '/'.ltrim($target['path'] ?? '', '/').(isset($target['query']) ? '?'.$target['query'] : '');

        return Result::redirectTo($path);
    }
}
