<?php

use App\Models\Contact;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

new class extends Component
{
    use WithFileUploads;

    public bool $isOpen = false;

    #[Locked]
    public ?int $editingContactId = null;

    #[Locked]
    public int $visibleCount = 12;

    #[Locked]
    public int $editorVersion = 0;

    public string $name = '';

    public ?TemporaryUploadedFile $photo = null;

    public bool $removePhoto = false;

    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** @return Paginator<int, Contact> */
    #[Computed]
    public function contacts(): Paginator
    {
        return $this->currentUser()->contacts()->orderBy('name')->orderBy('id')
            ->simplePaginate($this->visibleCount, page: 1);
    }

    #[Computed]
    public function currentPhotoUrl(): ?string
    {
        if ($this->editingContactId === null || $this->removePhoto) {
            return null;
        }

        $contact = $this->currentUser()->contacts()->findOrFail($this->editingContactId);

        return $contact->profile_photo === null ? null : route('contacts.photo', $contact);
    }

    public function loadMore(): void
    {
        if ($this->contacts->hasMorePages()) {
            $this->visibleCount += 12;
            unset($this->contacts);
        }
    }

    public function create(): void
    {
        $this->close();
        $this->reset('status');
        $this->isOpen = true;
    }

    public function edit(int $contactId): void
    {
        $contact = $this->currentUser()->contacts()->findOrFail($contactId);

        $this->close();
        $this->reset('status');
        $this->editingContactId = $contact->id;
        $this->name = $contact->name;
        $this->isOpen = true;
    }

    public function updatedPhoto(): void
    {
        $this->validateOnly('photo');
    }

    public function save(): void
    {
        $this->name = trim($this->name);
        $validated = $this->validate();
        $isEditing = $this->editingContactId !== null;
        $contact = $isEditing
            ? $this->currentUser()->contacts()->findOrFail($this->editingContactId)
            : $this->currentUser()->contacts()->make();
        $previousPhoto = $contact->profile_photo;
        $profilePhoto = $this->removePhoto ? null : $previousPhoto;

        if ($this->photo !== null) {
            $profilePhoto = $this->photo->store('contacts/'.$this->currentUser()->id, 'local');

            if ($profilePhoto === false) {
                throw ValidationException::withMessages(['photo' => 'The photo could not be saved. Please try again.']);
            }
        }

        try {
            $contact->fill(['name' => $validated['name'], 'profile_photo' => $profilePhoto])->save();
        } catch (Throwable $exception) {
            if ($profilePhoto !== null && $profilePhoto !== $previousPhoto) {
                Storage::disk('local')->delete($profilePhoto);
            }

            throw $exception;
        }

        if ($previousPhoto !== null && $previousPhoto !== $profilePhoto) {
            Storage::disk('local')->delete($previousPhoto);
        }

        unset($this->contacts);
        $this->close();
        $this->status = $isEditing ? 'Contact updated.' : 'Contact created.';
    }

    public function delete(int $contactId): void
    {
        $contact = $this->currentUser()->contacts()->findOrFail($contactId);
        $contact->delete();

        if ($contact->profile_photo !== null) {
            Storage::disk('local')->delete($contact->profile_photo);
        }

        if ($this->editingContactId === $contactId) {
            $this->close();
        }

        unset($this->contacts);
        $this->status = 'Contact deleted.';
    }

    public function close(): void
    {
        $this->reset('isOpen', 'editingContactId', 'name', 'photo', 'removePhoto');
        $this->resetValidation();
        $this->editorVersion++;
        unset($this->currentPhotoUrl);
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'photo' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048'],
        ];
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="flex flex-col gap-8" x-data="{ expandedContactId: null }">
    <header class="flex flex-col gap-5">
        <p class="text-sm text-galaxy-muted">Accounting Software</p>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Contacts</h1>
            <button type="button" class="auth-button" wire:click="create" wire:loading.attr="disabled" aria-haspopup="dialog" aria-controls="contact-editor">
                <i class="bi bi-plus-lg" aria-hidden="true"></i>
                Add contact
            </button>
        </div>
        <p class="text-sm leading-6 text-galaxy-muted">The people behind your transactions.</p>
    </header>

    <p role="status" class="text-sm text-galaxy-accent" @if ($status === '') hidden @endif>{{ $status }}</p>

    <section aria-label="Your contacts" class="flex flex-col gap-4">
        <p class="text-sm text-galaxy-muted">{{ $this->contacts->count() }} {{ Str::plural('contact', $this->contacts->count()) }} shown</p>

        <div class="grid grid-cols-2 items-start gap-4">
            @forelse ($this->contacts as $contact)
                <article class="min-w-0" wire:key="contact-{{ $contact->id }}">
                    <button
                        type="button"
                        class="contact-card"
                        aria-label="Options for {{ $contact->name }}"
                        aria-controls="contact-actions-{{ $contact->id }}"
                        aria-expanded="false"
                        x-bind:aria-expanded="typeof expandedContactId === 'number' && expandedContactId === {{ $contact->id }}"
                        x-on:click="expandedContactId = expandedContactId === {{ $contact->id }} ? null : {{ $contact->id }}"
                    >
                        @if ($contact->profile_photo !== null)
                            <img class="contact-avatar" src="{{ route('contacts.photo', $contact) }}" alt="" width="72" height="72" loading="lazy">
                        @else
                            <span class="contact-avatar" aria-hidden="true">{{ Str::upper(Str::substr($contact->name, 0, 1)) }}</span>
                        @endif
                        <span class="w-full text-center text-base font-medium wrap-anywhere">{{ $contact->name }}</span>
                    </button>
                    <div id="contact-actions-{{ $contact->id }}" class="mt-3 grid grid-cols-3 gap-1" x-cloak x-show="typeof expandedContactId === 'number' && expandedContactId === {{ $contact->id }}">
                        <button type="button" class="contact-action" aria-label="Edit {{ $contact->name }}" title="Edit contact" wire:click="edit({{ $contact->id }})" wire:loading.attr="disabled">
                            <i class="bi bi-pencil" aria-hidden="true"></i>
                        </button>
                        <button type="button" class="contact-action" aria-label="Delete {{ $contact->name }}" title="Delete contact" wire:click="delete({{ $contact->id }})" wire:confirm="Delete &quot;{{ $contact->name }}&quot;? This cannot be undone." wire:loading.attr="disabled">
                            <i class="bi bi-trash3" aria-hidden="true"></i>
                        </button>
                        <a class="contact-action" href="{{ route('contacts.show', $contact) }}" wire:navigate aria-label="View profile for {{ $contact->name }}" title="View profile">
                            <i class="bi bi-person" aria-hidden="true"></i>
                        </a>
                    </div>
                </article>
            @empty
                <div class="tag-empty col-span-2 flex flex-col gap-3 p-6">
                    <h2 class="text-lg font-semibold">Add your first contact</h2>
                    <p class="text-sm leading-6 text-galaxy-muted">Keep the people you pay and receive from in one place.</p>
                </div>
            @endforelse
        </div>

        @if ($this->contacts->hasMorePages())
            <div wire:key="contacts-more-{{ $visibleCount }}" wire:intersect.once.preserve-scroll="loadMore">
                <button type="button" class="tag-cancel w-full" wire:click="loadMore" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="loadMore">Load more contacts</span>
                    <span wire:loading wire:target="loadMore" role="status">Loading contacts…</span>
                </button>
            </div>
        @endif
    </section>

    <dialog
        id="contact-editor"
        class="transaction-modal"
        aria-labelledby="contact-editor-title"
        wire:ignore.self
        x-data="{ uploading: false }"
        x-effect="$wire.isOpen ? (!$el.open && $el.showModal()) : ($el.open && $el.close())"
        x-on:cancel.prevent="$wire.isOpen = false; $wire.close()"
        x-on:close="if (!$el.open && $wire.isOpen) { $wire.isOpen = false; $wire.close() }"
        x-on:click="if ($event.target === $el) { $wire.isOpen = false; $wire.close() }"
    >
        <div class="p-6" x-on:click.stop>
            <div class="mb-6 flex items-center justify-between gap-4">
                <h2 id="contact-editor-title" class="text-xl font-semibold">{{ $editingContactId === null ? 'Add contact' : 'Edit contact' }}</h2>
                <button type="button" class="modal-close" aria-label="Close contact editor" wire:click="close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </div>

            <form wire:submit="save" class="flex flex-col gap-6">
                <div class="flex flex-col gap-2">
                    <label for="contact-name" class="text-sm font-medium">Name</label>
                    <input id="contact-name" type="text" wire:model="name" maxlength="100" required autofocus autocomplete="off" @class(['auth-input', 'auth-input-invalid' => $errors->has('name')]) aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}" @error('name') aria-describedby="contact-name-error" @enderror>
                    @error('name')
                        <p id="contact-name-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="flex flex-col gap-3" wire:key="contact-photo-field-{{ $editorVersion }}" x-data x-init="uploading = false">
                    <label for="contact-photo" class="text-sm font-medium">Profile photo <span class="text-galaxy-muted">(optional)</span></label>
                    @if ($photo !== null && ! $errors->has('photo'))
                        <img class="contact-avatar" src="{{ $photo->temporaryUrl() }}" alt="Selected profile photo" width="72" height="72">
                    @elseif ($this->currentPhotoUrl !== null)
                        <img class="contact-avatar" src="{{ $this->currentPhotoUrl }}" alt="Current profile photo" width="72" height="72">
                    @endif
                    <input id="contact-photo" type="file" wire:model="photo" accept="image/jpeg,image/png,image/webp" class="contact-photo-input" aria-invalid="{{ $errors->has('photo') ? 'true' : 'false' }}" aria-describedby="contact-photo-help{{ $errors->has('photo') ? ' contact-photo-error' : '' }}"
                        x-on:livewire-upload-start="uploading = true"
                        x-on:livewire-upload-finish="uploading = false"
                        x-on:livewire-upload-error="uploading = false"
                        x-on:livewire-upload-cancel="uploading = false">
                    <p id="contact-photo-help" class="text-sm text-galaxy-muted">JPG, PNG or WebP. Up to 2 MB.</p>
                    <p class="text-sm text-galaxy-muted" role="status" x-cloak x-show="typeof uploading === 'boolean' && uploading">Uploading photo…</p>
                    @if ($this->currentPhotoUrl !== null && $photo === null)
                        <label class="flex min-h-11 items-center gap-3 text-sm">
                            <input type="checkbox" wire:model="removePhoto" class="size-5 accent-galaxy-accent">
                            Remove current photo
                        </label>
                    @endif
                    @error('photo')
                        <p id="contact-photo-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p>
                    @enderror
                </div>

                <div class="grid grid-cols-2 gap-3">
                    <button type="button" class="tag-cancel" wire:click="close" wire:loading.attr="disabled">Cancel</button>
                    <button type="submit" class="auth-button" wire:loading.attr="disabled" x-bind:disabled="typeof uploading === 'boolean' && uploading">
                        <span wire:loading.remove wire:target="save">{{ $editingContactId === null ? 'Create contact' : 'Save changes' }}</span>
                        <span wire:loading wire:target="save">Saving…</span>
                    </button>
                </div>
            </form>
        </div>
    </dialog>
</div>
