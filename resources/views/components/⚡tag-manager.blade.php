<?php

use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

new class extends Component
{
    public bool $isOpen = false;

    #[Locked]
    public ?int $editingTagId = null;

    public string $name = '';

    public string $color = '#bbb8ff';

    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** @return Collection<int, Tag> */
    #[Computed]
    public function tags(): Collection
    {
        return $this->currentUser()->tags()->orderBy('name')->orderBy('id')->get();
    }

    public function create(): void
    {
        $this->reset('editingTagId', 'name', 'color', 'status');
        $this->resetValidation();
        $this->isOpen = true;
    }

    public function edit(int $tagId): void
    {
        $tag = $this->currentUser()->tags()->findOrFail($tagId);

        $this->resetValidation();
        $this->reset('status');
        $this->editingTagId = $tag->id;
        $this->name = $tag->name;
        $this->color = $tag->color;
        $this->isOpen = true;
    }

    public function save(): void
    {
        $this->name = trim($this->name);

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:60'],
            'color' => ['required', 'string', 'regex:/\A#[0-9a-fA-F]{6}\z/'],
        ], [
            'color.regex' => 'Choose a valid six-digit hex color.',
        ]);

        $validated['color'] = strtolower($validated['color']);
        $isEditing = $this->editingTagId !== null;

        if ($isEditing) {
            $this->currentUser()->tags()->findOrFail($this->editingTagId)->update($validated);
        } else {
            $this->currentUser()->tags()->create($validated);
        }

        unset($this->tags);
        $this->close();
        $this->status = $isEditing ? 'Tag updated.' : 'Tag created.';
    }

    public function delete(int $tagId): void
    {
        $this->currentUser()->tags()->findOrFail($tagId)->delete();

        unset($this->tags);
        $this->status = 'Tag deleted.';
    }

    public function close(): void
    {
        $this->reset('isOpen', 'editingTagId', 'name', 'color');
        $this->resetValidation();
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="flex flex-col gap-8">
    <header class="flex flex-col gap-5">
        <p class="text-sm text-galaxy-muted">Accounting Software</p>
        <div class="flex items-center justify-between gap-4">
            <h1 class="text-2xl font-semibold tracking-tight">Tags</h1>
            <button type="button" class="auth-button" wire:click="create" wire:loading.attr="disabled">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Add tag
            </button>
        </div>
        <p class="text-sm leading-6 text-galaxy-muted">A little color for the way you spend.</p>
    </header>

    <p role="status" class="text-sm text-galaxy-accent" @if ($status === '') hidden @endif>{{ $status }}</p>

    <section aria-label="Your tags" class="flex flex-col gap-4">
        <p class="text-sm text-galaxy-muted">{{ $this->tags->count() }} {{ Str::plural('tag', $this->tags->count()) }}</p>

        @forelse ($this->tags as $tag)
            <article class="tag-card" wire:key="tag-{{ $tag->id }}" style="--tag-color: {{ $tag->color }}; --tag-ink: {{ $tag->foregroundColor() }};">
                <div class="flex items-start justify-between gap-4">
                    <h2 class="min-w-0 text-xl font-semibold wrap-anywhere">{{ $tag->name }}</h2>
                    <i class="bi bi-tag shrink-0 text-2xl" aria-hidden="true"></i>
                </div>
                <div class="flex justify-end gap-2">
                    <button type="button" class="tag-action" aria-label="Edit {{ $tag->name }}" wire:click="edit({{ $tag->id }})" wire:loading.attr="disabled">
                        <i class="bi bi-pencil" aria-hidden="true"></i>
                        Edit
                    </button>
                    <button type="button" class="tag-action" aria-label="Delete {{ $tag->name }}" wire:click="delete({{ $tag->id }})" wire:confirm="Delete &quot;{{ $tag->name }}&quot;? This cannot be undone." wire:loading.attr="disabled">
                        <i class="bi bi-trash3" aria-hidden="true"></i>
                        Delete
                    </button>
                </div>
            </article>
        @empty
            <div class="tag-empty flex flex-col gap-3 p-6">
                <h2 class="text-lg font-semibold">Make your first tag</h2>
                <p class="text-sm leading-6 text-galaxy-muted">Give it a name and a color that feels right. Your tags will live here.</p>
            </div>
        @endforelse
    </section>

    <dialog
        id="tag-editor"
        class="transaction-modal"
        aria-labelledby="tag-editor-title"
        wire:ignore.self
        x-data
        x-effect="$wire.isOpen ? (!$el.open && $el.showModal()) : ($el.open && $el.close())"
        x-on:cancel.prevent="$wire.isOpen = false; $wire.close()"
        x-on:close="if (!$el.open && $wire.isOpen) { $wire.isOpen = false; $wire.close() }"
        x-on:click="if ($event.target === $el) { $wire.isOpen = false; $wire.close() }"
    >
        <div class="p-6" x-on:click.stop>
            <div class="mb-6 flex items-center justify-between gap-4">
                <h2 id="tag-editor-title" class="text-xl font-semibold">{{ $editingTagId === null ? 'Add tag' : 'Edit tag' }}</h2>
                <button type="button" class="modal-close" aria-label="Close tag editor" wire:click="close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <form wire:submit="save" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <label for="tag-name" class="text-sm font-medium">Name</label>
                    <input id="tag-name" type="text" wire:model="name" maxlength="60" required autofocus autocomplete="off" placeholder="e.g. Groceries" @class(['auth-input', 'auth-input-invalid' => $errors->has('name')]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="tag-name-error" @enderror>
                    @error('name')
                        <p id="tag-name-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p>
                    @enderror
                </div>

                <fieldset class="flex min-w-0 flex-col gap-3">
                    <legend class="mb-3 text-sm font-medium">Color</legend>
                    <div class="grid grid-cols-3 gap-2 min-[390px]:grid-cols-6">
                        @foreach (['Lavender' => '#bbb8ff', 'Blue' => '#a8c7fa', 'Mint' => '#a5d6c0', 'Peach' => '#f1c48e', 'Rose' => '#e9adbd', 'Lilac' => '#d3b5e5'] as $colorName => $colorValue)
                            <button type="button" class="tag-swatch" style="--tag-color: {{ $colorValue }}" aria-label="{{ $colorName }}" x-bind:aria-pressed="$wire.color === '{{ $colorValue }}'" x-on:click="$wire.color = '{{ $colorValue }}'" wire:key="color-{{ $colorName }}">
                                <i class="bi bi-check-lg" aria-hidden="true" x-show="$wire.color === '{{ $colorValue }}'"></i>
                            </button>
                        @endforeach
                    </div>
                    <div class="flex items-center gap-4">
                        <input id="tag-color" type="color" wire:model="color" class="tag-color-input" aria-invalid="{{ $errors->has('color') ? 'true' : 'false' }}" @error('color') aria-describedby="tag-color-error" @enderror>
                        <div class="flex flex-col gap-1">
                            <label for="tag-color" class="text-sm font-medium">Custom color</label>
                            <span class="text-sm text-galaxy-muted" x-text="typeof $wire.color === 'string' ? $wire.color.toUpperCase() : ''"></span>
                        </div>
                    </div>
                    @error('color')
                        <p id="tag-color-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p>
                    @enderror
                </fieldset>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="tag-cancel" wire:click="close" wire:loading.attr="disabled">Cancel</button>
                    <button type="submit" class="auth-button" wire:loading.attr="disabled">
                        <span wire:loading.remove wire:target="save">{{ $editingTagId === null ? 'Create tag' : 'Save changes' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</div>
