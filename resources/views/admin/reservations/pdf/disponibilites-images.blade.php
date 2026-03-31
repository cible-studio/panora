/**
 * Export Excel via PhpSpreadsheet (composer require phpoffice/phpspreadsheet)
 * Produit un vrai .xlsx avec mise en forme professionnelle
 */
private function _exportExcelPhpSpreadsheet(\Illuminate\Support\Collection $panels, string $filename): \Symfony\Component\HttpFoundation\StreamedResponse
{
    $spreadsheet = new \PhpOffice\PhpSpreadsheet\Spreadsheet();
    $sheet       = $spreadsheet->getActiveSheet();
    $sheet->setTitle('Disponibilités');
 
    // ── En-tête ───────────────────────────────────────────────────
    $headers = [
        'A' => 'Référence',
        'B' => 'Désignation',
        'C' => 'Commune',
        'D' => 'Zone',
        'E' => 'Format',
        'F' => 'Dimensions',
        'G' => 'Éclairé',
        'H' => 'Catégorie',
        'I' => 'Tarif/mois (FCFA)',
        'J' => 'Trafic/jour',
        'K' => 'Statut',
        'L' => 'Date libération',
    ];
 
    foreach ($headers as $col => $label) {
        $sheet->setCellValue($col . '1', $label);
    }
 
    // Style en-tête
    $headerRange = 'A1:L1';
    $sheet->getStyle($headerRange)->applyFromArray([
        'font'      => ['bold' => true, 'color' => ['rgb' => 'FFFFFF'], 'size' => 11],
        'fill'      => ['fillType' => 'solid', 'startColor' => ['rgb' => '0F172A']],
        'alignment' => ['horizontal' => 'center', 'vertical' => 'center'],
        'borders'   => ['allBorders' => ['borderStyle' => 'thin', 'color' => ['rgb' => '334155']]],
    ]);
    $sheet->getRowDimension(1)->setRowHeight(22);
 
    // ── Données ────────────────────────────────────────────────────
    $statusLabels = [
        'libre'          => 'Disponible',
        'occupe'         => 'Occupé',
        'option_periode' => 'En option',
        'option'         => 'En option',
        'maintenance'    => 'Maintenance',
        'confirme'       => 'Confirmé',
    ];
 
    $statusColors = [
        'libre'          => '166534', // vert foncé
        'occupe'         => '991B1B', // rouge foncé
        'option_periode' => '92400E', // ambre foncé
        'option'         => '92400E',
        'maintenance'    => '374151', // gris
        'confirme'       => '5B21B6', // violet
    ];
 
    $row = 2;
    foreach ($panels as $p) {
        $sheet->setCellValue('A' . $row, $p['reference']);
        $sheet->setCellValue('B' . $row, $p['name']);
        $sheet->setCellValue('C' . $row, $p['commune']);
        $sheet->setCellValue('D' . $row, $p['zone']);
        $sheet->setCellValue('E' . $row, $p['format']);
        $sheet->setCellValue('F' . $row, $p['dimensions'] ?? '');
        $sheet->setCellValue('G' . $row, $p['is_lit'] ? 'Oui' : 'Non');
        $sheet->setCellValue('H' . $row, $p['category'] ?? '');
        $sheet->setCellValue('I' . $row, (float)($p['monthly_rate'] ?? 0));
        $sheet->setCellValue('J' . $row, (int)($p['daily_traffic'] ?? 0));
 
        $statusLabel = $statusLabels[$p['display_status']] ?? ucfirst($p['display_status']);
        $sheet->setCellValue('K' . $row, $statusLabel);
        $sheet->setCellValue('L' . $row, $p['release_info']['date'] ?? '');
 
        // Couleur de fond alternée
        $bgColor = ($row % 2 === 0) ? 'F8FAFC' : 'FFFFFF';
        $sheet->getStyle("A{$row}:L{$row}")->getFill()
            ->setFillType('solid')
            ->getStartColor()->setRGB($bgColor);
 
        // Couleur colonne statut
        $statusColor = $statusColors[$p['display_status']] ?? '374151';
        $sheet->getStyle("K{$row}")->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => $statusColor]],
        ]);
 
        // Format monétaire pour tarif
        $sheet->getStyle("I{$row}")->getNumberFormat()
            ->setFormatCode('#,##0" FCFA"');
 
        $row++;
    }
 
    // ── Bordures sur les données ───────────────────────────────────
    if ($row > 2) {
        $sheet->getStyle("A2:L" . ($row - 1))->getBorders()
            ->getAllBorders()->setBorderStyle('thin')
            ->getColor()->setRGB('E2E8F0');
    }
 
    // ── Largeurs colonnes ──────────────────────────────────────────
    $widths = ['A'=>14,'B'=>30,'C'=>16,'D'=>14,'E'=>14,'F'=>12,'G'=>10,'H'=>14,'I'=>18,'J'=>14,'K'=>14,'L'=>16];
    foreach ($widths as $col => $w) {
        $sheet->getColumnDimension($col)->setWidth($w);
    }
 
    // ── Freeze ligne 1 + filtre auto ──────────────────────────────
    $sheet->freezePane('A2');
    $sheet->setAutoFilter("A1:L1");
 
    // ── Résumé en bas ─────────────────────────────────────────────
    $summaryRow = $row + 1;
    $sheet->setCellValue('A' . $summaryRow, 'Total panneaux :');
    $sheet->setCellValue('B' . $summaryRow, $panels->count());
    $sheet->setCellValue('C' . $summaryRow, 'Disponibles :');
    $sheet->setCellValue('D' . $summaryRow, $panels->where('display_status', 'libre')->count());
    $sheet->setCellValue('E' . $summaryRow, 'Occupés :');
    $sheet->setCellValue('F' . $summaryRow, $panels->whereIn('display_status', ['occupe','option_periode'])->count());
    $sheet->setCellValue('G' . $summaryRow, 'Générée le :');
    $sheet->setCellValue('H' . $summaryRow, now()->format('d/m/Y H:i'));
 
    $sheet->getStyle("A{$summaryRow}:H{$summaryRow}")->applyFromArray([
        'font' => ['bold' => true, 'color' => ['rgb' => '64748B'], 'size' => 10],
        'fill' => ['fillType' => 'solid', 'startColor' => ['rgb' => 'F1F5F9']],
    ]);
 
    // ── Écriture et téléchargement ─────────────────────────────────
    $writer = new \PhpOffice\PhpSpreadsheet\Writer\Xlsx($spreadsheet);
 
    return response()->streamDownload(function () use ($writer) {
        $writer->save('php://output');
    }, "{$filename}.xlsx", [
        'Content-Type'        => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'Content-Disposition' => "attachment; filename={$filename}.xlsx",
        'Cache-Control'       => 'no-cache, must-revalidate',
    ]);
}
 
