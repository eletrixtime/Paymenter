<form
    class="mx-auto flex flex-col gap-2 mt-4 px-6 sm:px-14 pb-10 bg-primary-800 rounded-md xl:max-w-[40%] w-full"
    wire:submit="submit" id="login">
    <div class="flex flex-col items-center my-14">
        <x-logo class="h-10" />
        <h1 class="text-2xl text-center mt-6">{{ __('auth.sign_in_title') }} </h1>
    </div>
    <x-form.input name="email" type="email" :label="__('general.input.email')"
        :placeholder="__('general.input.email_placeholder')" wire:model="email" hideRequiredIndicator required autocomplete="email" />
    <x-form.input name="password" type="password" :label="__('general.input.password')"
        :placeholder="__('general.input.password_placeholder')" required hideRequiredIndicator wire:model="password" autocomplete="current-password" />
    <div class="flex flex-row">
        <x-form.checkbox name="remember" label="Remember me" wire:model="remember" />
    </div>

    <x-captcha :form="'login'" />

    <x-button.primary class="w-full" type="submit">{{ __('auth.sign_in') }}</x-button.primary>

    {!! hook('auth.login') !!}

    @if (config('settings.oauth_github') || config('settings.oauth_google') || config('settings.oauth_discord') || config('settings.oauth_authentik'))
    <div class="flex flex-col items-center mt-4">
        <div class="my-5 flex items-center w-full">
            <span aria-hidden="true" class="h-0.5 grow rounded bg-primary-700"></span>
            <span class="rounded-full px-3 py-1 text-xs font-medium bg-primary-700 text-gray-200">
                {{ __('auth.or_sign_in_with') }}
            </span>
            <span aria-hidden="true" class="h-0.5 grow rounded bg-primary-700"></span>
        </div>
        <div class="flex flex-row flex-wrap justify-center mt-2 gap-4">
            @foreach (['github', 'google', 'discord'] as $provider)
            @if (config('settings.oauth_' . $provider))
            <a href="{{ route('oauth.redirect', $provider) }}"
                class="flex items-center justify-center px-4 h-10 border border-neutral rounded-md text-primary-100">
                <img src="/assets/images/{{ $provider }}-dark.svg" alt="{{ $provider }}"
                    class="size-5 mr-2 text-secondary">
                {{ __(ucfirst($provider)) }}
            </a>
            @endif
            @endforeach

            @if (config('settings.oauth_authentik'))
            <a href="{{ route('oauth.redirect', 'authentik') }}"
                id="btn-sso-authentik"
                class="flex items-center justify-center px-4 h-10 border border-neutral rounded-md text-primary-100 hover:bg-primary-700 transition-colors">
                {{-- Authentik logo inline SVG --}}
                <svg class="size-5 mr-2" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                    <path d="M12 2C6.477 2 2 6.477 2 12s4.477 10 10 10 10-4.477 10-10S17.523 2 12 2z" fill="#FD4B2D"/>
                    <path d="M16.5 8.5l-4.5 3-4.5-3v7l4.5 3 4.5-3v-7z" fill="#fff"/>
                </svg>
                Authentik
            </a>
            @endif
        </div>
    </div>
    @endif

</form>
