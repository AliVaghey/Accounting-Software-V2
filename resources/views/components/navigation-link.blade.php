@props(['route', 'icon', 'label'])

<a
    href="{{ route($route) }}"
    wire:navigate.hover
    @if (request()->routeIs($route, $route.'.*')) aria-current="page" @endif
    {{ $attributes->class('navigation-link') }}
>
    <i class="bi bi-{{ $icon }} text-xl leading-none" aria-hidden="true"></i>
    <span class="text-[11px] font-medium leading-4">{{ $label }}</span>
</a>
