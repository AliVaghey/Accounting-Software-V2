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
    <div
        class="relative"
        x-data="{ isOpen: false }"
        x-on:click.outside="isOpen = false"
        x-on:focusout="if (! $el.contains($event.relatedTarget)) isOpen = false"
        x-on:keydown.escape.prevent.stop="if (isOpen) { isOpen = false; $refs.moreButton.focus() }"
    >
        <button
            type="button"
            @class(['navigation-link w-full cursor-pointer', 'navigation-more-active' => request()->routeIs('statistics', 'contacts')])
            id="more-options-button"
            aria-controls="more-options"
            aria-expanded="false"
            x-bind:aria-expanded="isOpen"
            x-ref="moreButton"
            x-on:click="isOpen = ! isOpen"
        >
            <i class="bi bi-three-dots text-xl leading-none" aria-hidden="true"></i>
            <span class="text-[11px] font-medium leading-4">More</span>
        </button>

        <div
            id="more-options"
            class="navigation-more-menu"
            aria-labelledby="more-options-button"
            x-cloak
            x-show="isOpen"
        >
            <x-navigation-link route="statistics" icon="bar-chart" label="Statistics" />
            <x-navigation-link route="contacts" icon="people" label="Contacts" />
        </div>
    </div>
</nav>
