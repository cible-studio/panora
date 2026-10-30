<x-admin-layout title="Nouveau devis">
    <x-slot:topbarLeft>
    {{-- Retour intelligent : la provenance (?back=), sinon la page
         précédente, sinon la destination ci-dessous. --}}
    @include('admin.partials._smart_back', ['defaut' => [
        'url'   => route('admin.quotes.index'),
        'label' => 'Devis',
    ]])
</x-slot:topbarLeft>

    @include('admin.quotes.partials._form', [
        'quote'  => null,
        'action' => route('admin.quotes.store'),
        'method' => 'POST',
    ])
</x-admin-layout>
