<table style="width:100%; border-collapse:collapse; margin-top:6px;">
    <tr>
        <td style="width:60%;"></td>
        <td style="width:40%;">
            <table style="width:100%; border-collapse:collapse;">
                <tr>
                    <td style="padding:3px 6px; border:0.5px solid #ccc;">Total Importe Retenido:</td>
                    <td style="padding:3px 6px; border:0.5px solid #ccc; text-align:right; font-weight:bold;">
                        S/ {{ number_format($imp_retenido, 2) }}
                    </td>
                </tr>
                <tr>
                    <td style="padding:3px 6px; border:0.5px solid #ccc;">Total Importe Pagado:</td>
                    <td style="padding:3px 6px; border:0.5px solid #ccc; text-align:right;">
                        S/ {{ number_format($imp_pagado, 2) }}
                    </td>
                </tr>
            </table>
        </td>
    </tr>
</table>
