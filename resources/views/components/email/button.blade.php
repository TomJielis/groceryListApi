@props(['href'])
{{-- Full width, so it is easy to tap on a phone --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 8px 0 24px;">
    <tr>
        <td align="center" bgcolor="#5ebd8a" style="background-color: #5ebd8a; border-radius: 10px;">
            <a href="{{ $href }}" style="display: block; padding: 15px 16px; font-size: 16px; line-height: 20px; font-weight: 600; color: #ffffff; text-decoration: none; border-radius: 10px;">{{ $slot }}</a>
        </td>
    </tr>
</table>
