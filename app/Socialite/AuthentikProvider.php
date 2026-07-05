<?php

namespace App\Socialite;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User;

class AuthentikProvider extends AbstractProvider
{
    /**
     * The scopes to request from Authentik.
     */
    protected $scopes = ['openid', 'profile', 'email'];

    /**
     * The separator between scopes.
     */
    protected $scopeSeparator = ' ';

    /**
     * Create a new provider instance.
     */
    public function __construct(Request $request, string $clientId, string $clientSecret, string $redirectUrl, array $guzzle = [])
    {
        parent::__construct($request, $clientId, $clientSecret, $redirectUrl, $guzzle);
    }

    /**
     * Get the base Authentik URL from settings.
     */
    protected function getBaseUrl(): string
    {
        return rtrim(config('settings.oauth_authentik_url', ''), '/');
    }

    /**
     * Get the authorization URL.
     */
    protected function getAuthUrl($state): string
    {
        return $this->buildAuthUrlFromBase(
            $this->getBaseUrl() . '/application/o/authorize/',
            $state
        );
    }

    /**
     * Get the token URL.
     */
    protected function getTokenUrl(): string
    {
        return $this->getBaseUrl() . '/application/o/token/';
    }

    /**
     * Get the raw user data from the userinfo endpoint using the Bearer token.
     */
    protected function getUserByToken($token): array
    {
        $response = $this->getHttpClient()->get(
            $this->getBaseUrl() . '/application/o/userinfo/',
            [
                'headers' => [
                    'Authorization' => 'Bearer ' . $token,
                    'Accept'        => 'application/json',
                ],
            ]
        );

        return json_decode((string) $response->getBody(), true);
    }

    /**
     * Map the raw Authentik OIDC user claims to a Socialite User object.
     *
     * Authentik OIDC standard claims:
     *  - sub              → unique user ID
     *  - email            → email address
     *  - email_verified   → bool
     *  - name             → full display name
     *  - given_name       → first name
     *  - family_name      → last name
     *  - preferred_username → username / nickname
     *  - picture          → avatar URL
     */
    protected function mapUserToObject(array $user): User
    {
        return (new User)->setRaw($user)->map([
            'id'       => Arr::get($user, 'sub'),
            'name'     => Arr::get($user, 'name') ?? Arr::get($user, 'preferred_username'),
            'email'    => Arr::get($user, 'email'),
            'nickname' => Arr::get($user, 'preferred_username'),
            'avatar'   => Arr::get($user, 'picture'),
        ]);
    }

    /**
     * Determine if the email returned by Authentik is verified.
     */
    public function isEmailVerified(array $rawUser): bool
    {
        return (bool) Arr::get($rawUser, 'email_verified', false);
    }
}
