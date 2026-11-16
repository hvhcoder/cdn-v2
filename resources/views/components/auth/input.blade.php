@props(['id', 'name', 'type' => 'text', 'autocomplete' => null, 'placeholder' => null])

<input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}" value="{{ old($name) }}" @if ($autocomplete) autocomplete="{{ $autocomplete }}" @endif @if ($placeholder) placeholder="{{ $placeholder }}" @endif required
    class="block h-10 w-full rounded-lg bg-neutral-100 px-3 text-sm text-neutral-950 outline-1 -outline-offset-1 outline-neutral-200 placeholder:text-neutral-600 hover:outline-neutral-300 focus:outline-2 focus:-outline-offset-2 focus:outline-zinc-950 dark:bg-neutral-900 dark:text-neutral-50 dark:outline-neutral-700 dark:placeholder:text-neutral-400 dark:hover:outline-neutral-600 dark:focus:outline-zinc-50" />
