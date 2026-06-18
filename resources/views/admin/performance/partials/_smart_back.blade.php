{{-- Alias rétrocompat → le vrai partial est désormais à
     resources/views/admin/partials/_smart_back.blade.php.

     2026-06-18 : le smart back a élargi son scope (initialement créé
     pour Performance, désormais utilisé partout — teams, clients,
     invoices…). On a déplacé le partial vers admin/partials/ et on
     garde cet alias pour ne pas casser les @include existants. --}}
@include('admin.partials._smart_back')
