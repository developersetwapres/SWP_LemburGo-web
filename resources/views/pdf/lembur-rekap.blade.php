<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>Rekap Pengajuan Uang Lembur</title>
    <style>
        @page {
            margin: 16mm 8mm 12mm;
        }

        body {
            color: #000;
            font-family: 'DejaVu Sans', sans-serif;
            font-size: 8pt;
        }

        .document-header {
            margin-bottom: 6mm;
            text-align: center;
        }

        .document-header h1,
        .document-header p {
            font-weight: 700;
            margin: 0;
        }

        .document-header h1 {
            font-size: 12pt;
            margin-bottom: 2mm;
        }

        .document-header p {
            font-size: 10pt;
            line-height: 1.5;
        }

        .separator {
            border: 0;
            border-top: 0.7pt solid #000;
            margin: 0 0 4mm;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        tr {
            page-break-inside: avoid;
        }

        th,
        td {
            border: 0.45pt solid #000;
            overflow-wrap: break-word;
            padding: 2mm 1.5mm;
            text-align: center;
            vertical-align: middle;
            word-wrap: break-word;
        }

        th {
            background: #e0e0e0;
            font-size: 6.5pt;
            font-weight: 700;
        }

        td {
            height: 24mm;
        }

        .photo {
            display: block;
            margin: 0 auto;
            max-height: 22mm;
            max-width: 37mm;
        }
    </style>
</head>
<body>
    <header class="document-header">
        <h1>REKAP PENGAJUAN UANG LEMBUR (DI LUAR JAM KERJA)</h1>
        <p>TEKNISI KOMPUTER, JARINGAN, DAN PROGRAMMER</p>
        <p>BIRO TATA USAHA DAN SUMBER DAYA MANUSIA</p>
        <p>BULAN {{ $bulan }} TAHUN {{ $tahun }}</p>
    </header>

    <hr class="separator">

    <table>
        <colgroup>
            <col style="width: 8.25%;">
            <col style="width: 13.25%;">
            <col style="width: 17%;">
            <col style="width: 13.25%;">
            <col style="width: 18.25%;">
            <col style="width: 18.25%;">
            <col style="width: 11.75%;">
        </colgroup>
        <thead>
            <tr>
                <th>Tanggal</th>
                <th>Nama Lengkap</th>
                <th>Kegiatan/Acara</th>
                <th>Lokasi</th>
                <th>Lampiran Foto Eviden</th>
                <th>Lampiran Foto Presensi Pulang</th>
                <th>Waktu Kepulangan</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($lemburs as $lembur)
                <tr>
                    <td>{{ $lembur['tanggal'] }}</td>
                    <td>{{ $lembur['nama_lengkap'] }}</td>
                    <td>{{ $lembur['kegiatan'] }}</td>
                    <td>{{ $lembur['lokasi'] }}</td>
                    <td>
                        @if ($lembur['foto_eviden'])
                            <img class="photo" src="{{ $lembur['foto_eviden'] }}" alt="Foto eviden">
                        @else
                            -
                        @endif
                    </td>
                    <td>
                        @if ($lembur['foto_presensi_pulang'])
                            <img class="photo" src="{{ $lembur['foto_presensi_pulang'] }}" alt="Foto presensi pulang">
                        @else
                            -
                        @endif
                    </td>
                    <td>{{ $lembur['waktu_kepulangan'] }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="7">Tidak ada data lembur pada periode ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
