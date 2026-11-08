@props(['variant' => 'body'])
@php
    [$class, $style] = match ($variant) {
        'small' => ['em-text', 'margin: 0 0 8px; font-size: 15px; line-height: 22px; color: #475569;'],
        'muted' => ['em-muted', 'margin: 0 0 12px; font-size: 14px; line-height: 20px; color: #64748b;'],
        default => ['em-text', 'margin: 0 0 16px; font-size: 16px; line-height: 24px; color: #475569;'],
    };
@endphp
<p class="{{ $class }}" style="{{ $style }}">{{ $slot }}</p>