/**
 * Fallback Excel si PhpSpreadsheet non installé.
 * Produit un .xls HTML reconnu par Excel/LibreOffice.
 */
private function _exportExcelHtml(\Illuminate\Support\Collection $panels, string $filename): \Illuminate\Http\Response
{
    $statusLabels = [
        'libre'          => 'Disponible',
        'occupe'         => 'Occupé',
        'option_periode' => 'En option',
        'option'         => 'En option',
        'maintenance'    => 'Maintenance',
        'confirme'       => 'Confirmé',
    ];
    $statusBg = [
        'libre'          => '#d1fae5',
        'occupe'         => '#fee2e2',
        'option_periode' => '#fef3c7',
        'option'         => '#fef3c7',
        'maintenance'    => '#f1f5f9',
        'confirme'       => '#ede9fe',
    ];
 
    $rows = '';
    foreach ($panels as $i => $p) {
        $bg    = ($i % 2 === 0) ? '#ffffff' : '#f8fafc';
        $sBg   = $statusBg[$p['display_status']] ?? '#f1f5f9';
        $label = $statusLabels[$p['display_status']] ?? ucfirst($p['display_status']);
        $tarif = $p['monthly_rate'] ? number_format($p['monthly_rate'], 0, ',', ' ') . ' FCFA' : '—';
        $trafic = $p['daily_traffic'] ? number_format($p['daily_traffic']) . ' contacts' : '—';
 
        $rows .= "<tr style='background:{$bg}'>
            <td style='font-family:monospace;font-weight:bold'>" . htmlspecialchars($p['reference']) . "</td>
            <td>" . htmlspecialchars($p['name']) . "</td>
            <td>" . htmlspecialchars($p['commune']) . "</td>
            <td>" . htmlspecialchars($p['zone']) . "</td>
            <td>" . htmlspecialchars($p['format'] ?? '—') . "</td>
            <td>" . htmlspecialchars($p['dimensions'] ?? '—') . "</td>
            <td style='text-align:center'>" . ($p['is_lit'] ? '✓' : '') . "</td>
            <td>" . htmlspecialchars($p['category'] ?? '—') . "</td>
            <td style='text-align:right;font-weight:bold'>{$tarif}</td>
            <td style='text-align:right'>{$trafic}</td>
            <td style='background:{$sBg};text-align:center;font-weight:bold'>{$label}</td>
            <td>" . htmlspecialchars($p['release_info']['date'] ?? '') . "</td>
        </tr>\n";
    }
 
    $total    = $panels->count();
    $dispos   = $panels->where('display_status', 'libre')->count();
    $occupes  = $panels->whereIn('display_status', ['occupe','option_periode'])->count();
    $generated = now()->format('d/m/Y H:i');
 
    $html = "
<html xmlns:o='urn:schemas-microsoft-com:office:office'
      xmlns:x='urn:schemas-microsoft-com:office:excel'
      xmlns='http://www.w3.org/TR/REC-html40'>
<head>
<meta charset='UTF-8'>
<style>
  body     { font-family: Calibri, Arial, sans-serif; font-size: 11px; }
  table    { border-collapse: collapse; width: 100%; }
  th       { background: #0F172A; color: white; padding: 7px 10px; text-align: left;
             font-size: 11px; border: 1px solid #334155; }
  td       { padding: 6px 10px; border: 1px solid #E2E8F0; font-size: 11px; vertical-align: middle; }
  .summary { background: #F1F5F9; font-weight: bold; color: #64748B; padding: 6px 10px; }
  h2       { color: #0F172A; font-size: 14px; margin-bottom: 4px; }
  .meta    { color: #64748B; font-size: 10px; margin-bottom: 12px; }
</style>
</head>
<body>
<h2>CIBLE CI — Disponibilités &amp; Panneaux</h2>
<div class='meta'>
    Généré le {$generated} &nbsp;|&nbsp;
    Total : <strong>{$total}</strong> panneaux &nbsp;|&nbsp;
    Disponibles : <strong>{$dispos}</strong> &nbsp;|&nbsp;
    Occupés : <strong>{$occupes}</strong>
</div>
<table>
<thead>
<tr>
    <th>Référence</th><th>Désignation</th><th>Commune</th><th>Zone</th>
    <th>Format</th><th>Dims</th><th>Éclairé</th><th>Catégorie</th>
    <th>Tarif/mois</th><th>Trafic/j</th><th>Statut</th><th>Libre le</th>
</tr>
</thead>
<tbody>
{$rows}
</tbody>
</table>
</body>
</html>";
 
    return response($html, 200, [
        'Content-Type'        => 'application/vnd.ms-excel; charset=UTF-8',
        'Content-Disposition' => "attachment; filename={$filename}.xls",
        'Cache-Control'       => 'no-cache',
    ]);
}