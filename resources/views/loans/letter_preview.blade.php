<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $title }} — {{ $documentNumber }}</title>
    <style>
        * { box-sizing: border-box; }
        body { margin: 0; background: #e2e8f0; font-family: Helvetica, Arial, sans-serif; color: #111; }
        .toolbar {
            position: sticky; top: 0; z-index: 10;
            display: flex; align-items: center; justify-content: space-between; gap: 12px; flex-wrap: wrap;
            padding: 10px 16px; background: #0f172a; color: #fff;
        }
        .toolbar .title { font-size: 13px; font-weight: 700; }
        .toolbar .sub { font-size: 11px; color: #cbd5e1; }
        .toolbar .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .btn {
            display: inline-flex; align-items: center; gap: 6px;
            border: 0; border-radius: 8px; padding: 8px 14px;
            font-size: 12px; font-weight: 700; cursor: pointer; text-decoration: none;
        }
        .btn-primary { background: #d97706; color: #fff; }
        .btn-secondary { background: #1e293b; color: #e2e8f0; border: 1px solid #475569; }
        .paper-wrap { padding: 24px 12px 40px; display: flex; justify-content: center; }
        .paper {
            width: 210mm; min-height: 297mm; background: #fff;
            padding: 20mm 18mm; box-shadow: 0 12px 30px rgba(15, 23, 42, .18);
            font-size: 10pt;
        }
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
        .info { width: 100%; }
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

        @page { size: A4 portrait; margin: 20mm 18mm; }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .paper-wrap { padding: 0; display: block; }
            .paper { width: auto; min-height: 0; padding: 0; box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">{{ $title }}</div>
            <div class="sub">Nomor {{ $documentNumber }} — pratinjau cetak (A4)</div>
        </div>
        <div class="actions">
            <button type="button" class="btn btn-primary" onclick="window.print()">Cetak Sekarang</button>
            <a class="btn btn-secondary" href="{{ route('loans.bast', $loan) }}">Unduh PDF</a>
            <a class="btn btn-secondary" href="{{ route('loans.show', $loan) }}">Kembali</a>
        </div>
    </div>

    <div class="paper-wrap">
        <div class="paper">
            @include('pdf.letter_body', ['loan' => $loan, 'setting' => $setting, 'title' => $title, 'documentNumber' => $documentNumber])
        </div>
    </div>
</body>
</html>
