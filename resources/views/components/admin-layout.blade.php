@props(['title' => 'Dashboard'])

<x-slot:title>{{ $title }}</x-slot:title>

@include('layouts.admin', [
  'title' => $title,
  'slot'  => $slot,
  'topbarActions' => $topbarActions ?? ''
])