<?php

use App\Models\User;
use Livewire\Livewire;

test('redirects guests away from navigation destinations', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');
})->with(['/tags', '/transactions', '/statistics']);

test('renders the navigation on every authenticated destination', function (string $routeName, string $view) {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route($routeName));

    $response->assertOk()
        ->assertViewIs($view)
        ->assertSeeLivewire('add-transaction-modal')
        ->assertSeeInOrder([
            'aria-label="Main navigation"',
            'href="'.route('home').'"',
            'href="'.route('tags').'"',
            'aria-label="Add transaction"',
            'href="'.route('transactions').'"',
            'href="'.route('statistics').'"',
        ], false);

    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $activeLinks = $xpath->query('//nav//a[@aria-current="page"]');

    expect($activeLinks)->toHaveCount(1);
    expect($activeLinks->item(0)->getAttribute('href'))->toBe(route($routeName));
    expect($xpath->query('//nav//a[@*[name()="wire:navigate.hover"]]'))->toHaveCount(4);
    expect($xpath->query('//nav//button'))->toHaveCount(1);
    if ($routeName !== 'tags') {
        expect($response->getContent())->toMatch('/<main\b[^>]*>\s*<\/main>/s');
    }
})->with([
    'dashboard' => ['home', 'home'],
    'tags' => ['tags', 'tags'],
    'transactions' => ['transactions', 'transactions'],
    'statistics' => ['statistics', 'statistics'],
]);

test('omits navigation and the transaction modal from authentication pages', function (string $path) {
    $this->get($path)->assertOk()
        ->assertDontSee('aria-label="Main navigation"', false)
        ->assertDontSeeLivewire('add-transaction-modal');
})->with(['/login', '/register']);

test('opens closes and reopens the empty transaction modal', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('add-transaction-modal')
        ->assertSet('isOpen', false)
        ->dispatch('open-transaction-modal')
        ->assertSet('isOpen', true)
        ->call('close')
        ->assertSet('isOpen', false)
        ->dispatch('open-transaction-modal')
        ->assertSet('isOpen', true);

    $this->assertDatabaseCount('users', 1);
});

test('refuses to open the transaction modal without authentication', function () {
    Livewire::test('add-transaction-modal')
        ->dispatch('open-transaction-modal')
        ->assertForbidden();
});
