@php
    $icon = $icon ?? 'phone';
@endphp
<table cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
    <tr>
        <td style="width: 14px; height: 14px; border: 1px solid #9ca3af; text-align: center; vertical-align: middle; padding: 0;">
            @if($icon === 'phone')
                <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#6b7280" d="M20 15.5c-1.25 0-2.45-.2-3.57-.57a1 1 0 0 0-1.02.24l-2.2 2.2a15.35 15.35 0 0 1-6.59-6.59l2.2-2.21a1 1 0 0 0 .24-1.02A11.36 11.36 0 0 1 8.5 4a1 1 0 0 0-1-1H4a1 1 0 0 0-1 1 17 17 0 0 0 17 17h3a1 1 0 0 0 1-1v-3.5a1 1 0 0 0-1-1z"/>
                </svg>
            @elseif($icon === 'web')
                <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#6b7280" d="M12 2a10 10 0 1 0 0 20 10 10 0 0 0 0-20zm7.93 9h-3.18a15.7 15.7 0 0 0-1.07-4.52A8.03 8.03 0 0 1 19.93 11zM12 4c.95 1.37 1.66 3.1 2 5H10c.34-1.9 1.05-3.63 2-5zM4.07 13h3.18c.18 1.58.48 3.09.92 4.5A8.03 8.03 0 0 1 4.07 13zm3.18-2H4.07a8.03 8.03 0 0 1 4.1-4.52c-.44 1.41-.74 2.92-.92 4.52z"/>
                </svg>
            @else
                <svg xmlns="http://www.w3.org/2000/svg" width="9" height="9" viewBox="0 0 24 24" aria-hidden="true">
                    <path fill="#6b7280" d="M20 4H4c-1.1 0-2 .9-2 2v12c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4-8 5-8-5V6l8 5 8-5v2z"/>
                </svg>
            @endif
        </td>
    </tr>
</table>
