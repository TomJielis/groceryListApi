{{-- Bordered block, like the cards in the app --}}
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="margin: 0 0 12px; border-collapse: separate;">
    <tr>
        <td class="em-panel" style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px 16px 8px;">
            {{ $slot }}
        </td>
    </tr>
</table>
