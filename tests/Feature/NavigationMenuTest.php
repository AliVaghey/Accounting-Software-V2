<?php

use App\Models\User;
use Livewire\Livewire;

test('redirects guests away from navigation destinations', function (string $path) {
    $this->get($path)->assertRedirectToRoute('login');
})->with(['/tags', '/transactions', '/statistics', '/contacts']);

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
            '>More</span>',
            'href="'.route('statistics').'"',
            'href="'.route('contacts').'"',
        ], false);

    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    $activeLinks = $xpath->query('//nav//a[@aria-current="page"]');

    expect($activeLinks)->toHaveCount(1);
    expect($activeLinks->item(0)->getAttribute('href'))->toBe(route($routeName));
    expect($xpath->query('//nav//a[@*[name()="wire:navigate.hover"]]'))->toHaveCount(5);
    expect($xpath->query('//nav//button'))->toHaveCount(2);
    expect($xpath->query('//div[@id="more-options"]//a'))->toHaveCount(2);
    expect($xpath->query('//button[@id="more-options-button" and @aria-controls="more-options" and @aria-expanded="false"]'))->toHaveCount(1);
    expect($xpath->query('//div[@id="more-options"]//a')->item(0)->getAttribute('href'))->toBe(route('statistics'));
    expect($xpath->query('//div[@id="more-options"]//a')->item(1)->getAttribute('href'))->toBe(route('contacts'));
    expect($xpath->query('//button[@id="more-options-button" and contains(@class, "navigation-more-active")]'))
        ->toHaveCount(in_array($routeName, ['statistics', 'contacts']) ? 1 : 0);
    if (! in_array($routeName, ['tags', 'contacts', 'transactions'])) {
        expect($response->getContent())->toMatch('/<main\b[^>]*>\s*<\/main>/s');
    }
})->with([
    'dashboard' => ['home', 'home'],
    'tags' => ['tags', 'tags'],
    'transactions' => ['transactions', 'transactions'],
    'statistics' => ['statistics', 'statistics'],
    'contacts' => ['contacts', 'contacts'],
]);

test('omits navigation and the transaction modal from authentication pages', function (string $path) {
    $this->get($path)->assertOk()
        ->assertDontSee('aria-label="Main navigation"', false)
        ->assertDontSeeLivewire('add-transaction-modal');
})->with(['/login', '/register']);

test('opens closes and reopens the transaction modal', function () {
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
        ->assertForbidden();
});
