@if ($errors->any())
    <div role="alert" class="flex gap-2.5 rounded-lg bg-red-50 p-3 text-[13px] text-red-700 ring-1 ring-red-600/20 ring-inset dark:bg-red-500/10 dark:text-red-400 dark:ring-red-500/20">
        <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10" /><line x1="12" x2="12" y1="8" y2="12" /><line x1="12" x2="12.01" y1="16" y2="16" /></svg>
        <div class="space-y-1">
            @foreach ($errors->all() as $error)
                <p>{{ $error }}</p>
            @endforeach
        </div>
    </div>
@endif
