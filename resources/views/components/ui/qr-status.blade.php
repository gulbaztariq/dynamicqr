@props(['qrCode'])

@php
    $icon = match (true) {
        ! $qrCode->is_active => 'pause',
        blank($qrCode->target_url) => 'alert',
        default => 'check',
    };
@endphp

{{-- Status pairs an icon with the word, so state never rests on colour alone. --}}
<x-ui.badge :classes="$qrCode->statusClasses()" :icon="$icon">{{ $qrCode->statusLabel() }}</x-ui.badge>
