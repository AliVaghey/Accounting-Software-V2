<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Pagination\Paginator;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    #[Locked]
    public int $visibleCount = 12;

    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** @return Paginator<int, Transaction> */
    #[Computed]
    public function transactions(): Paginator
    {
        return $this->currentUser()->transactions()->with([
            'contact' => fn ($query) => $query->where('user_id', $this->currentUser()->id),
            'tags' => fn ($query) => $query->where('user_id', $this->currentUser()->id)->orderBy('name')->orderBy('tags.id'),
        ])->latest()->orderByDesc('id')->simplePaginate($this->visibleCount, page: 1);
    }

    #[Computed]
    public function ownerName(): string
    {
        return $this->currentUser()->name;
    }

    public function loadMore(): void
    {
        if ($this->transactions->hasMorePages()) {
            $this->visibleCount += 12;
            unset($this->transactions);
        }
    }

    #[On('transaction-saved')]
    public function refreshTransactions(string $message = ''): void
    {
        unset($this->transactions);
        $this->status = $message;
    }

    public function delete(int $transactionId): void
    {
        $this->currentUser()->transactions()->findOrFail($transactionId)->delete();

        unset($this->transactions);
        $this->status = 'Transaction deleted.';
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

<div class="flex flex-col gap-8" x-data="{ expandedTransactionId: null }">
    <header class="flex flex-col gap-5">
        <p class="text-sm text-galaxy-muted">Accounting Software</p>
        <div class="flex items-center justify-between gap-3">
            <h1 class="text-2xl font-semibold tracking-tight">Transactions</h1>
            <button type="button" class="auth-button px-4" x-on:click="$dispatch('open-transaction-modal')" aria-haspopup="dialog" aria-controls="add-transaction-modal">
                <i class="bi bi-plus-lg" aria-hidden="true"></i> Add
            </button>
        </div>
        <p class="text-sm leading-6 text-galaxy-muted">Your money in and out, latest first.</p>
    </header>

    <p role="status" class="text-sm text-galaxy-accent" @if ($status === '') hidden @endif>{{ $status }}</p>

    <section aria-label="Your transactions" class="flex flex-col gap-4">
        <p class="text-sm text-galaxy-muted">{{ $this->transactions->count() }} {{ Str::plural('transaction', $this->transactions->count()) }} shown <span class="text-xs">· Persian dates</span></p>
        @forelse ($this->transactions as $transaction)
            <x-transaction-card :$transaction :owner-name="$this->ownerName" wire:key="transaction-{{ $transaction->id }}" />
        @empty
            <div class="tag-empty flex flex-col gap-3 p-6">
                <h2 class="text-lg font-semibold">Add your first transaction</h2>
                <p class="text-sm leading-6 text-galaxy-muted">Record an expense or income to start your personal ledger.</p>
                <button type="button" class="auth-link self-start" x-on:click="$dispatch('open-transaction-modal')" aria-haspopup="dialog" aria-controls="add-transaction-modal">Add transaction</button>
            </div>
        @endforelse

        @if ($this->transactions->hasMorePages())
            <div wire:key="transactions-more-{{ $visibleCount }}" wire:intersect.once.preserve-scroll="loadMore">
                <button type="button" class="tag-cancel w-full" wire:click="loadMore" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="loadMore">Load more transactions</span>
                    <span wire:loading wire:target="loadMore">Loading transactions…</span>
                </button>
            </div>
        @endif
    </section>
</div>
