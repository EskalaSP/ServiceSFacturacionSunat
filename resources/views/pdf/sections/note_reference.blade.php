{{-- Referencia de documento afectado (NC/ND) --}}
@if(!empty($doc_afectado_tipo))
    @php
        $tipoAfectadoLabel = match($doc_afectado_tipo) {
            '01' => 'Factura',
            '03' => 'Boleta',
            '07' => 'Nota de Crédito',
            '08' => 'Nota de Débito',
            default => 'Documento',
        };
        $numAfectado = ($doc_afectado_serie ?? '') . '-' . ($doc_afectado_correlativo ?? '');
    @endphp

    @if($is_ticket)
        <div class="note-reference">
            <div class="ref-title">Documento Afectado</div>
            <div class="info-line">{{ $tipoAfectadoLabel }}: {{ $numAfectado }}</div>
            @if(!empty($cod_motivo))
            <div class="info-line"><span class="info-label">Motivo:</span> [{{ $cod_motivo }}] {{ $des_motivo ?? '' }}</div>
            @endif
        </div>
    @else
        <div class="note-reference">
            <table>
                <tr>
                    <td style="width: 140px;"><strong>Documento Afectado:</strong></td>
                    <td>{{ $tipoAfectadoLabel }} {{ $numAfectado }}</td>
                </tr>
                @if(!empty($cod_motivo))
                <tr>
                    <td><strong>Motivo:</strong></td>
                    <td>[{{ $cod_motivo }}] {{ $des_motivo ?? '' }}</td>
                </tr>
                @endif
            </table>
        </div>
    @endif
@endif
