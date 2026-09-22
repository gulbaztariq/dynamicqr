@props(['days', 'route' => null, 'params' => []])

@php
    $route = $route ?: url()->current();
    $options = [7 => '7 days', 30 => '30 days', 90 => '90 days', 365 => '12 months'];
    $params = array_filter($params, fn ($v) => $v !== null && $v !== '');
@endphp

{{-- Time-range control, in one row above the charts. --}}
<div class="inline-flex rounded-lg bg-slate-100 p-1" role="group" aria-label="Date range">
    @foreach ($options as $value => $label)
        <a href="{{ $route }}?{{ http_build_query($params + ['range' => $value]) }}"
           @class([
               'rounded-md px-3 py-1.5 text-xs font-semibold transition',
               'bg-white text-slate-900 shadow-sm' => $days === $value,
               'text-slate-600 hover:text-slate-900' => $days !== $value,
           ])
           @if ($days === $value) aria-current="true" @endif>
            {{ $label }}
        </a>
    @endforeach
</div>
