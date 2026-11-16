@extends('layouts.admin')

@section('title', 'Cài đặt hệ thống')

@php
    $input = 'block h-10 w-full rounded-lg bg-neutral-100 px-3 text-sm outline-1 -outline-offset-1 outline-neutral-200 focus:outline-2 focus:-outline-offset-2 focus:outline-zinc-950';
    $show = fn ($value) => is_float($value) ? rtrim(rtrim(number_format($value, 2, '.', ''), '0'), '.') : $value;
@endphp

@section('content')
<div class="flex flex-wrap items-start justify-between gap-3">
    <div>
        <h1 class="text-2xl font-semibold tracking-tight">Cài đặt hệ thống</h1>
        <p class="mt-1 max-w-2xl text-sm text-neutral-600">
            Giá trị trong <code>.env</code> là mặc định. Ô nào để trống thì dùng mặc định; ô nào có số thì ưu tiên hơn <code>.env</code>.
            Thay đổi có hiệu lực ngay cho các job và video tiếp theo, không cần restart worker.
        </p>
    </div>
    <form method="POST" action="{{ route('admin.settings.reset') }}" onsubmit="return confirm('Khôi phục tất cả về mặc định trong .env?')">
        @csrf @method('DELETE')
        <button class="rounded-lg border border-neutral-200 px-3 py-2 text-sm">Khôi phục tất cả về mặc định</button>
    </form>
</div>

<form method="POST" action="{{ route('admin.settings.update') }}" class="mt-6 space-y-6">
    @csrf @method('PUT')

    @foreach ($groups as $group => $fields)
        <section class="rounded-xl border border-neutral-200 p-5">
            <h2 class="text-base font-semibold">{{ $group }}</h2>
            <div class="mt-4 grid gap-5 sm:grid-cols-2">
                @foreach ($fields as $field)
                    @php $id = 'f_'.$field['name']; $value = old('settings.'.$field['name'], $field['override']); @endphp
                    <div>
                        <label for="{{ $id }}" class="mb-1.5 flex items-center justify-between text-sm font-medium">
                            <span>{{ $field['label'] }}</span>
                            @if ($field['override'] !== null)
                                <span class="rounded-full bg-blue-50 px-2 py-0.5 text-xs font-normal text-blue-700">Đang ghi đè</span>
                            @endif
                        </label>

                        @if ($field['type'] === 'select')
                            <select id="{{ $id }}" name="settings[{{ $field['name'] }}]" class="{{ $input }}">
                                <option value="">Mặc định .env ({{ $field['options'][$field['default']] ?? $field['default'] }})</option>
                                @foreach ($field['options'] as $optionValue => $optionLabel)
                                    <option value="{{ $optionValue }}" @selected($value === $optionValue)>{{ $optionLabel }}</option>
                                @endforeach
                            </select>
                        @else
                            <div class="flex items-center gap-2">
                                <input id="{{ $id }}" name="settings[{{ $field['name'] }}]" type="number" inputmode="decimal"
                                       step="{{ $field['type'] === 'float' ? '0.01' : '1' }}" min="{{ $field['min'] }}" max="{{ $field['max'] }}"
                                       value="{{ $value }}" placeholder="Mặc định: {{ $show($field['default']) }}" class="{{ $input }}">
                                <span class="shrink-0 text-sm text-neutral-600">{{ $field['unit'] }}</span>
                            </div>
                        @endif

                        <p class="mt-1 text-xs text-neutral-600">{{ $field['help'] }}</p>
                        @error('settings.'.$field['name'])
                            <p class="mt-1 text-xs text-red-600">{{ $message }}</p>
                        @enderror
                    </div>
                @endforeach
            </div>
        </section>
    @endforeach

    <button class="rounded-lg bg-neutral-950 px-5 py-2.5 text-sm text-white">Lưu cài đặt</button>
</form>
@endsection
