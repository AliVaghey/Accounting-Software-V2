@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="theme-color" content="#1b1d35">
        <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96" />
        <link rel="icon" type="image/svg+xml" href="/favicon.svg" />
        <link rel="shortcut icon" href="/favicon.ico" />
        <title>{{ $title }} · Accounting Software</title>
        @fonts
        @PwaHead
        @vite(['resources/css/app.css', 'resources/js/app.js'])
        @livewireStyles
    </head>
    <body class="min-h-dvh bg-galaxy-deep font-sans text-galaxy-ink antialiased">
        <main @class([
            'mx-auto flex min-h-dvh w-full max-w-[440px] flex-col bg-galaxy px-6 py-10 sm:px-8 pb-32',
            'app-with-navigation' => auth()->check(),
        ])>
            {{ $slot }}
        </main>

        @auth
            <x-bottom-navigation />
            <livewire:add-transaction-modal />
        @endauth

        @livewireScripts
        @RegisterServiceWorkerScript
    </body>
</html>
