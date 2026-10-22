{{-- Encadré « votre campagne » des mails de renouvellement (2026-10-22). --}}
@php $coul = $charte['couleurs']; @endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">
<tr><td style="border:1px solid {{ $coul['gris'] }}; border-radius:12px; padding:14px 18px; background-color:#FAFAFA;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="font-size:14px; line-height:21px;">
    <tr>
      <td width="1%" style="padding:3px 14px 3px 0; color:#777777; white-space:nowrap; vertical-align:top;">Campagne</td>
      <td style="padding:3px 0; font-weight:bold;">{{ $campagne }}</td>
    </tr>
    <tr>
      <td width="1%" style="padding:3px 14px 3px 0; color:#777777; white-space:nowrap; vertical-align:top;">Dispositif</td>
      <td style="padding:3px 0; font-weight:bold;">{{ $dispositif }}</td>
    </tr>
    <tr>
      <td width="1%" style="padding:3px 14px 3px 0; color:#777777; white-space:nowrap; vertical-align:top;">Échéance</td>
      <td style="padding:3px 0; font-weight:bold; color:{{ $coul['rouge'] }};">{{ $echeance }}</td>
    </tr>
    @if($panneaux)
    <tr>
      <td width="1%" style="padding:3px 14px 3px 0; color:#777777; white-space:nowrap; vertical-align:top;">Emplacements</td>
      <td style="padding:3px 0; font-size:13px; color:#333333;">
        {{ implode(' · ', $panneaux) }}@if($autres) <span style="color:#777777;">et {{ $autres }} autre{{ $autres > 1 ? 's' : '' }}</span>@endif
      </td>
    </tr>
    @endif
  </table>
</td></tr>
</table>
