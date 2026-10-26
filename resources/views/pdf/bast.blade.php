<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        @page { margin: 36pt 45pt; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: Helvetica, Arial, sans-serif; font-size: 10pt; color: #111; }
        .kop { width: 100%; border-bottom: 3px solid #000; padding-bottom: 8px; margin-bottom: 16px; }
        .kop td { vertical-align: middle; }
        .kop img { max-width: 64px; max-height: 64px; }
        .kop .instansi { font-size: 13pt; font-weight: bold; text-transform: uppercase; }
        .kop .alamat { font-size: 9pt; }
        .judul { text-align: center; font-size: 12pt; font-weight: bold; text-transform: uppercase; text-decoration: underline; margin: 10px 0 4px; }
        .nomor { text-align: center; font-size: 9pt; margin-bottom: 14px; }
        table.data { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.data th, table.data td { border: 1px solid #444; padding: 5px 7px; font-size: 9.5pt; vertical-align: top; }
        table.data th { background: #f0f0f0; text-align: left; }
        .info td { padding: 2.5px 0; vertical-align: top; }
        .info td:first-child { width: 155px; }
        .ttd { width: 100%; margin-top: 34px; page-break-inside: avoid; }
        .ttd td { width: 33.33%; text-align: center; vertical-align: top; font-size: 9.5pt; }
        .ttd .role { font-weight: bold; text-decoration: underline; }
        .ttd .space { height: 70px; }
        .ttd img { max-height: 55px; max-width: 140px; }
        .ttd .nama { font-weight: bold; margin-top: 6px; }
        .ttd .identitas { font-size: 8.5pt; }
        .footer { margin-top: 18px; font-size: 8pt; color: #555; border-top: 1px solid #ccc; padding-top: 5px; }
        .catatan { font-size: 9pt; background: #f7f7f7; border: 1px dashed #aaa; padding: 7px; margin-bottom: 12px; }
        .unit-codes { font-family: 'Courier New', monospace; font-size: 8.5pt; }
    </style>
</head>
<body>
    @include('pdf.letter_body', ['loan' => $loan, 'setting' => $setting, 'title' => $title, 'documentNumber' => $documentNumber])
</body>
</html>
