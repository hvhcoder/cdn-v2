@props(['id', 'name', 'autocomplete' => 'current-password', 'toggle' => true])

<div class="relative">
    <input id="{{ $id }}" name="{{ $name }}" type="password" autocomplete="{{ $autocomplete }}" required
        class="block h-10 w-full rounded-lg bg-neutral-100 {{ $toggle ? 'pr-11' : 'pr-3' }} pl-3 text-sm text-neutral-950 outline-1 -outline-offset-1 outline-neutral-200 hover:outline-neutral-300 focus:outline-2 focus:-outline-offset-2 focus:outline-zinc-950 dark:bg-neutral-900 dark:text-neutral-50 dark:outline-neutral-700 dark:hover:outline-neutral-600 dark:focus:outline-zinc-50" />
    @if ($toggle)
        <button type="button" data-toggle-password aria-label="Hiện mật khẩu" aria-pressed="false" aria-controls="{{ $id }}" class="group absolute inset-y-0 right-0 flex w-10 cursor-pointer items-center justify-center rounded-r-lg text-neutral-600 transition-colors duration-150 hover:text-neutral-950 focus-visible:outline-2 focus-visible:-outline-offset-2 focus-visible:outline-zinc-950 dark:text-neutral-400 dark:hover:text-neutral-50 dark:focus-visible:outline-zinc-50">
            <svg class="size-4 group-aria-pressed:hidden" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M2.062 12.348a1 1 0 0 1 0-.696 10.75 10.75 0 0 1 19.876 0 1 1 0 0 1 0 .696 10.75 10.75 0 0 1-19.876 0" /><circle cx="12" cy="12" r="3" /></svg>
            <svg class="hidden size-4 group-aria-pressed:block" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M10.733 5.076a10.744 10.744 0 0 1 11.205 6.575 1 1 0 0 1 0 .696 10.747 10.747 0 0 1-1.444 2.49" /><path d="M14.084 14.158a3 3 0 0 1-4.242-4.242" /><path d="M17.479 17.499a10.75 10.75 0 0 1-15.417-5.151 1 1 0 0 1 0-.696 10.75 10.75 0 0 1 4.446-5.143" /><path d="m2 2 20 20" /></svg>
        </button>
    @endif
</div>
