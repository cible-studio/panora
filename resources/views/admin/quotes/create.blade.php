<x-admin-layout title="Nouveau devis">
    <x-slot:topbarLeft>
        <a href="{{ route('admin.quotes.index') }}" class="btn btn-ghost btn-sm">← Retour aux devis</a>
    </x-slot:topbarLeft>

    @include('admin.quotes.partials._form', [
        'quote'  => null,
        'action' => route('admin.quotes.store'),
        'method' => 'POST',
    ])
</x-admin-layout>
