<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('loads account contacts alphabetically in successive batches', function () {
    $user = User::factory()->create();
    $names = array_map(fn (int $number): string => sprintf('Contact %02d', $number), range(1, 26));
    foreach (array_reverse($names) as $name) {
        Contact::factory()->for($user)->create(['name' => $name]);
    }
    Contact::factory()->create(['name' => 'Another account person']);

    $this->actingAs($user)->get(route('contacts'))->assertOk()
        ->assertSeeLivewire('contact-manager')
        ->assertSee('Contact 01')->assertDontSee('Contact 13');

    Livewire::actingAs($user)->test('contact-manager')
        ->assertSeeInOrder(array_slice($names, 0, 12))
        ->assertSee('12 contacts shown')
        ->assertDontSee('Contact 13')
        ->call('loadMore')
        ->assertSeeInOrder(array_slice($names, 0, 24))
        ->assertDontSee('Contact 25')
        ->call('loadMore')
        ->assertSeeInOrder($names)
        ->assertSee('26 contacts shown')
        ->assertDontSee('Load more contacts')
        ->call('loadMore')
        ->assertDontSee('Another account person');
});

test('shows the first contact empty state', function () {
    Livewire::actingAs(User::factory()->create())->test('contact-manager')
        ->assertSee('Add your first contact')
        ->assertSee('0 contacts shown')
        ->assertDontSee('Load more contacts');
});

test('creates a contact without a photo for the current account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('contact-manager')->call('create')
        ->assertSet('isOpen', true)
        ->set('name', '  Sara Ahmadi  ')->call('save')
        ->assertHasNoErrors()->assertSet('isOpen', false)
        ->assertSee('Sara Ahmadi')->assertSee('Contact created.');

    $this->assertDatabaseHas('contacts', ['user_id' => $user->id, 'name' => 'Sara Ahmadi', 'profile_photo' => null]);
    $this->assertDatabaseCount('contacts', 1);
});

test('stores a contact photo privately and serves it only to its owner', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $photo = UploadedFile::fake()->image('portrait.png', 120, 120);

    Livewire::actingAs($user)->test('contact-manager')->call('create')
        ->set('name', 'Sara Ahmadi')->set('photo', $photo)->call('save')
        ->assertHasNoErrors()->assertSee('Contact created.');

    $contact = $user->contacts()->sole();
    Storage::disk('local')->assertExists($contact->profile_photo);
    expect($contact->profile_photo)->toStartWith('contacts/'.$user->id.'/');

    $this->actingAs($user)->get(route('contacts.photo', $contact))->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertStreamedContent(Storage::disk('local')->get($contact->profile_photo));

    $this->actingAs(User::factory()->create())->get(route('contacts.photo', $contact))->assertNotFound();
});

test('rejects invalid contact names without storing a contact', function (string $name, string $rule, string $message) {
    Livewire::actingAs(User::factory()->create())->test('contact-manager')->call('create')
        ->set('name', $name)->call('save')
        ->assertHasErrors(['name' => $rule])->assertSee($message)
        ->assertSet('isOpen', true);

    $this->assertDatabaseCount('contacts', 0);
})->with([
    'missing' => ['', 'required', 'The name field is required.'],
    'whitespace' => ['   ', 'required', 'The name field is required.'],
    'too long' => [str_repeat('a', 101), 'max', 'The name field must not be greater than 100 characters.'],
]);

test('rejects invalid photos without changing a stored contact', function (Closure $upload, string $rule, string $message) {
    Storage::fake('local');
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();

    Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->set('photo', $upload())->call('save')
        ->assertHasErrors(['photo' => $rule])->assertSee($message);

    $this->assertDatabaseHas('contacts', $contact->getAttributes());
    Storage::disk('local')->assertDirectoryEmpty('contacts');
})->with([
    'document' => [fn () => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), 'image', 'The photo field must be an image.'],
    'SVG' => [fn () => UploadedFile::fake()->createWithContent('portrait.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'), 'image', 'The photo field must be an image.'],
    'GIF' => [fn () => UploadedFile::fake()->image('portrait.gif'), 'mimes', 'The photo field must be a file of type: jpg, jpeg, png, webp.'],
    'oversized' => [fn () => UploadedFile::fake()->image('portrait.jpg')->size(2049), 'max', 'The photo field must not be greater than 2048 kilobytes.'],
]);

test('edits a name while preserving the existing photo', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('portrait.jpg')->store('contacts/'.$user->id, 'local');
    $contact = Contact::factory()->for($user)->create(['name' => 'Sara', 'profile_photo' => $path]);

    Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->assertSet('name', 'Sara')->assertSet('isOpen', true)
        ->set('name', '  Sara Ahmadi  ')->call('save')
        ->assertHasNoErrors()->assertSee('Contact updated.');

    expect($contact->fresh())->name->toBe('Sara Ahmadi')->profile_photo->toBe($path);
    Storage::disk('local')->assertExists($path);
    $this->assertDatabaseCount('contacts', 1);
});

test('replaces a photo and removes the old file after saving', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $oldPath = UploadedFile::fake()->image('old.jpg')->store('contacts/'.$user->id, 'local');
    $contact = Contact::factory()->for($user)->create(['profile_photo' => $oldPath]);

    Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->set('photo', UploadedFile::fake()->image('new.png'))->call('save')
        ->assertHasNoErrors();

    $newPath = $contact->fresh()->profile_photo;
    expect($newPath)->not->toBe($oldPath);
    Storage::disk('local')->assertExists($newPath);
    Storage::disk('local')->assertMissing($oldPath);
});

