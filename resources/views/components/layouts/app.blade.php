@props(['title'])

<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="scheme-dark">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1b1d35">
        <title>{{ $title }} · Accounting Software</title>
        @fonts
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-dvh bg-galaxy-deep font-sans text-galaxy-ink antialiased">
        <main class="mx-auto flex min-h-dvh w-full max-w-[440px] flex-col bg-galaxy px-6 py-10 sm:px-8">
            {{ $slot }}
        </main>
    </body>
</html>
