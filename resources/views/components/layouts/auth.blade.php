@props(['title', 'description'])

<x-layouts.app :title="$title">
    <header class="flex items-center gap-3 text-sm font-medium">
        <i class="bi bi-journal-text text-xl text-galaxy-accent" aria-hidden="true"></i>
        <span>Accounting Software</span>
    </header>

    <div class="flex flex-1 flex-col justify-center gap-8 py-10">
        <div class="flex flex-col gap-2">
            <h1 class="text-[28px] font-semibold tracking-tight">{{ $title }}</h1>
            <p class="text-sm leading-6 text-galaxy-muted">{{ $description }}</p>
        </div>

        {{ $slot }}
    </div>

    <footer class="flex items-center justify-center gap-2 text-xs text-galaxy-muted">
        <i class="bi bi-wallet2" aria-hidden="true"></i>
        <span>Your everyday finances, in one place.</span>
    </footer>
</x-layouts.app>
