<x-admin-layout title="Modifier {{ $quote->reference }}">
    <x-slot:topbarLeft>
    {{-- Retour intelligent : la provenance (?back=), sinon la page
         précédente, sinon la destination ci-dessous. --}}
    @include('admin.partials._smart_back', ['defaut' => [
        'url'   => route('admin.quotes.show', $quote),
        'label' => 'Le devis',
    ]])
</x-slot:topbarLeft>

    @include('admin.quotes.partials._form', [
        'action' => route('admin.quotes.update', $quote),
        'method' => 'PUT',
    ])
</x-admin-layout>
