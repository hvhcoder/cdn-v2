@extends('layouts.admin')

@php
    $editing = $config->exists;
    $platform = $platforms[$config->platform];
    $input = 'block w-full rounded-lg bg-neutral-100 px-3 text-sm outline-1 -outline-offset-1 outline-neutral-200 focus:outline-2 focus:-outline-offset-2 focus:outline-zinc-950';
@endphp

@section('title', ($editing ? 'Sửa' : 'Thêm').' cấu hình '.$platform['label'])

@section('content')
<h1 class="text-2xl font-semibold tracking-tight">{{ $editing ? 'Sửa cấu hình '.$config->name : 'Thêm cấu hình '.$platform['label'] }}</h1>
<p class="mt-1 text-sm text-neutral-600">{{ $platform['desc'] }}</p>

@unless ($editing)
    <div class="mt-4 flex gap-1 text-sm">
        @foreach ($platforms as $key => $p)
            <a href="{{ route('admin.upload-configs.create', ['platform' => $key]) }}" class="rounded-lg px-3 py-1.5 {{ $config->platform === $key ? 'bg-neutral-950 text-white' : 'border border-neutral-200' }}">{{ $p['label'] }}</a>
        @endforeach
    </div>
@endunless

<form method="POST" action="{{ $editing ? route('admin.upload-configs.update', $config) : route('admin.upload-configs.store') }}"
      class="mt-6 grid max-w-2xl gap-4 rounded-xl border border-neutral-200 p-6">
    @csrf
    @if ($editing) @method('PUT') @else <input type="hidden" name="platform" value="{{ $config->platform }}"> @endif

    <div>
        <label for="name" class="mb-1.5 block text-sm font-medium">Tên gợi nhớ</label>
        <input id="name" name="name" value="{{ old('name', $config->name) }}" required class="{{ $input }} h-10">
    </div>

    @foreach ($platform['fields'] as $key => $field)
        @php
            $secret = $field['secret'] ?? false;
            $value = $secret ? '' : old("config_data.$key", $config->data($key));
            $required = $field['required'] && ! ($editing && $secret);
        @endphp
        <div>
            <label for="f_{{ $key }}" class="mb-1.5 block text-sm font-medium">{{ $field['label'] }}</label>
            @if ($field['type'] === 'textarea')
                <textarea id="f_{{ $key }}" name="config_data[{{ $key }}]" rows="4" @required($required) autocomplete="off"
                          placeholder="{{ $editing && $secret ? 'Để trống để giữ nguyên' : ($field['placeholder'] ?? '') }}" class="{{ $input }} py-2">{{ $value }}</textarea>
            @else
                <input id="f_{{ $key }}" name="config_data[{{ $key }}]" type="{{ $field['type'] }}" value="{{ $value }}" @required($required) autocomplete="off"
                       placeholder="{{ $editing && $secret ? 'Để trống để giữ nguyên' : ($field['placeholder'] ?? '') }}" class="{{ $input }} h-10">
            @endif
        </div>
    @endforeach

    <div class="flex gap-3">
        <button class="rounded-lg bg-neutral-950 px-4 py-2 text-sm text-white">Lưu</button>
        <a href="{{ route('admin.upload-configs.index', ['platform' => $config->platform]) }}" class="rounded-lg border border-neutral-200 px-4 py-2 text-sm">Hủy</a>
    </div>
</form>
@endsection
