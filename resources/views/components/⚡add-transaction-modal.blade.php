<?php

use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public bool $isOpen = false;

    #[On('open-transaction-modal')]
    public function open(): void
    {
        abort_unless(auth()->check(), 403);

        $this->isOpen = true;
    }

    public function close(): void
    {
        $this->isOpen = false;
    }
};
?>

<dialog
    id="add-transaction-modal"
    class="transaction-modal"
    aria-label="Add transaction"
    wire:ignore.self
    x-data
    x-effect="$wire.isOpen ? $el.showModal() : $el.close()"
    x-on:cancel.prevent="$wire.close()"
    x-on:close="$wire.isOpen && $wire.close()"
    x-on:click="if ($event.target === $el) $wire.close()"
>
    <div class="min-h-64 p-5" x-on:click.stop>
        <div class="flex justify-end">
            <button type="button" class="modal-close" aria-label="Close add transaction" wire:click="close" autofocus>
                <i class="bi bi-x-lg text-lg" aria-hidden="true"></i>
            </button>
        </div>
    </div>
</dialog>