test('removes an existing photo when requested', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('portrait.jpg')->store('contacts/'.$user->id, 'local');
    $contact = Contact::factory()->for($user)->create(['profile_photo' => $path]);

    Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->set('removePhoto', true)->call('save')->assertHasNoErrors();

    expect($contact->fresh()->profile_photo)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

test('closing discards changes and a new editor clears the file and validation', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();

    Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->set('photo', UploadedFile::fake()->image('unsaved.jpg'))
        ->set('name', '   ')->call('save')->assertHasErrors('name')
        ->call('close')->assertSet('isOpen', false)
        ->call('create')->assertSet('isOpen', true)
        ->assertSet('editingContactId', null)->assertSet('name', '')
        ->assertSet('photo', null)->assertSet('removePhoto', false)->assertHasNoErrors();

    $this->assertDatabaseHas('contacts', $contact->getAttributes());
    Storage::disk('local')->assertDirectoryEmpty('contacts');
});

test('deletes the selected contact and photo and leaves other contacts intact', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $path = UploadedFile::fake()->image('portrait.jpg')->store('contacts/'.$user->id, 'local');
    $contact = Contact::factory()->for($user)->create(['name' => 'Remove me', 'profile_photo' => $path]);
    $otherContact = Contact::factory()->for($user)->create();

    Livewire::actingAs($user)->test('contact-manager')->call('delete', $contact->id)
        ->assertDontSee('Remove me')->assertSee('Contact deleted.')->assertSee('1 contact shown');

    $this->assertModelMissing($contact);
    $this->assertModelExists($otherContact);
    Storage::disk('local')->assertMissing($path);
});

test('refuses another account contact actions', function (string $action) {
    $user = User::factory()->create();
    $contact = Contact::factory()->create();

    Livewire::actingAs($user)->test('contact-manager')->call($action, $contact->id)->assertNotFound();

    $this->assertDatabaseHas('contacts', $contact->getAttributes());
})->with(['edit', 'delete']);

test('rechecks ownership before saving an open editor or storing its upload', function () {
    Storage::fake('local');
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $component = Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id)
        ->set('photo', UploadedFile::fake()->image('portrait.jpg'))->set('name', 'Unauthorized change');
    $contact->user()->associate(User::factory()->create())->save();

    $component->call('save')->assertNotFound();

    $this->assertDatabaseHas('contacts', $contact->getAttributes());
    Storage::disk('local')->assertDirectoryEmpty('contacts');
});

test('prevents tampering with contact identifiers and pagination limits', function (string $property, int $value) {
    expect(fn () => Livewire::actingAs(User::factory()->create())->test('contact-manager')->set($property, $value))
        ->toThrow(CannotUpdateLockedPropertyException::class);
})->with([
    'editing identifier' => ['editingContactId', 99],
    'batch limit' => ['visibleCount', 1000000],
]);

test('refuses contact actions after the session ends', function (string $action) {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();
    $component = Livewire::actingAs($user)->test('contact-manager')->call('edit', $contact->id);
    auth()->logout();

    $component->call($action, $contact->id)->assertForbidden();

    $this->assertDatabaseHas('contacts', $contact->getAttributes());
})->with(['create', 'edit', 'save', 'delete', 'loadMore']);

test('keeps profiles empty with contacts active in navigation', function () {
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create();

    $response = $this->actingAs($user)->get(route('contacts.show', $contact))->assertOk()
        ->assertViewIs('contacts.show')->assertSee('Contact profile · Accounting Software');

    expect($response->getContent())->toMatch('/<main\b[^>]*>\s*<\/main>/s');
    $document = new DOMDocument;
    $document->loadHTML($response->getContent(), LIBXML_NOERROR | LIBXML_NOWARNING);
    $xpath = new DOMXPath($document);
    expect($xpath->query('//nav//a[@aria-current="page"]')->item(0)->getAttribute('href'))->toBe(route('contacts'));
});

test('protects contact profile and photo routes from guests', function (string $routeName) {
    $contact = Contact::factory()->create();
    $this->get(route($routeName, $contact))->assertRedirectToRoute('login');
})->with(['contacts.show', 'contacts.photo']);

test('hides another account profile and photo', function (string $routeName) {
    $contact = Contact::factory()->create();
    $this->actingAs(User::factory()->create())->get(route($routeName, $contact))->assertNotFound();
})->with(['contacts.show', 'contacts.photo']);

test('returns not found for contacts without an available photo', function (?string $path) {
    Storage::fake('local');
    $user = User::factory()->create();
    $contact = Contact::factory()->for($user)->create(['profile_photo' => $path]);
    $this->actingAs($user)->get(route('contacts.photo', $contact))->assertNotFound();
})->with([null, 'contacts/missing.jpg']);

test('escapes contact names in cards and icon labels', function () {
    $user = User::factory()->create();
    $name = '<script>alert("contact")</script>';
    Contact::factory()->for($user)->create(['name' => $name]);

    Livewire::actingAs($user)->test('contact-manager')->assertSee($name)->assertDontSeeHtml($name);
});
