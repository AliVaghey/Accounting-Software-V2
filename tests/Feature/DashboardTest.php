<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-11 12:00:00', 'UTC'));
});

test('renders the date and monthly statistics before loading recent transactions', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->create(['description' => 'Deferred ledger entry']);

    $this->actingAs($user)->get(route('home'))->assertOk()->assertViewIs('home')
        ->assertSeeLivewire('dashboard-overview')->assertSeeLivewire('recent-transactions')
        ->assertSeeInOrder(['Wednesday, 20 Aban 1405', 'This month by tag', 'Recent transactions', 'Loading recent transactions…'])
        ->assertDontSee('Deferred ledger entry');
});

test('uses Persian month boundaries and Tehran midnight', function (string $now, string $date, string $start, string $end) {
    $this->travelTo(Carbon::parse($now, 'UTC'));
    $user = User::factory()->create();

    $component = Livewire::actingAs($user)->test('dashboard-overview')->assertSee($date);

    expect($component->get('period')['start']->toDateTimeString())->toBe($start);
    expect($component->get('period')['end']->toDateTimeString())->toBe($end);
})->with([
    'leap Esfand ends before Tehran midnight' => ['2025-03-20 20:29:59', 'Thursday, 30 Esfand 1403', '2025-02-18 20:30:00', '2025-03-20 20:30:00'],
    'Nowruz starts at Tehran midnight' => ['2025-03-20 20:30:00', 'Friday, 1 Farvardin 1404', '2025-03-20 20:30:00', '2025-04-20 20:30:00'],
    'non-leap Esfand has 29 days' => ['2026-03-20 12:00:00', 'Friday, 29 Esfand 1404', '2026-02-19 20:30:00', '2026-03-20 20:30:00'],
    'Aban has 30 days' => ['2026-11-11 12:00:00', 'Wednesday, 20 Aban 1405', '2026-10-22 20:30:00', '2026-11-21 20:30:00'],
]);

test('counts the entire Persian month with an inclusive start and exclusive end', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Boundary tag']);
    foreach (['2026-10-22 20:29:59', '2026-10-22 20:30:00', '2026-11-21 20:29:59', '2026-11-21 20:30:00'] as $date) {
        Transaction::factory()->for($user)->create(['amount' => 1000, 'created_at' => $date])->tags()->attach($tag);
    }

    Livewire::actingAs($user)->test('dashboard-overview')->assertSee('2 transactions')->assertSee('2,000')
        ->assertSee('100.0% of transactions');
});

test('shows tag amounts and percentages of all monthly transactions rather than amounts', function () {
    $user = User::factory()->create();
    $uber = Tag::factory()->for($user)->create(['name' => 'Uber', 'color' => '#a8c7fa']);
    $food = Tag::factory()->for($user)->create(['name' => 'Food', 'color' => '#a5d6c0']);
    Tag::factory()->for($user)->create(['name' => 'Unused tag']);
    Transaction::factory()->for($user)->create(['amount' => 1000000])->tags()->attach($uber);
    Transaction::factory()->income()->for($user)->create(['amount' => 2000000])->tags()->attach($uber);
    Transaction::factory()->for($user)->create(['amount' => 5000])->tags()->attach($food);
    Transaction::factory()->for($user)->create(['amount' => 6000])->tags()->attach($food);
    Transaction::factory()->for($user)->create(['amount' => 7000]);

    $component = Livewire::actingAs($user)->test('dashboard-overview')
        ->assertSee('5 transactions')->assertSee('Uber')->assertSee('3,000,000')
        ->assertSee('Food')->assertSee('11,000')->assertSee('40.0% of transactions')->assertDontSee('Unused tag');

    $statistics = $component->get('statistics');
    expect(array_column($statistics['tags'], 'share'))->toBe([50.0, 50.0]);
    expect(array_column($statistics['tags'], 'percentage'))->toBe([40.0, 40.0]);
    $document = new DOMDocument;
    $document->loadHTML($component->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//svg//circle[@stroke="#a8c7fa" and @stroke-dasharray="50 100"]'))->toHaveCount(1);
    expect($xpath->query('//svg//text'))->toHaveCount(0);
});

test('counts overlapping tags equally in the pie while preserving transaction percentages', function () {
    $user = User::factory()->create();
    $tags = Tag::factory()->for($user)->count(2)->create();
    Transaction::factory()->for($user)->create(['amount' => 1000])->tags()->attach($tags->modelKeys());
    Transaction::factory()->for($user)->create(['amount' => 9000])->tags()->attach($tags->first());

    $component = Livewire::actingAs($user)->test('dashboard-overview')
        ->assertSee('10,000')->assertSee('1,000')->assertSee('100.0% of transactions')->assertSee('50.0% of transactions');

    $breakdown = $component->get('statistics')['tags'];
    expect(round($breakdown[0]['share'], 1))->toBe(66.7);
    expect(round($breakdown[1]['share'], 1))->toBe(33.3);
    expect(array_sum(array_column($breakdown, 'share')))->toEqualWithDelta(100, 0.00001);
});

test('shows empty and untagged monthly states without inventing pie slices', function () {
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('dashboard-overview')
        ->assertSee('0 transactions')->assertSee('No transactions this Persian month.')->assertSee('Add transaction');
    expect($component->get('statistics')['tags'])->toBe([]);
    Transaction::factory()->for($user)->create();

    $component->dispatch('transaction-saved', message: 'Transaction created.')
        ->assertSee('1 transaction')->assertSee('No tagged transactions this Persian month.');
    expect($component->get('statistics')['tags'])->toBe([]);
});

test('does not leak foreign tags or transactions through stale attachments', function () {
    $user = User::factory()->create();
    $ownedTag = Tag::factory()->for($user)->create(['name' => 'Owned tag']);
    $foreignTag = Tag::factory()->create(['name' => 'Foreign private tag']);
    Transaction::factory()->for($user)->create(['amount' => 1000])->tags()->attach([$ownedTag->id, $foreignTag->id]);
    Transaction::factory()->create(['amount' => 999999])->tags()->attach($ownedTag);

    Livewire::actingAs($user)->test('dashboard-overview')
        ->assertSee('1 transaction')->assertSee('Owned tag')->assertSee('1,000')
        ->assertDontSee('Foreign private tag')->assertDontSee('999,999');
});

test('aggregates the full month even when recent transactions are capped at thirty', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();
    $transactions = Transaction::factory()->for($user)->count(31)->create(['amount' => 1000]);
    foreach ($transactions as $transaction) {
        $transaction->tags()->attach($tag);
    }

    Livewire::actingAs($user)->test('dashboard-overview')->assertSee('31 transactions')->assertSee('31,000');
    Livewire::actingAs($user)->test('recent-transactions')->assertSee('30 transactions shown');
});

