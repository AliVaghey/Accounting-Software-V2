<?php

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;

test('renders authentication pages for guests', function (string $path, string $view) {
    $this->get($path)->assertOk()->assertViewIs($view);
})->with([
    'login' => ['/login', 'auth.login'],
    'register' => ['/register', 'auth.register'],
]);

test('redirects guests away from the home page and logout action', function (string $method, string $path) {
    $this->{$method}($path)->assertRedirectToRoute('login');
})->with([
    'home' => ['get', '/home'],
    'logout' => ['post', '/logout'],
]);

test('redirects authenticated visitors from root and authentication pages to home', function (string $method, string $path) {
    $user = User::factory()->create();

    $this->actingAs($user)->{$method}($path)->assertRedirectToRoute('home');
    $this->assertDatabaseCount('users', 1);
})->with([
    'root' => ['get', '/'],
    'login page' => ['get', '/login'],
    'register page' => ['get', '/register'],
    'login action' => ['post', '/login'],
    'register action' => ['post', '/register'],
]);

test('renders an empty home page for authenticated users', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get('/home');

    $response->assertOk()->assertViewIs('home');
    expect($response->getContent())->toMatch('/<main\b[^>]*>\s*<\/main>/s');
});

test('registers a user with a hashed password and starts a new authenticated session', function () {
    Event::fake([Registered::class]);
    $this->withSession(['_token' => 'previous-token']);
    $previousSessionId = session()->getId();

    $response = $this->post('/register', [
        'name' => 'Mina Rahimi',
        'email' => '  MINA@example.com ',
        'password' => 'a-long-unique-password',
        'password_confirmation' => 'a-long-unique-password',
        'email_verified_at' => now(),
        'id' => 999,
    ]);

    $response->assertRedirectToRoute('home')->assertSessionHasNoErrors();
    $user = User::where('email', 'mina@example.com')->sole();
    $this->assertAuthenticatedAs($user);
    $this->assertDatabaseCount('users', 1);
    expect($user->name)->toBe('Mina Rahimi');
    expect(Hash::check('a-long-unique-password', $user->password))->toBeTrue();
    expect($user->email_verified_at)->toBeNull();
    expect($user->id)->not->toBe(999);
    expect(session()->getId())->not->toBe($previousSessionId);
    Event::assertDispatched(Registered::class, fn (Registered $event): bool => $event->user->is($user));
});

test('requires registration fields without creating a user', function () {
    $response = $this->from('/register')->post('/register');

    $response->assertRedirect('/register')->assertSessionHasErrors([
        'name' => 'The name field is required.',
        'email' => 'The email field is required.',
        'password' => 'The password field is required.',
    ]);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('rejects invalid registration values without creating a user', function (array $changes, string $field, string $message) {
    $response = $this->from('/register')->post('/register', array_replace([
        'name' => 'Mina Rahimi',
        'email' => 'mina@example.com',
        'password' => 'a-long-unique-password',
        'password_confirmation' => 'a-long-unique-password',
    ], $changes));

    $response->assertRedirect('/register')->assertSessionHasErrors([$field => $message]);
    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
})->with([
    'invalid email' => [['email' => 'invalid-email'], 'email', 'The email field must be a valid email address.'],
    'long name' => [['name' => str_repeat('a', 256)], 'name', 'The name field must not be greater than 255 characters.'],
    'long email' => [['email' => str_repeat('a', 244).'@example.com'], 'email', 'The email field must not be greater than 255 characters.'],
    'short password' => [['password' => 'short', 'password_confirmation' => 'short'], 'password', 'The password field must be at least 8 characters.'],
    'long password' => [['password' => str_repeat('a', 73), 'password_confirmation' => str_repeat('a', 73)], 'password', 'The password field must not be greater than 72 characters.'],
    'mismatched confirmation' => [['password_confirmation' => 'another-password'], 'password', 'The password field confirmation does not match.'],
    'missing confirmation' => [['password_confirmation' => null], 'password', 'The password field confirmation does not match.'],
]);

test('rejects duplicate email addresses after normalization', function () {
    $user = User::factory()->create(['email' => 'mina@example.com']);

    $response = $this->post('/register', [
        'name' => 'Another account',
        'email' => 'MINA@example.com',
        'password' => 'a-long-unique-password',
        'password_confirmation' => 'a-long-unique-password',
    ]);

    $response->assertSessionHasErrors(['email' => 'The email has already been taken.']);
    $this->assertDatabaseCount('users', 1);
    $this->assertModelExists($user);
    $this->assertGuest();
});

test('limits registration attempts by IP address', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/register')->assertSessionHasErrors();
    }

    $this->post('/register', [
        'name' => 'Mina Rahimi',
        'email' => 'mina@example.com',
        'password' => 'a-long-unique-password',
        'password_confirmation' => 'a-long-unique-password',
    ])->assertTooManyRequests();

    $this->assertGuest();
    $this->assertDatabaseCount('users', 0);
});

