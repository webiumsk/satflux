<table cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
    <tr>
        <td style="padding-right: 4px; vertical-align: middle;">
            @include('pdf.partials.business-invoice-footer-icon', ['icon' => $icon])
        </td>
        <td style="vertical-align: middle; font-size: 9px; font-family: DejaVu Sans, sans-serif;">
            <a href="{{ $href }}" style="color: #2563eb; text-decoration: underline;">{{ $label }}</a>
        </td>
    </tr>
</table>
