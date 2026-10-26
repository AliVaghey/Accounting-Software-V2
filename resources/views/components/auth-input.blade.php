@props(['name', 'label', 'type' => 'text', 'hint' => null])

<div class="flex flex-col gap-2">
    <label for="{{ $name }}" class="text-sm font-medium">{{ $label }}</label>
    <input
        id="{{ $name }}"
        name="{{ $name }}"
        type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name) }}" @endif
        aria-invalid="{{ $errors->has($name) ? 'true' : 'false' }}"
        @if ($errors->has($name)) aria-describedby="{{ $name }}-error"
        @elseif ($hint) aria-describedby="{{ $name }}-hint" @endif
        {{ $attributes->class(['auth-input', 'auth-input-invalid' => $errors->has($name)]) }}
    >
    @error($name)
        <p id="{{ $name }}-error" role="alert" class="text-sm leading-5 text-galaxy-error">{{ $message }}</p>
    @else
        @if ($hint)
            <p id="{{ $name }}-hint" class="text-xs leading-5 text-galaxy-muted">{{ $hint }}</p>
        @endif
    @enderror
</div>
