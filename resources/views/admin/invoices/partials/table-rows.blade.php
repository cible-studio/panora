{{-- resources/views/admin/invoices/partials/table-rows.blade.php --}}
@forelse($invoices as $invoice)
    @include('admin.invoices.partials.row', ['invoice' => $invoice])
@empty
<tr>
    <td colspan="9" style="text-align:center; color:var(--text3); padding:32px;">Aucune facture</td>
</tr>
@endforelse
