<x-layouts.auth title="Log in" description="A little clarity for your everyday finances. Log in to your personal account.">
    <form method="POST" action="{{ route('login.store') }}" class="flex flex-col gap-5">
        @csrf

        <x-auth-input name="email" label="Email address" type="email" autocomplete="username" inputmode="email" maxlength="255" required autofocus />
        <x-auth-input name="password" label="Password" type="password" autocomplete="current-password" required />

        <label class="flex min-h-11 w-fit cursor-pointer items-center gap-3 text-sm text-galaxy-muted">
            <input type="checkbox" name="remember" value="1" @checked(old('remember')) class="size-5 accent-galaxy-accent focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-galaxy-accent">
            Keep me logged in
        </label>

        <button type="submit" class="auth-button">
            Log in <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
    </form>

    <p class="text-center text-sm text-galaxy-muted">
        New here? <a href="{{ route('register') }}" class="auth-link">Create an account</a>
    </p>
</x-layouts.auth>
