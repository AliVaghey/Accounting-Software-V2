<?php

use App\Models\Tag;
use App\Models\User;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;

test('shows every account tag alphabetically without pagination', function () {
    $user = User::factory()->create();
    $names = array_map(fn (int $number): string => sprintf('Tag %02d', $number), range(1, 26));

    foreach (array_reverse($names) as $name) {
        Tag::factory()->for($user)->create(['name' => $name]);
    }

    Tag::factory()->create(['name' => 'Private tag']);

    $this->actingAs($user)->get(route('tags'))->assertOk()
        ->assertSeeLivewire('tag-manager')
        ->assertSeeInOrder($names)
        ->assertSee('26 tags')
        ->assertDontSee('Private tag');
});

test('shows an empty state for an account without tags', function () {
    Livewire::actingAs(User::factory()->create())->test('tag-manager')
        ->assertSee('Make your first tag')
        ->assertSee('0 tags');
});

test('creates a tag for the current account and normalizes its fields', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call('create')
        ->assertSet('isOpen', true)
        ->set('name', '  Groceries  ')
        ->set('color', '#A5D6C0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isOpen', false)
        ->assertSee('Groceries')
        ->assertSee('Tag created.');

    $this->assertDatabaseHas('tags', ['user_id' => $user->id, 'name' => 'Groceries', 'color' => '#a5d6c0']);
    $this->assertDatabaseCount('tags', 1);
});

test('rejects invalid tag fields without persisting a tag', function (string $field, string $value, string $rule, string $message) {
    Livewire::actingAs(User::factory()->create())->test('tag-manager')
        ->call('create')
        ->set('name', 'Groceries')
        ->set('color', '#a5d6c0')
        ->set($field, $value)
        ->call('save')
        ->assertHasErrors([$field => $rule])
        ->assertSee($message)
        ->assertSet('isOpen', true);

    $this->assertDatabaseCount('tags', 0);
})->with([
    'missing name' => ['name', '', 'required', 'The name field is required.'],
    'blank name' => ['name', '   ', 'required', 'The name field is required.'],
    'long name' => ['name', str_repeat('a', 61), 'max', 'The name field must not be greater than 60 characters.'],
    'missing color' => ['color', '', 'required', 'The color field is required.'],
    'short color' => ['color', '#abc', 'regex', 'Choose a valid six-digit hex color.'],
    'non-hex color' => ['color', '#gggggg', 'regex', 'Choose a valid six-digit hex color.'],
    'CSS injection' => ['color', '#fff; background: url(example)', 'regex', 'Choose a valid six-digit hex color.'],
    'trailing newline' => ['color', "#ffffff\n", 'regex', 'Choose a valid six-digit hex color.'],
]);

test('edits the selected tag and keeps other tags unchanged', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Food', 'color' => '#ffffff']);
    $otherTag = Tag::factory()->for($user)->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call('edit', $tag->id)
        ->assertSet('name', 'Food')
        ->assertSet('color', '#ffffff')
        ->assertSet('isOpen', true)
        ->set('name', 'Groceries')
        ->set('color', '#a5d6c0')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('isOpen', false)
        ->assertSee('Tag updated.');

    expect($tag->fresh())->name->toBe('Groceries')->color->toBe('#a5d6c0');
    $this->assertDatabaseHas('tags', $otherTag->getAttributes());
    $this->assertDatabaseCount('tags', 2);
});

test('keeps the stored tag unchanged when an edit fails validation', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call('edit', $tag->id)
        ->set('name', '')
        ->set('color', 'red')
        ->call('save')
        ->assertHasErrors(['name' => 'required', 'color' => 'regex'])
        ->assertSet('isOpen', true);

    $this->assertDatabaseHas('tags', $tag->getAttributes());
});

test('closing and reopening the editor clears unsaved edits and validation errors', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call('edit', $tag->id)
        ->set('name', '')
        ->call('save')
        ->assertHasErrors('name')
        ->call('close')
        ->assertSet('isOpen', false)
        ->call('create')
        ->assertSet('isOpen', true)
        ->assertSet('editingTagId', null)
        ->assertSet('name', '')
        ->assertSet('color', '#bbb8ff')
        ->assertHasNoErrors();

    $this->assertDatabaseHas('tags', $tag->getAttributes());
});

test('deletes only the selected tag and refreshes the list', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create(['name' => 'Remove me']);
    $otherTag = Tag::factory()->for($user)->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call('delete', $tag->id)
        ->assertDontSee('Remove me')
        ->assertSee('Tag deleted.')
        ->assertSee('1 tag');

    $this->assertModelMissing($tag);
    $this->assertModelExists($otherTag);
});

test('refuses access to another account tag', function (string $action) {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    Livewire::actingAs($user)->test('tag-manager')
        ->call($action, $tag->id)
        ->assertStatus(404);

    $this->assertDatabaseHas('tags', $tag->getAttributes());
})->with(['edit', 'delete']);

test('checks tag ownership again when saving an open editor', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();
    $component = Livewire::actingAs($user)->test('tag-manager')->call('edit', $tag->id);
    $tag->user()->associate($otherUser)->save();

    $component->set('name', 'Unauthorized change')->call('save')->assertStatus(404);

    expect($tag->fresh()->name)->toBe($tag->name);
});

test('prevents tampering with the editing tag identifier', function () {
    $user = User::factory()->create();
    $tag = Tag::factory()->create();

    expect(fn () => Livewire::actingAs($user)->test('tag-manager')->set('editingTagId', $tag->id))
        ->toThrow(CannotUpdateLockedPropertyException::class);

    $this->assertModelExists($tag);
});

test('refuses tag actions after the session ends', function (string $action) {
    $user = User::factory()->create();
    $tag = Tag::factory()->for($user)->create();
    $component = Livewire::actingAs($user)->test('tag-manager')->call('edit', $tag->id);
    auth()->logout();

    $component->call($action, $tag->id)->assertForbidden();

    $this->assertDatabaseHas('tags', $tag->getAttributes());
    $this->assertDatabaseCount('tags', 1);
})->with(['create', 'edit', 'save', 'delete']);

test('escapes tag names when rendering cards and their actions', function () {
    $user = User::factory()->create();
    $name = '<script>alert("tag")</script>';
    Tag::factory()->for($user)->create(['name' => $name]);

    Livewire::actingAs($user)->test('tag-manager')
        ->assertSee($name)
        ->assertDontSeeHtml($name);
});

test('uses contrasting text for tag colors', function (string $color, string $foreground) {
    $user = User::factory()->create();
    Tag::factory()->for($user)->create(['color' => $color]);

    Livewire::actingAs($user)->test('tag-manager')
        ->assertSeeHtml('--tag-color: '.$color.'; --tag-ink: '.$foreground.';');
})->with([
    'black' => ['#000000', '#ffffff'],
    'white' => ['#ffffff', '#000000'],
    'blue' => ['#0000ff', '#ffffff'],
    'mint' => ['#a5d6c0', '#000000'],
]);
