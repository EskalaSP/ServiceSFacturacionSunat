@if($is_ticket)
    <div class="info-block">
        <div class="info-line"><span class="info-label">Régimen:</span> {{ $regimen_label }}</div>
        <div class="info-line"><span class="info-label">Tasa:</span> {{ $tasa }}%</div>
        @if(!empty($observacion))
        <div class="info-line"><span class="info-label">Obs.:</span> {{ $observacion }}</div>
        @endif
    </div>
@else
    <table class="info-section">
        <tr>
            <td class="info-label">Régimen:</td>
            <td>{{ $regimen_label }}</td>
            <td class="info-label">Tasa:</td>
            <td>{{ $tasa }}%</td>
        </tr>
        @if(!empty($observacion))
        <tr>
            <td class="info-label">Observación:</td>
            <td colspan="3">{{ $observacion }}</td>
        </tr>
        @endif
    </table>
@endif
