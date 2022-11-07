<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\User;
use Carbon\Carbon;
use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\Facades\Socialite;

/**
 * Class LoginController
 */
class LoginController extends Controller
{
    use AuthenticatesUsers;

    /**
     * @var string
     */
    protected $redirectTo = '/';

    protected $maxAttempts = 5;

    protected $decayMinutes = 10;

    /**
     * LoginController constructor.
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    public function captcha()
    {
        return captcha_img();
    }

    public function authenticated(Request $request, $user)
    {
        $user->update([
            'last_login_at' => Carbon::now()->toDateTimeString(),
            'last_login_ip' => $request->ip(),
        ]);

        system_log(7, 'LOGIN_SUCCESS');

        hook('login_successful', [
            'user' => $user,
        ]);

        if (env('WIZARD_STEP', 1) != config('liman.wizard_max_steps') && $user->status) {
            return redirect()->route('wizard', env('WIZARD_STEP', 1));
        }
    }

    public function attemptLogin(Request $request)
    {
        $credientials = (object) $this->credentials($request);

        $flag = $this->guard()->attempt(
            $this->credentials($request),
            (bool) $request->remember
        );

        Event::listen('login_attempt_success', function ($data) use (&$flag) {
            $this->guard()->login($data, (bool) request()->remember);
            $flag = true;
        });

        if (! $flag) {
            event('login_attempt', $credientials);
        }

        return $flag;
    }

    protected function validateLogin(Request $request)
    {
        $request->request->add([
            $this->username() => $request->liman_email_aciklab,
            'password' => $request->liman_password_divergent,
        ]);
        if (env('EXTENSION_DEVELOPER_MODE')) {
            $request->validate([
                $this->username() => 'required|string',
                'password' => 'required|string',
            ]);
        } else {
            $request->validate([
                $this->username() => 'required|string',
                'password' => 'required|string',
                'captcha' => 'required|captcha',
            ]);
        }
    }

    protected function sendFailedLoginResponse(Request $request): never
    {
        $credientials = (object) $this->credentials($request);
        hook('login_failed', [
            'email' => $credientials->email,
            'password' => $credientials->password,
        ]);

        throw ValidationException::withMessages([
            $this->username() => [trans('auth.failed')],
        ]);
    }

    public function redirectToKeycloak()
    {
        if (env('KEYCLOAK_ACTIVE', false) == false) {
            return;
        }

        return Socialite::driver('keycloak')->stateless()->redirect();
    }

    public function retrieveFromKeycloak(Request $request)
    {
        if (env('KEYCLOAK_ACTIVE', false) == false) {
            return;
        }

        $remote = Socialite::driver('keycloak')->stateless()->user();

        $user = User::find($remote->id);

        if (! $user) {
            $emailExists = User::where('email', $remote->email)->get();
            if (count($emailExists) < 1) {
                $user = User::create([
                    'id' => $remote->id,
                    'username' => $remote->nickname,
                    'email' => $remote->email,
                    'auth_type' => 'keycloak',
                    'status' => 0,
                    'forceChange' => false,
                    'name' => $remote->name,
                    'password' => Hash::make(Str::random(16))
                ]);
            } else {
                return redirect('/giris')->withErrors(__('Keycloak kullanıcısının e-posta adresi sistemde mevcut.'));
            }
        }

        $user->update([
            'last_login_at' => Carbon::now()->toDateTimeString(),
            'last_login_ip' => $request->ip(),
        ]);

        system_log(7, 'LOGIN_SUCCESS');

        hook('login_successful', [
            'user' => $user,
        ]);

        Auth::loginUsingId($user->id, true);

        return redirect('/');
    }
}
