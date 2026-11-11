<?php

use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;

new class extends Component
{
    public string $status = '';

    public function boot(): void
    {
        abort_unless(auth()->check(), 403);
    }

    /** @return Collection<int, Transaction> */
    #[Computed]
    public function transactions(): Collection
    {
        return $this->currentUser()->transactions()->with([
            'contact' => fn ($query) => $query->where('user_id', $this->currentUser()->id),
            'tags' => fn ($query) => $query->where('user_id', $this->currentUser()->id)->orderBy('name')->orderBy('tags.id'),
        ])->latest()->orderByDesc('id')->limit(30)->get();
    }

    #[Computed]
    public function ownerName(): string
    {
        return $this->currentUser()->name;
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
        $this->dispatch('transaction-deleted');
    }

    protected function currentUser(): User
    {
        $user = auth()->user();

        abort_unless($user instanceof User, 403);

        return $user;
    }
};
?>

@placeholder
    <section class="flex flex-col gap-4" aria-labelledby="recent-transactions-heading" aria-busy="true">
        <h2 id="recent-transactions-heading" class="text-lg font-semibold">Recent transactions</h2>
        <div class="tag-empty flex min-h-40 items-center justify-center p-5">
            <p role="status" class="text-sm text-galaxy-muted">Loading recent transactions…</p>
        </div>
    </section>
@endplaceholder

<section class="flex flex-col gap-4" aria-labelledby="recent-transactions-heading" x-data="{ expandedTransactionId: null }">
    <header class="flex items-center justify-between gap-3">
        <h2 id="recent-transactions-heading" class="text-lg font-semibold">Recent transactions</h2>
        <a href="{{ route('transactions') }}" wire:navigate.hover class="auth-link text-sm">View all</a>
    </header>
    <p class="text-sm text-galaxy-muted">{{ $this->transactions->count() }} {{ Str::plural('transaction', $this->transactions->count()) }} shown · Latest 30</p>
    <p role="status" class="text-sm text-galaxy-accent" @if ($status === '') hidden @endif>{{ $status }}</p>

    @forelse ($this->transactions as $transaction)
        <x-transaction-card :$transaction :owner-name="$this->ownerName" prefix="recent" wire:key="recent-transaction-{{ $transaction->id }}" />
    @empty
        <div class="tag-empty flex flex-col gap-3 p-6">
            <p class="text-sm leading-6 text-galaxy-muted">No transactions yet. Record your first expense or income.</p>
            <button type="button" class="auth-link self-start" x-on:click="$dispatch('open-transaction-modal')" aria-haspopup="dialog" aria-controls="add-transaction-modal">Add transaction</button>
        </div>
    @endforelse
</section>
