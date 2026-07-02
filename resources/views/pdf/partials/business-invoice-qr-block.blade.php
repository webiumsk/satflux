@if(! $isQuote && ($bankQr || $btcPayQr))
    <table cellpadding="0" cellspacing="0" style="margin-top: 12px;">
        <tr>
            @if($bankQr)
                <td style="vertical-align: top; width: 132px;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 132px; height: 132px; border: 1.5px solid #00a0e3; text-align: center; vertical-align: middle; padding: 0;">
                                <img src="{{ $bankQr }}" alt="" width="110" height="110" style="width: 110px; height: 110px;">
                            </td>
                        </tr>
                    </table>
                    <div style="width: 132px; margin-top: 5px; text-align: center; font-size: 9px; color: #374151; line-height: 1.3;">
                        <span style="font-weight: bold; font-size: 10px; color: #00a0e3;">PAY</span> by square
                    </div>
                </td>
            @endif

            @if($bankQr && $btcPayQr)
                <td style="width: 36px; font-size: 1px; line-height: 1px;">&nbsp;</td>
            @endif

            @if($btcPayQr)
                <td style="vertical-align: top; width: 132px;">
                    <table cellpadding="0" cellspacing="0">
                        <tr>
                            <td style="width: 132px; height: 132px; border: 1.5px solid #f7931a; text-align: center; vertical-align: middle; padding: 0;">
                                <img src="{{ $btcPayQr }}" alt="" width="110" height="110" style="width: 110px; height: 110px;">
                            </td>
                        </tr>
                    </table>
                    @if(!empty($btcPayUrl))
                        <a href="{{ $btcPayUrl }}" style="display: block; width: 132px; margin-top: 5px; text-align: center; font-size: 9px; color: #374151; line-height: 1.3; text-decoration: underline;">
                            <span style="font-weight: bold; font-size: 10px; color: #f7931a;">Bitcoin</span> / Lightning <span style="font-size: 8px; color: #6b7280;">&#8599;</span>
                        </a>
                    @else
                        <div style="width: 132px; margin-top: 5px; text-align: center; font-size: 9px; color: #374151; line-height: 1.3;">
                            <span style="font-weight: bold; font-size: 10px; color: #f7931a;">Bitcoin</span> / Lightning
                        </div>
                    @endif
                </td>
            @endif
        </tr>
    </table>
@endif
