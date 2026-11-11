<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('lists only owned transactions latest first in successive batches', function () {
    $user = User::factory()->create();
    foreach (range(1, 26) as $number) {
        Transaction::factory()->for($user)->create([
            'description' => sprintf('Entry %02d', $number),
            'created_at' => '2026-11-01 12:00:00',
        ]);
    }
    Transaction::factory()->create(['description' => 'Private foreign entry']);
    $descriptions = array_map(fn (int $number): string => sprintf('Entry %02d', $number), range(26, 1));

    $this->actingAs($user)->get(route('transactions'))->assertOk()
        ->assertSeeLivewire('transaction-manager')->assertSee('Entry 26')->assertDontSee('Entry 14');

    Livewire::actingAs($user)->test('transaction-manager')
        ->assertSeeInOrder(array_slice($descriptions, 0, 12))
        ->assertSee('12 transactions shown')->assertDontSee('Entry 14')
        ->call('loadMore')->assertSeeInOrder(array_slice($descriptions, 0, 24))
        ->assertDontSee('Entry 02')
        ->call('loadMore')->assertSeeInOrder($descriptions)
        ->assertSee('26 transactions shown')->assertDontSee('Load more transactions')
        ->call('loadMore')->assertSet('visibleCount', 36)
        ->assertDontSee('Private foreign entry');
});

test('shows an empty ledger with an add action', function () {
    Livewire::actingAs(User::factory()->create())->test('transaction-manager')
        ->assertSee('Add your first transaction')->assertSee('0 transactions shown')
        ->assertDontSee('Load more transactions');
});

test('orders by creation time before the identifier tie breaker', function () {
    $user = User::factory()->create();
    Transaction::factory()->for($user)->create(['description' => 'Newest by date', 'created_at' => '2026-11-03 12:00:00']);
    Transaction::factory()->for($user)->create(['description' => 'Oldest by date', 'created_at' => '2026-11-01 12:00:00']);
    Transaction::factory()->for($user)->create(['description' => 'Middle by date', 'created_at' => '2026-11-02 12:00:00']);

    Livewire::actingAs($user)->test('transaction-manager')
        ->assertSeeInOrder(['Newest by date', 'Middle by date', 'Oldest by date']);
});

test('shows transaction details with a Persian creation date and readable direction', function () {
    $user = User::factory()->create(['name' => 'Ledger owner']);
    $contact = Contact::factory()->for($user)->create(['name' => 'Sara Ahmadi']);
    $tag = Tag::factory()->for($user)->create(['name' => 'Salary']);
    $transaction = Transaction::factory()->income()->for($user)->for($contact)->create([
        'amount' => 1234567, 'description' => 'Monthly payment', 'created_at' => '2025-03-21 10:15:00',
    ]);
    $transaction->tags()->attach($tag);

    Livewire::actingAs($user)->test('transaction-manager')
        ->assertSee('+1,234,567')->assertSee('Income')->assertSee('Monthly payment')
        ->assertSee('Contact: Sara Ahmadi')->assertSee('Salary')->assertSee('Owner: Ledger owner')
        ->assertSee('1404/01/01 · 13:45');
});

test('formats Persian leap days and the Tehran date boundary', function () {
    $transaction = Transaction::factory()->make([
        'created_at' => Carbon::parse('2025-03-20 20:29:00', 'UTC'),
    ]);
    expect($transaction->persianCreatedAt())->toBe('1403/12/30 · 23:59');
    $transaction->created_at = Carbon::parse('2025-03-20 20:30:00', 'UTC');
    expect($transaction->persianCreatedAt())->toBe('1404/01/01 · 00:00');
});

test('creates income and expense with optional associations omitted', function (bool $isIncome) {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('add-transaction-modal')->call('open')
        ->set('amount', '1234567')->set('description', '   ')->call('save', $isIncome)
        ->assertHasNoErrors()->assertSet('isOpen', false)
        ->assertDispatched('transaction-saved', message: 'Transaction created.');

    $this->assertDatabaseHas('transactions', [
        'user_id' => $user->id, 'amount' => 1234567, 'description' => null,
        'contact_id' => null, 'is_income' => $isIncome,
    ]);
    $this->assertDatabaseCount('transactions', 1);
    $this->assertDatabaseCount('tag_transaction', 0);
})->with(['expense' => false, 'income' => true]);

