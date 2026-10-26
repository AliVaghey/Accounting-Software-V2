<x-layouts.auth title="Create an account" description="Make room for a clearer view of your money. Start with your personal account.">
    <form method="POST" action="{{ route('register.store') }}" class="flex flex-col gap-5">
        @csrf

        <x-auth-input name="name" label="Name" autocomplete="name" maxlength="255" required autofocus />
        <x-auth-input name="email" label="Email address" type="email" autocomplete="username" inputmode="email" maxlength="255" required />
        <x-auth-input name="password" label="Password" type="password" autocomplete="new-password" hint="Use a long, unique password." maxlength="72" required />
        <x-auth-input name="password_confirmation" label="Confirm password" type="password" autocomplete="new-password" maxlength="72" required />

        <button type="submit" class="auth-button">
            Create account <i class="bi bi-arrow-right" aria-hidden="true"></i>
        </button>
    </form>

    <p class="text-center text-sm text-galaxy-muted">
        Already have an account? <a href="{{ route('login') }}" class="auth-link">Log in</a>
    </p>
</x-layouts.auth>
