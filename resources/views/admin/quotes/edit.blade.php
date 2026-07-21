<x-admin-layout title="Modifier {{ $quote->reference }}">
    <x-slot:topbarLeft>
        <a href="{{ route('admin.quotes.show', $quote) }}" class="btn btn-ghost btn-sm">← Retour au devis</a>
    </x-slot:topbarLeft>

    @include('admin.quotes.partials._form', [
        'action' => route('admin.quotes.update', $quote),
        'method' => 'PUT',
    ])
</x-admin-layout>