test('creates a transaction with several tags and a contact', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $tags = Tag::factory()->for($user)->count(2)->create();

    Livewire::actingAs($user)->test('add-transaction-modal')->call('open')
        ->set('amount', '999999999999')->set('description', '  Shared groceries  ')
        ->set('contactId', (string) $contact->id)->set('tagIds', $tags->modelKeys())
        ->call('save', false)->assertHasNoErrors();

    $transaction = $user->transactions()->sole();
    expect($transaction)->amount->toBe(999999999999)->description->toBe('Shared groceries');
    expect($contact->transactions()->sole()->id)->toBe($transaction->id);
    expect($transaction->tags->modelKeys())->toEqualCanonicalizing($tags->modelKeys());
    expect($tags->first()->transactions()->sole()->id)->toBe($transaction->id);
});

test('edits a transaction and replaces its associations without changing its creation date', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $tags = Tag::factory()->for($user)->count(2)->create();
    $transaction = Transaction::factory()->for($user)->for($contact)->create([
        'amount' => 1000, 'description' => 'Old description', 'created_at' => '2026-10-01 10:00:00',
    ]);
    $transaction->tags()->attach($tags->first());

    Livewire::actingAs($user)->test('add-transaction-modal')
        ->dispatch('edit-transaction', transactionId: $transaction->id)
        ->assertSet('amount', '1000')->assertSet('description', 'Old description')
        ->assertSet('contactId', (string) $contact->id)->assertSet('tagIds', [$tags->first()->id])
        ->set('amount', '2000')->set('description', '  Updated  ')
        ->set('contactId', '')->set('tagIds', [$tags->last()->id])
        ->call('save', true)->assertHasNoErrors()->assertSet('isOpen', false)
        ->assertDispatched('transaction-saved', message: 'Transaction updated.');

    expect($transaction->fresh())->amount->toBe(2000)->description->toBe('Updated')
        ->contact_id->toBeNull()->is_income->toBeTrue();
    expect($transaction->fresh()->created_at->format('Y-m-d H:i:s'))->toBe('2026-10-01 10:00:00');
    expect($transaction->fresh()->tags->modelKeys())->toBe([$tags->last()->id]);
    $this->assertDatabaseCount('transactions', 1);
});

test('removes all tags and description when editing', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create();
    $transaction->tags()->attach(Tag::factory()->for($user)->create());

    Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id)
        ->set('tagIds', [])->set('description', '')->call('save', false)->assertHasNoErrors();

    expect($transaction->fresh()->description)->toBeNull();
    $this->assertDatabaseCount('tag_transaction', 0);
});

test('rejects invalid amounts without saving a transaction', function (string $amount, string $rule, string $message) {
    Livewire::actingAs(User::factory()->create())->test('add-transaction-modal')->call('open')
        ->set('amount', $amount)->call('save', false)->assertHasErrors(['amount' => $rule])
        ->assertSee($message)->assertSet('isOpen', true)->assertNotDispatched('transaction-saved');

    $this->assertDatabaseCount('transactions', 0);
})->with([
    'missing' => ['', 'required', 'The amount field is required.'],
    'zero' => ['0', 'min', 'The amount field must be at least 1.'],
    'negative' => ['-1', 'regex', 'Enter a whole amount of up to 12 digits.'],
    'decimal' => ['1.5', 'regex', 'Enter a whole amount of up to 12 digits.'],
    'formatted request' => ['1,000', 'regex', 'Enter a whole amount of up to 12 digits.'],
    'exponent' => ['1e3', 'regex', 'Enter a whole amount of up to 12 digits.'],
    'too large' => ['1000000000000', 'regex', 'Enter a whole amount of up to 12 digits.'],
]);

test('rejects an oversized description', function () {
    Livewire::actingAs(User::factory()->create())->test('add-transaction-modal')->call('open')
        ->set('amount', '1')->set('description', str_repeat('a', 256))->call('save', false)
        ->assertHasErrors(['description' => 'max'])
        ->assertSee('The description field must not be greater than 255 characters.');

    $this->assertDatabaseCount('transactions', 0);
});

