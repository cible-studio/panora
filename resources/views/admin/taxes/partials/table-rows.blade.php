{{-- resources/views/admin/taxes/partials/table-rows.blade.php --}}
@forelse($taxes as $tax)
<tr>
    <td><strong>{{ $tax->commune->name }}</strong></td>
    <td>
        @if($tax->type === 'odp')
            <span class="badge badge-blue">ODP</span>
        @else
            <span class="badge badge-purple">TM</span>
        @endif
    </td>
    <td>{{ $tax->year }}</td>
    <td style="color:var(--accent); font-weight:600;">{{ number_format($tax->amount, 0, ',', ' ') }} FCFA</td>
    <td style="font-size:12px;">{{ $tax->due_date ? $tax->due_date->format('d/m/Y') : '—' }}</td>
    <td style="font-size:12px; color:var(--text3);">{{ $tax->paid_at ? $tax->paid_at->format('d/m/Y') : '—' }}</td>
    <td>
        @if($tax->status === 'en_attente')
            <span class="badge badge-orange">En attente</span>
        @elseif($tax->status === 'payee')
            <span class="badge badge-green">Payée ✓</span>
        @else
            <span class="badge badge-red">En retard ⚠️</span>
        @endif
    </td>
    <td>
        <div style="display:flex; gap:6px;">
            @if($tax->status !== 'payee')
            <form method="POST" action="{{ route('admin.taxes.pay', $tax) }}" class="inline-form">
                @csrf @method('PATCH')
                <button class="btn btn-success btn-sm" title="Marquer payée">✓ Payée</button>
            </form>
            @endif
            <a href="{{ route('admin.taxes.edit', $tax) }}" class="btn btn-ghost btn-sm">✏️</a>
            <form method="POST" action="{{ route('admin.taxes.destroy', $tax) }}" class="inline-form" onsubmit="return confirm('Supprimer cette taxe ?')">
                @csrf @method('DELETE')
                <button class="btn btn-danger btn-sm">🗑️</button>
            </form>
        </div>
    </td>
</tr>
@empty
<tr>
    <td colspan="8" style="text-align:center; color:var(--text3); padding:32px;">Aucune taxe</td>
</tr>
@endforelse