test('loads only the last thirty owned transactions in stable date and identifier order', function () {
    $user = User::factory()->create();
    foreach (range(1, 31) as $number) {
        Transaction::factory()->for($user)->create(['description' => sprintf('Recent entry %02d', $number)]);
    }
    Transaction::factory()->for($user)->create(['description' => 'Older with higher ID', 'created_at' => now()->subMonth()]);
    Transaction::factory()->create(['description' => 'Another account entry']);

    Livewire::actingAs($user)->test('recent-transactions')->assertSee('30 transactions shown')
        ->assertSeeInOrder(array_map(fn (int $number): string => sprintf('Recent entry %02d', $number), range(31, 2)))
        ->assertDontSee('Recent entry 01')->assertDontSee('Older with higher ID')->assertDontSee('Another account entry')
        ->assertSee('View all')->assertDontSee('Load more transactions');
});

test('shows recent transaction details but hides reassigned contacts and tags', function () {
    $user = User::factory()->create(['name' => 'Personal ledger']);
    $contact = Contact::factory()->for($user)->create(['name' => 'Sara Ahmadi']);
    $tag = Tag::factory()->for($user)->create(['name' => 'Salary']);
    $transaction = Transaction::factory()->income()->for($user)->for($contact)->create([
        'amount' => 1234567, 'description' => 'Monthly payment', 'created_at' => '2025-03-21 10:15:00',
    ]);
    $transaction->tags()->attach($tag);
    $component = Livewire::actingAs($user)->test('recent-transactions')
        ->assertSee('+1,234,567')->assertSee('Monthly payment')->assertSee('Contact: Sara Ahmadi')
        ->assertSee('Salary')->assertSee('Owner: Personal ledger')->assertSee('1404/01/01 · 13:45');
    $otherUser = User::factory()->create();
    $contact->user()->associate($otherUser)->save();
    $tag->user()->associate($otherUser)->save();

    $component->dispatch('transaction-saved')->assertDontSee('Sara Ahmadi')->assertDontSee('Salary')
        ->assertSee('No contact')->assertSee('No tags');
});

test('refreshes recent entries and monthly statistics after saving and deleting', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'New tag']);
    $overview = Livewire::actingAs($user)->test('dashboard-overview')->assertSee('0 transactions');
    $recent = Livewire::actingAs($user)->test('recent-transactions')->assertSee('No transactions yet.');
    Transaction::factory()->for($user)->create(['description' => 'New entry', 'amount' => 2000])->tags()->attach($tag);

    $overview->dispatch('transaction-saved')->assertSee('1 transaction')->assertSee('New tag')->assertSee('2,000');
    $recent->dispatch('transaction-saved', message: 'Transaction created.')->assertSee('New entry')->assertSee('Transaction created.');
    $transaction = $user->transactions()->sole();
    $recent->call('delete', $transaction->id)->assertSee('Transaction deleted.')->assertSee('No transactions yet.')
        ->assertDispatched('transaction-deleted');
    $overview->dispatch('transaction-deleted')->assertSee('0 transactions')->assertDontSee('New tag');
    $this->assertModelMissing($transaction);
});

test('refuses to delete another accounts recent transaction', function () {
    $transaction = Transaction::factory()->create();

    Livewire::actingAs(User::factory()->create())->test('recent-transactions')->call('delete', $transaction->id)->assertNotFound();

    $this->assertModelExists($transaction);
});

test('escapes tag names in monthly statistics', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => '<script>tag()</script>']);
    Transaction::factory()->for($user)->create()->tags()->attach($tag);

    Livewire::actingAs($user)->test('dashboard-overview')->assertSee('<script>tag()</script>')->assertDontSee('<script>tag()</script>', false);
});

test('refuses unauthenticated dashboard components', function (string $component) {
    Livewire::test($component)->assertForbidden();
})->with(['dashboard-overview', 'recent-transactions']);

test('refuses dashboard updates when the session ends', function (string $component) {
    $instance = Livewire::actingAs(User::factory()->create())->test($component);
    auth()->logout();

    $instance->call('$refresh')->assertForbidden();
})->with(['dashboard-overview', 'recent-transactions']);
