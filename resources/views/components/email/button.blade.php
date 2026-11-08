@props(['href', 'variant' => 'primary'])
@php
    $outline = $variant === 'outline';
@endphp
{{-- Full width, so it is easy to tap on a phone. Primary is the main action of a mail, outline the buttons next to it --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: {{ $outline ? '0 0 12px' : '8px 0 24px' }}; border-collapse: separate;">
    <tr>
        <td align="center" @if ($outline) class="em-outline" bgcolor="#ffffff" style="background-color: #ffffff; border: 1px solid #cbd5e1; border-radius: 10px;" @else bgcolor="#5ebd8a" style="background-color: #5ebd8a; border-radius: 10px;" @endif>
            <a href="{{ $href }}" @if ($outline) class="em-text" @endif style="display: block; padding: 15px 16px; font-size: 16px; line-height: 20px; font-weight: 600; color: {{ $outline ? '#475569' : '#ffffff' }}; text-decoration: none; border-radius: 10px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
