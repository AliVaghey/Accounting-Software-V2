<?php

use App\Models\Contact;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $isOpen = false;

    #[Locked]
    public ?int $editingTransactionId = null;

    #[Locked]
    public int $contactsVisibleCount = 12;

    #[Locked]
    public int $editorVersion = 0;

    public string $amount = '';

    public string $description = '';

    /** @var list<int|string> */
    public array $tagIds = [];

    public ?string $contactId = null;

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

    /** @return Paginator<int, Contact> */
    #[Computed]
    public function contacts(): Paginator
    {
        return $this->currentUser()->contacts()->orderBy('name')->orderBy('id')
            ->simplePaginate($this->contactsVisibleCount, page: 1);
    }

    #[Computed]
    public function selectedContact(): ?Contact
    {
        return blank($this->contactId) ? null : $this->currentUser()->contacts()->find($this->contactId);
    }

    public function loadMoreContacts(): void
    {
        if ($this->isOpen && $this->contacts->hasMorePages()) {
            $this->contactsVisibleCount += 12;
            unset($this->contacts);
        }
    }

    #[On('open-transaction-modal')]
    public function open(): void
    {
        $this->close();
        $this->isOpen = true;
    }

    #[On('edit-transaction')]
    public function edit(int $transactionId): void
    {
        $transaction = $this->currentUser()->transactions()->with('tags')->findOrFail($transactionId);

        $this->close();
        $this->editingTransactionId = $transaction->id;
        $this->amount = (string) $transaction->amount;
        $this->description = $transaction->description ?? '';
        $this->contactId = $transaction->contact_id === null ? null : (string) $transaction->contact_id;
        $this->tagIds = $transaction->tags->modelKeys();
        $this->isOpen = true;
    }

    public function save(bool $isIncome): void
    {
        abort_unless($this->isOpen, 403);

        $this->description = trim($this->description);
        $this->contactId = $this->contactId === '' ? null : $this->contactId;
        $isEditing = $this->editingTransactionId !== null;

        DB::transaction(function () use ($isIncome): void {
            $validated = $this->validate([
                'amount' => ['required', 'regex:/^[0-9]{1,12}$/', 'integer', 'min:1', 'max:999999999999'],
                'description' => ['nullable', 'string', 'max:255'],
                'contactId' => ['nullable', 'integer', Rule::exists(Contact::class, 'id')->where('user_id', $this->currentUser()->id)],
                'tagIds' => ['array'],
                'tagIds.*' => ['required', 'integer', 'distinct', Rule::exists(Tag::class, 'id')->where('user_id', $this->currentUser()->id)],
            ], [
                'amount.regex' => 'Enter a whole amount of up to 12 digits.',
                'contactId.exists' => 'Choose one of your contacts.',
                'tagIds.*.exists' => 'Choose only your own tags.',
                'tagIds.*.distinct' => 'Each tag can be selected only once.',
            ]);

            $transaction = $this->editingTransactionId === null
                ? $this->currentUser()->transactions()->make()
                : $this->currentUser()->transactions()->findOrFail($this->editingTransactionId);

            $transaction->fill([
                'amount' => $validated['amount'],
                'description' => $validated['description'] === '' ? null : $validated['description'],
                'contact_id' => $validated['contactId'],
                'is_income' => $isIncome,
            ])->save();
            $transaction->tags()->sync($validated['tagIds']);
        });

        $this->close();
        $this->dispatch('transaction-saved', message: $isEditing ? 'Transaction updated.' : 'Transaction created.');
    }

    public function close(): void
    {
        $this->reset('isOpen', 'editingTransactionId', 'amount', 'description', 'tagIds', 'contactId', 'contactsVisibleCount');
        $this->resetValidation();
        $this->editorVersion++;
        unset($this->tags, $this->contacts, $this->selectedContact);
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<dialog
    id="add-transaction-modal"
    class="transaction-modal transaction-editor"
    aria-label="Add transaction"
    aria-labelledby="transaction-editor-title"
    wire:ignore.self
    x-data
    x-effect="$wire.isOpen ? (!$el.open && $el.showModal()) : ($el.open && $el.close())"
    x-on:cancel.prevent="$wire.isOpen = false; $wire.close()"
    x-on:close="if (!$el.open && $wire.isOpen) { $wire.isOpen = false; $wire.close() }"
    x-on:click="if ($event.target === $el) { $wire.isOpen = false; $wire.close() }"
>
    @if ($isOpen)
        <div class="transaction-editor-frame" wire:key="transaction-editor-{{ $editorVersion }}" x-on:click.stop>
            <header class="flex shrink-0 items-center justify-between gap-3 px-5 py-4">
                <h2 id="transaction-editor-title" class="text-xl font-semibold">{{ $editingTransactionId === null ? 'Add transaction' : 'Edit transaction' }}</h2>
                <button type="button" class="modal-close shrink-0" aria-label="Close transaction editor" wire:click="close">
                    <i class="bi bi-x-lg" aria-hidden="true"></i>
                </button>
            </header>

            <form id="transaction-form" wire:submit="save(false)" class="transaction-editor-body flex flex-col gap-6 px-5 py-2">
                <div class="flex flex-col gap-2" x-data="{
                    format(value) { return String(value ?? '').replace(/\B(?=(\d{3})+(?!\d))/g, ',') },
                    update(event) {
                        const field = event.target;
                        const digitsBeforeCaret = field.value.slice(0, field.selectionStart).replace(/[^0-9]/g, '').length;
                        const raw = field.value.replace(/[^0-9]/g, '');
                        field.value = this.format(raw);
                        let caret = 0, digits = 0;
                        while (caret < field.value.length && digits < digitsBeforeCaret) {
                            if (/[0-9]/.test(field.value[caret])) digits++;
                            caret++;
                        }
                        field.setSelectionRange(caret, caret);
                        $wire.amount = raw;
                    }
                }">
                    <label for="transaction-amount" class="text-sm font-medium">Amount</label>
                    <input id="transaction-amount" type="text" inputmode="numeric" autocomplete="off" autofocus required
                        class="auth-input text-2xl font-semibold tabular-nums" x-bind:value="format($wire.amount)" x-on:input="update($event)"
                        aria-invalid="{{ $errors->has('amount') ? 'true' : 'false' }}" aria-describedby="transaction-amount-help{{ $errors->has('amount') ? ' transaction-amount-error' : '' }}">
                    <p id="transaction-amount-help" class="text-xs text-galaxy-muted">Whole amounts. Up to 12 digits.</p>
                    @error('amount') <p id="transaction-amount-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p> @enderror
                </div>

                <div class="flex flex-col gap-2">
                    <label for="transaction-description" class="text-sm font-medium">Description <span class="text-galaxy-muted">(optional)</span></label>
                    <textarea id="transaction-description" wire:model="description" maxlength="255" rows="2" class="auth-input resize-y py-3" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}" @error('description') aria-describedby="transaction-description-error" @enderror></textarea>
                    @error('description') <p id="transaction-description-error" role="alert" class="text-sm text-galaxy-error">{{ $message }}</p> @enderror
                </div>

                <fieldset class="flex min-w-0 flex-col gap-3">
                    <legend class="mb-3 text-sm font-medium">Tags <span class="text-galaxy-muted">(optional)</span></legend>
                    <div class="flex flex-wrap gap-2">
                        @forelse ($this->tags as $tag)
                            <label class="transaction-tag-option" style="--tag-color: {{ $tag->color }}; --tag-ink: {{ $tag->foregroundColor() }}" wire:key="transaction-tag-{{ $tag->id }}">
                                <input type="checkbox" wire:model="tagIds" value="{{ $tag->id }}" class="size-4 shrink-0 accent-black">
                                <span class="wrap-anywhere">{{ $tag->name }}</span>
                            </label>
                        @empty
                            <p class="text-sm text-galaxy-muted">No tags yet. You can add them on the Tags page.</p>
                        @endforelse
                    </div>
                    @error('tagIds') <p role="alert" class="text-sm text-galaxy-error">{{ $message }}</p> @enderror
                    @error('tagIds.*') <p role="alert" class="text-sm text-galaxy-error">{{ $message }}</p> @enderror
                </fieldset>

                <fieldset class="flex min-w-0 flex-col gap-3 pb-3">
                    <legend class="mb-3 text-sm font-medium">Contact <span class="text-galaxy-muted">(optional)</span></legend>
                    <p class="text-sm text-galaxy-muted" aria-live="polite">Selected: {{ $this->selectedContact?->name ?? 'No contact' }}</p>
                    <div class="transaction-contact-list" tabindex="0" aria-label="Choose a contact">
                        <label class="transaction-contact-option">
                            <input type="radio" name="transaction-contact" wire:model.live="contactId" value="" class="size-4 shrink-0 accent-galaxy-accent">
                            <span>No contact</span>
                        </label>
                        @forelse ($this->contacts as $contact)
                            <label class="transaction-contact-option" wire:key="transaction-contact-{{ $contact->id }}">
                                <input type="radio" name="transaction-contact" wire:model.live="contactId" value="{{ $contact->id }}" class="size-4 shrink-0 accent-galaxy-accent">
                                <span class="wrap-anywhere">{{ $contact->name }}</span>
                            </label>
                        @empty
                            <p class="px-3 py-2 text-sm text-galaxy-muted">No contacts yet.</p>
                        @endforelse
                        @if ($this->contacts->hasMorePages())
                            <div wire:key="transaction-contacts-more-{{ $contactsVisibleCount }}" wire:intersect.once.preserve-scroll="loadMoreContacts">
                                <button type="button" class="tag-cancel w-full" wire:click="loadMoreContacts" wire:loading.attr="disabled">
                                    <span wire:loading.remove wire:target="loadMoreContacts">Load more contacts</span>
                                    <span wire:loading wire:target="loadMoreContacts">Loading contacts…</span>
                                </button>
                            </div>
                        @endif
                    </div>
                    @error('contactId') <p role="alert" class="text-sm text-galaxy-error">{{ $message }}</p> @enderror
                </fieldset>
            </form>

            <footer class="transaction-editor-footer grid shrink-0 grid-cols-2 gap-3 p-5">
                <button type="submit" form="transaction-form" class="transaction-save transaction-save-expense" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-up-right" aria-hidden="true"></i> Save expense
                </button>
                <button type="button" class="transaction-save transaction-save-income" wire:click="save(true)" wire:loading.attr="disabled">
                    <i class="bi bi-arrow-down-left" aria-hidden="true"></i> Save income
                </button>
                <p class="col-span-2 text-center text-xs text-galaxy-muted" wire:loading wire:target="save">Saving transaction…</p>
            </footer>
        </div>
    @endif
</dialog>