test('rejects foreign or missing contacts and tags', function (string $field, string $kind) {
    $user = User::factory()->create();
    $id = match ($kind) {
        'foreign contact' => Contact::factory()->create()->id,
        'foreign tag' => Tag::factory()->create()->id,
        default => 999999,
    };
    $value = $field === 'tagIds' ? [$id] : (string) $id;

    Livewire::actingAs($user)->test('add-transaction-modal')->call('open')
        ->set('amount', '1000')->set($field, $value)->call('save', false)
        ->assertHasErrors([$field === 'tagIds' ? 'tagIds.0' : 'contactId' => 'exists'])
        ->assertSee($field === 'tagIds' ? 'Choose only your own tags.' : 'Choose one of your contacts.');

    $this->assertDatabaseCount('transactions', 0);
    $this->assertDatabaseCount('tag_transaction', 0);
})->with([
    ['contactId', 'foreign contact'], ['tagIds', 'foreign tag'],
    ['contactId', 'missing'], ['tagIds', 'missing'],
]);

test('rejects duplicate and malformed tag selections', function (array $tagIds, string $rule) {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['id' => 1]);

    Livewire::actingAs($user)->test('add-transaction-modal')->call('open')
        ->set('amount', '1000')->set('tagIds', $tagIds)->call('save', false)
        ->assertHasErrors(['tagIds.0' => $rule]);

    $this->assertDatabaseCount('transactions', 0);
})->with(['duplicate' => [[1, 1], 'distinct'], 'malformed' => [['abc'], 'integer']]);

test('paginates only owned contacts in the picker and lists all owned tags', function () {
    $user = User::factory()->create();
    foreach (range(1, 26) as $number) {
        Contact::factory()->for($user)->create(['name' => sprintf('Person %02d', $number)]);
    }
    $tags = Tag::factory()->for($user)->count(15)->create();
    Contact::factory()->create(['name' => 'Foreign person']);
    Tag::factory()->create(['name' => 'Foreign tag']);

    $component = Livewire::actingAs($user)->test('add-transaction-modal')->call('open')
        ->assertSee($tags->pluck('name')->all())->assertSee('Person 12')->assertDontSee('Person 13')
        ->call('loadMoreContacts')->assertSee('Person 24')->assertDontSee('Person 25')
        ->call('loadMoreContacts')->assertSee('Person 26')->assertDontSee('Load more contacts')
        ->call('loadMoreContacts')->assertSet('contactsVisibleCount', 36)
        ->assertDontSee('Foreign person')->assertDontSee('Foreign tag');

    $component->call('close')->call('open')->assertSet('contactsVisibleCount', 12)->assertDontSee('Person 13');
});

test('keeps a later contact selected when reopening the editor', function () {
    $user = User::factory()->create();
    Contact::factory()->for($user)->count(12)->create(['name' => 'A person']);
    $contact = Contact::factory()->for($user)->create(['name' => 'Zara Selected']);
    $transaction = Transaction::factory()->for($user)->for($contact)->create();

    Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id)
        ->assertSee('Selected: Zara Selected')->call('save', false)->assertHasNoErrors();

    expect($transaction->fresh()->contact_id)->toBe($contact->id);
});

test('resets editor values and errors on cancellation and a fresh add', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create();

    Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id)
        ->set('amount', '0')->call('save', false)->assertHasErrors('amount')
        ->call('close')->assertSet('editingTransactionId', null)->assertSet('description', '')
        ->assertSet('tagIds', [])->assertSet('contactId', null)->assertHasNoErrors()
        ->call('open')->assertSet('amount', '')->assertSet('isOpen', true);

    $this->assertDatabaseCount('transactions', 1);
});

test('refuses foreign transaction editing and deletion', function (string $component, string $action) {
    $transaction = Transaction::factory()->create();

    Livewire::actingAs(User::factory()->create())->test($component)
        ->call($action, $transaction->id)->assertNotFound();

    $this->assertModelExists($transaction);
})->with([['add-transaction-modal', 'edit'], ['transaction-manager', 'delete']]);

test('rechecks transaction ownership before saving an open editor', function () {
    $user = User::factory()->create();
    $transaction = Transaction::factory()->for($user)->create(['amount' => 1000]);
    $component = Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id);
    $transaction->user()->associate(User::factory()->create())->save();

    $component->set('amount', '5000')->call('save', false)->assertNotFound();

    expect($transaction->fresh()->amount)->toBe(1000);
});