test('logs in with normalized credentials and regenerates the session', function () {
    $user = User::factory()->create(['email' => 'mina@example.com']);
    $this->withSession(['_token' => 'previous-token', 'url.intended' => '/register']);
    $previousSessionId = session()->getId();

    $response = $this->post('/login', ['email' => ' MINA@example.com ', 'password' => 'password']);

    $response->assertRedirectToRoute('home')->assertSessionHasNoErrors();
    $this->assertAuthenticatedAs($user);
    expect(session()->getId())->not->toBe($previousSessionId);
});

test('sets a remember cookie only when requested', function (bool $remember) {
    $user = User::factory()->create();

    $response = $this->post('/login', [
        'email' => $user->email,
        'password' => 'password',
        'remember' => $remember,
    ]);

    $response->assertRedirectToRoute('home');
    $this->assertAuthenticatedAs($user);
    $cookieName = Auth::guard('web')->getRecallerName();
    if ($remember) {
        $response->assertCookie($cookieName);
    } else {
        $response->assertCookieMissing($cookieName);
    }
})->with([true, false]);

test('requires login credentials', function () {
    $response = $this->from('/login')->post('/login');

    $response->assertRedirect('/login')->assertSessionHasErrors([
        'email' => 'The email field is required.',
        'password' => 'The password field is required.',
    ]);
    $this->assertGuest();
});

test('rejects incorrect credentials with the same generic message', function (string $email, string $password) {
    User::factory()->create(['email' => 'mina@example.com']);

    $response = $this->from('/login')->post('/login', ['email' => $email, 'password' => $password]);

    $response->assertRedirect('/login')->assertSessionHasErrors([
        'email' => 'These credentials do not match our records.',
    ])->assertSessionMissing('_old_input.password');
    $this->assertGuest();
})->with([
    'wrong password' => ['mina@example.com', 'incorrect-password'],
    'unknown email' => ['unknown@example.com', 'password'],
]);

test('blocks valid credentials after five failed attempts and allows login after cooldown', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/login', ['email' => $user->email, 'password' => 'incorrect'])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
    }

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Too many login attempts. Please try again in 60 seconds.']);
    $this->assertGuest();

    $this->travel(61)->seconds();

    $this->post('/login', ['email' => $user->email, 'password' => 'password'])->assertRedirectToRoute('home');
    $this->assertAuthenticatedAs($user);
});

test('logs out and invalidates the session and csrf token', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->withSession(['_token' => 'previous-token', 'private-data' => 'secret']);
    $previousSessionId = session()->getId();

    $response = $this->post('/logout');

    $response->assertRedirectToRoute('login')->assertSessionMissing('private-data');
    $this->assertGuest();
    expect(session()->getId())->not->toBe($previousSessionId);
    expect(session()->token())->not->toBe('previous-token');
});

test('escapes old form input and never repopulates passwords', function () {
    $maliciousName = '<script>alert("unsafe")</script>';

    $response = $this->withSession(['_old_input' => [
        'name' => $maliciousName,
        'password' => 'secret-password',
        'password_confirmation' => 'secret-password',
    ]])->get('/register');

    $response->assertOk()->assertSee($maliciousName)->assertDontSee($maliciousName, false)
        ->assertDontSee('secret-password');
});
