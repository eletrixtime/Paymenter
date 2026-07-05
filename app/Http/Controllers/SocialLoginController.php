<?php

namespace App\Http\Controllers;

use App\Actions\Auth\Login;
use App\Models\User;
use App\Socialite\AuthentikProvider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\GithubProvider;
use Laravel\Socialite\Two\GoogleProvider;
use SocialiteProviders\Discord\Provider as DiscordProvider;

class SocialLoginController extends Controller
{
    protected $discord_driver;

    protected $github_driver;

    protected $google_driver;

    protected $authentik_driver;

    public function __construct()
    {
        $this->discord_driver = Socialite::buildProvider(DiscordProvider::class, [
            'client_id' => config('settings.oauth_discord_client_id'),
            'client_secret' => config('settings.oauth_discord_client_secret'),
            'redirect' => '/oauth/discord/callback',
        ]);

        $this->github_driver = Socialite::buildProvider(GithubProvider::class, [
            'client_id' => config('settings.oauth_github_client_id'),
            'client_secret' => config('settings.oauth_github_client_secret'),
            'redirect' => '/oauth/github/callback',
        ]);

        $this->google_driver = Socialite::buildProvider(GoogleProvider::class, [
            'client_id' => config('settings.oauth_google_client_id'),
            'client_secret' => config('settings.oauth_google_client_secret'),
            'redirect' => '/oauth/google/callback',
        ]);

        $this->authentik_driver = Socialite::buildProvider(AuthentikProvider::class, [
            'client_id' => config('settings.oauth_authentik_client_id'),
            'client_secret' => config('settings.oauth_authentik_client_secret'),
            'redirect' => url('/oauth/authentik/callback'),
        ]);
    }

    public function redirect($provider)
    {
        if (!config("settings.oauth_$provider")) {
            abort(404);
        }

        return match ($provider) {
            'discord'  => $this->discord_driver->scopes(['email'])->redirect(),
            'github'   => $this->github_driver->scopes(['user:email'])->redirect(),
            'google'   => $this->google_driver->scopes(['email'])->redirect(),
            'authentik' => $this->authentik_driver->scopes(['openid', 'profile', 'email'])->redirect(),
            default    => abort(404),
        };
    }

    public function handle($provider)
    {
        if ($provider === 'discord') {
            $oauth_user = $this->discord_driver->user();

            if ($oauth_user->user['verified'] == false) {
                return redirect()->route('login')->with('error', __('auth.oauth.unverified_discord_account'));
            }

            return $this->findUserAndLogin($oauth_user->email);

        } elseif ($provider === 'google') {
            $oauth_user = $this->google_driver->user();

            if (!$oauth_user->user['email_verified']) {
                return redirect()->route('login')->with('error', __('auth.oauth.unverified_google_account'));
            }

            return $this->findUserAndLogin($oauth_user->email);

        } elseif ($provider === 'github') {
            $oauth_user = $this->github_driver->user();

            return $this->findUserAndLogin($this->github_driver->user()->email);

        } elseif ($provider === 'authentik') {
            return $this->handleAuthentik();

        } else {
            return redirect()->route('login');
        }
    }

    /**
     * Handle the Authentik OIDC callback.
     * Creates the user account automatically if it does not exist yet.
     */
    private function handleAuthentik(): RedirectResponse
    {
        $oauth_user = $this->authentik_driver->user();
        $raw = $oauth_user->getRaw();

        // Require a verified email from Authentik
        if (empty($oauth_user->email)) {
            return redirect()->route('login')->with('error', 'No email address was returned by Authentik. Please check your application scopes.');
        }

        $user = User::where('email', $oauth_user->email)->first();

        if (!$user) {
            // Parse first/last name from OIDC claims
            $firstName = $raw['given_name'] ?? null;
            $lastName  = $raw['family_name'] ?? null;

            // Fall back to splitting the full name if given/family_name are absent
            if (!$firstName && !$lastName) {
                $nameParts = explode(' ', $oauth_user->name ?? $oauth_user->email, 2);
                $firstName = $nameParts[0];
                $lastName  = $nameParts[1] ?? '';
            }

            $user = User::create([
                'first_name'        => $firstName,
                'last_name'         => $lastName,
                'email'             => $oauth_user->email,
                'password'          => Hash::make(Str::random(32)),
                'email_verified_at' => now(), // Authentik already verified the email
            ]);
        }

        // Handle 2FA if enabled on this account
        if ($user->tfa_secret) {
            Session::put('2fa', [
                'user_id' => $user->id,
                'remember' => true,
                'expires'  => now()->addMinutes(5),
            ]);

            return redirect()->route('2fa');
        }

        (new Login)->execute($user, true);

        return redirect()->route('dashboard');
    }

    private function findUserAndLogin(string $email): RedirectResponse
    {
        $user = User::where('email', $email)->first();

        if (!$user) {
            return redirect()->route('register')->with('error', __('auth.oauth.account_not_registered'));
        }

        if ($user->tfa_secret) {
            Session::put('2fa', [
                'user_id' => $user->id,
                'remember' => true,
                'expires'  => now()->addMinutes(5),
            ]);

            return redirect()->route('2fa');
        }

        (new Login)->execute($user, true);

        return redirect()->route('home');
    }
}
