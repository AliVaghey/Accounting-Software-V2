@props(['transaction', 'ownerName', 'prefix' => 'transaction'])

<article {{ $attributes->class(['min-w-0']) }}>
    <button type="button" @class(['transaction-card', 'transaction-income' => $transaction->is_income, 'transaction-expense' => ! $transaction->is_income])
        aria-label="Options for {{ $transaction->is_income ? 'income' : 'expense' }} {{ number_format($transaction->amount) }}"
        aria-controls="{{ $prefix }}-actions-{{ $transaction->id }}" aria-expanded="false"
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
                <span class="transaction-tag" style="--tag-color: {{ $tag->color }}; --tag-ink: {{ $tag->foregroundColor() }}" wire:key="{{ $prefix }}-{{ $transaction->id }}-tag-{{ $tag->id }}">{{ $tag->name }}</span>
            @empty
                <span class="text-xs text-galaxy-muted">No tags</span>
            @endforelse
        </span>
        <span class="text-xs text-galaxy-muted wrap-anywhere">Owner: {{ $ownerName }}</span>
    </button>
    <div id="{{ $prefix }}-actions-{{ $transaction->id }}" class="mt-3 grid grid-cols-2 gap-3" x-cloak x-show="expandedTransactionId === {{ $transaction->id }}">
        <button type="button" class="contact-action gap-2 text-sm" x-on:click="$dispatch('edit-transaction', { transactionId: {{ $transaction->id }} })" aria-haspopup="dialog" aria-controls="add-transaction-modal">
            <i class="bi bi-pencil" aria-hidden="true"></i> Edit
        </button>
        <button type="button" class="contact-action gap-2 text-sm" wire:click="delete({{ $transaction->id }})" wire:confirm="Delete this {{ $transaction->is_income ? 'income' : 'expense' }} of {{ number_format($transaction->amount) }}? This cannot be undone." wire:loading.attr="disabled">
            <i class="bi bi-trash3" aria-hidden="true"></i> Delete
        </button>
    </div>
</article>
