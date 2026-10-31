<nav aria-label="Main navigation" {{ $attributes->class('bottom-navigation') }} x-data>
    <x-navigation-link route="home" icon="grid-1x2" label="Dashboard" />
    <x-navigation-link route="tags" icon="tags" label="Tags" />

    <div class="flex items-center justify-center">
        <button
            type="button"
            class="navigation-add"
            aria-label="Add transaction"
            aria-haspopup="dialog"
            aria-controls="add-transaction-modal"
            x-on:click="$dispatch('open-transaction-modal')"
        >
            <i class="bi bi-plus-lg text-2xl" aria-hidden="true"></i>
        </button>
    </div>

    <x-navigation-link route="transactions" icon="receipt" label="Transactions" />
    <x-navigation-link route="statistics" icon="bar-chart" label="Statistics" />
</nav>
