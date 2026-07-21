@extends('client.layout', ['pageTitle' => 'Devis ' . $quote->reference])

@section('content')
    @if(session('success')) <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#dcfce7;border:1px solid #86efac;border-radius:8px;color:#166534">{{ session('success') }}</div> @endif
    @if(session('error'))   <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#fee2e2;border:1px solid #fca5a5;border-radius:8px;color:#991b1b">{{ session('error') }}</div> @endif
    @if(session('warning')) <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#fef3c7;border:1px solid #fcd34d;border-radius:8px;color:#78350f">{{ session('warning') }}</div> @endif
    @if(session('info'))    <div style="max-width:960px;margin:16px auto 0;padding:14px;background:#dbeafe;border:1px solid #93c5fd;border-radius:8px;color:#1e40af">{{ session('info') }}</div> @endif

    @include('quotes._show_content', ['quote' => $quote, 'isPublic' => false])
@endsection