test('revalidates removed associations on an open editor', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->for($contact)->create(['amount' => 1000]);
    $transaction->tags()->attach($tag);
    $component = Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id);
    $contact->delete();
    $tag->delete();

    $component->set('amount', '5000')->call('save', false)
        ->assertHasErrors(['contactId' => 'exists', 'tagIds.0' => 'exists']);

    expect($transaction->fresh()->amount)->toBe(1000);
});

test('deletes a transaction and its attachments while preserving tags and contacts', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->for($contact)->create();
    $transaction->tags()->attach($tag);

    Livewire::actingAs($user)->test('transaction-manager')->call('delete', $transaction->id)
        ->assertSee('Transaction deleted.')->assertSee('Add your first transaction');

    $this->assertModelMissing($transaction);
    $this->assertDatabaseCount('tag_transaction', 0);
    $this->assertModelExists($contact);
    $this->assertModelExists($tag);
});

test('preserves transactions when their contact and tag are deleted', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $tag = Tag::factory()->for($user)->create();
    $transaction = Transaction::factory()->for($user)->for($contact)->create(['amount' => 1000]);
    $transaction->tags()->attach($tag);

    Livewire::actingAs($user)->test('contact-manager')->call('delete', $contact->id);
    Livewire::actingAs($user)->test('tag-manager')->call('delete', $tag->id);

    $this->assertModelExists($transaction);
    expect($transaction->fresh())->contact_id->toBeNull()->amount->toBe(1000);
    $this->assertDatabaseCount('tag_transaction', 0);
    Livewire::actingAs($user)->test('transaction-manager')->assertSee('No contact')->assertSee('No tags');
});

test('refuses saving a closed transaction editor', function () {
    Livewire::actingAs(User::factory()->create())->test('add-transaction-modal')
        ->set('amount', '1000')->call('save', false)->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
});

test('refreshes the ledger when a transaction is saved', function () {
    $user = User::factory()->create();
    $component = Livewire::actingAs($user)->test('transaction-manager')->assertSee('0 transactions shown');
    Transaction::factory()->for($user)->create(['description' => 'Newly saved entry']);

    $component->dispatch('transaction-saved', message: 'Transaction created.')
        ->assertSee('Newly saved entry')->assertSee('1 transaction shown')->assertSee('Transaction created.');
});

test('escapes user text in transaction cards and the editor', function () {
    $user = User::factory()->create(['name' => '<script>owner()</script>']);
    $contact = Contact::factory()->for($user)->create(['name' => '<script>contact()</script>']);
    $tag = Tag::factory()->for($user)->create(['name' => '<script>tag()</script>']);
    $transaction = Transaction::factory()->for($user)->for($contact)->create(['description' => '<script>description()</script>']);
    $transaction->tags()->attach($tag);

    Livewire::actingAs($user)->test('transaction-manager')
        ->assertSee('<script>description()</script>')->assertSee('<script>owner()</script>')
        ->assertSee('<script>contact()</script>')->assertSee('<script>tag()</script>')
        ->assertDontSee('<script>description()</script>', false)->assertDontSee('<script>owner()</script>', false)
        ->assertDontSee('<script>contact()</script>', false)->assertDontSee('<script>tag()</script>', false);
    Livewire::actingAs($user)->test('add-transaction-modal')->call('edit', $transaction->id)
        ->assertDontSee('<script>description()</script>', false)->assertDontSee('<script>contact()</script>', false)
        ->assertDontSee('<script>tag()</script>', false);
});

test('locks transaction identifiers and pagination limits', function (string $component, string $field) {
    Livewire::actingAs(User::factory()->create())->test($component)->set($field, 1000);
})->with([
    ['add-transaction-modal', 'editingTransactionId'], ['add-transaction-modal', 'contactsVisibleCount'],
    ['transaction-manager', 'visibleCount'],
])->throws(CannotUpdateLockedPropertyException::class);

test('refuses transaction components after the session ends', function (string $component, string $action) {
    $instance = Livewire::actingAs(User::factory()->create())->test($component);
    auth()->logout();

    $instance->call($action)->assertForbidden();

    $this->assertDatabaseCount('transactions', 0);
})->with([['add-transaction-modal', 'open'], ['transaction-manager', 'loadMore']]);

test('refuses unauthenticated transaction components', function (string $component) {
    Livewire::test($component)->assertForbidden();
})->with(['add-transaction-modal', 'transaction-manager']);
