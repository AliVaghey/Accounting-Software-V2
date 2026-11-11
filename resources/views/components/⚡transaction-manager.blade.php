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
            <article wire:key="transaction-{{ $transaction->id }}" class="min-w-0">
                <button type="button" @class(['transaction-card', 'transaction-income' => $transaction->is_income, 'transaction-expense' => ! $transaction->is_income])
                    aria-label="Options for {{ $transaction->is_income ? 'income' : 'expense' }} {{ number_format($transaction->amount) }}"
                    aria-controls="transaction-actions-{{ $transaction->id }}" aria-expanded="false"
                    x-bind:aria-expanded="expandedTransactionId === {{ $transaction->id }}"
                    x-on:click="expandedTransactionId = expandedTransactionId === {{ $transaction->id }} ? null : {{ $transaction->id }}">
                    <span class="flex flex-wrap items-center justify-between gap-2">
                        <span class="flex items-center gap-2 text-sm font-medium">
                            <i class="bi {{ $transaction->is_income ? 'bi-arrow-down-left' : 'bi-arrow-up-right' }}" aria-hidden="true"></i>
                            {{ $transaction->is_income ? 'Income' : 'Expense' }}
                        </span>
                        <span class="transaction-amount min-w-0 text-right text-2xl font-semibold tabular-nums wrap-anywhere">{{ $transaction->is_income ? '+' : '−' }}{{ number_format($transaction->amount) }}</span>
                    </span>
                    <time datetime="{{ $transaction->created_at?->toIso8601String() }}" class="text-right text-xs text-galaxy-muted tabular-nums">{{ $transaction->persianCreatedAt() }}</time>
                    <span class="whitespace-pre-line text-sm text-galaxy-ink wrap-anywhere">{{ $transaction->description ?? 'No description' }}</span>
                    <span class="text-sm text-galaxy-muted wrap-anywhere">Contact: {{ $transaction->contact?->name ?? 'No contact' }}</span>
                    <span class="flex flex-wrap gap-2">
                        @forelse ($transaction->tags as $tag)
                            <span class="transaction-tag" style="--tag-color: {{ $tag->color }}; --tag-ink: {{ $tag->foregroundColor() }}" wire:key="transaction-{{ $transaction->id }}-tag-{{ $tag->id }}">{{ $tag->name }}</span>
                        @empty
                            <span class="text-xs text-galaxy-muted">No tags</span>
                        @endforelse
                    </span>
                    <span class="text-xs text-galaxy-muted wrap-anywhere">Owner: {{ $this->ownerName }}</span>
                </button>
                <div id="transaction-actions-{{ $transaction->id }}" class="mt-3 grid grid-cols-2 gap-3" x-cloak x-show="expandedTransactionId === {{ $transaction->id }}">
                    <button type="button" class="contact-action gap-2 text-sm" x-on:click="$dispatch('edit-transaction', { transactionId: {{ $transaction->id }} })" aria-haspopup="dialog" aria-controls="add-transaction-modal">
                        <i class="bi bi-pencil" aria-hidden="true"></i> Edit
                    </button>
                    <button type="button" class="contact-action gap-2 text-sm" wire:click="delete({{ $transaction->id }})" wire:confirm="Delete this {{ $transaction->is_income ? 'income' : 'expense' }} of {{ number_format($transaction->amount) }}? This cannot be undone." wire:loading.attr="disabled">
                        <i class="bi bi-trash3" aria-hidden="true"></i> Delete
                    </button>
                </div>
            </article>
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
