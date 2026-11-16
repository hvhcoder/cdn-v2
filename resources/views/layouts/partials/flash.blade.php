@if (session('status'))
    <div class="mb-5 flex items-start gap-2.5 rounded-xl bg-emerald-50 p-3.5 text-sm text-emerald-800 ring-1 ring-emerald-600/15 ring-inset">
        <svg class="mt-0.5 size-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 6 9 17l-5-5"/></svg>
        <p>{{ session('status') }}</p>
    </div>
@endif
@if ($errors->any() && ! request()->routeIs('login', 'register'))
    <div class="mb-5 rounded-xl bg-red-50 p-3.5 text-sm text-red-700 ring-1 ring-red-600/15 ring-inset">
        @foreach ($errors->all() as $error)
            <p>{{ $error }}</p>
        @endforeach
    </div>
@endif
