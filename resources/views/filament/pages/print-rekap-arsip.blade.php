<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Print Rekap Arsip</title>
    <style>
        body {
            font-family: sans-serif;
            color: #333;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 1rem;
        }

        th, td {
            border: 1px solid #999;
            padding: 8px;
            text-align: center;
        }

        th {
            background-color: #ffbd10;
            color: white;
        }

        img.logo {
            width: 100px;
            margin-bottom: 1rem;
        }

        .footer {
            margin-top: 3rem;
            text-align: right;
        }

        @media print {
            @page {
                size: A4 landscape;
                margin: 1cm;
            }
        }
    </style>
</head>
<body onload="window.print()">
    <div>
        <table>
            <thead>
                <tr>
                    <th></th>
                    <th colspan="{{ count($rekap->keys()) * 3 + 1 }}">LAPORAN KINERJA APLIKASI SIARDI</th>
                </tr>
                <tr>
                    <td rowspan="3">
                        <img src="{{ asset('logo.png') }}" class="logo" alt="Logo">
                    </td>
                    @foreach ($rekap->keys() as $key)
                        <th colspan="3">{{ $key }}</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach($rekap->keys() as $kc)
                        <th>Kredit</th>
                        <th>Deposito</th>
                        <th>Tabungan</th>
                    @endforeach
                </tr>
                <tr>
                    @foreach ($rekap as $data)
                        <td>{{ $data['kredit'] }}</td>
                        <td>{{ $data['deposito'] }}</td>
                        <td>{{ $data['tabungan'] }}</td>
                    @endforeach
                </tr>
                <tr>
                    <th>Total Keseluruhan</th>
                    @foreach ($rekap as $data)
                        <td colspan="3">{{ $data['total'] }}</td>
                    @endforeach
                </tr>
            </thead>
        </table>

        <div class="footer">
            <p>{{ auth()->user()?->branchOffice?->branch_name ?? 'SIARDI' }}, {{ \Carbon\Carbon::now()->locale('id')->translatedFormat('d F Y') }}</p>
            <p>Mengetahui,</p>
            <p style="margin-top: 3rem; text-decoration: underline;">{{ auth()->user()?->name ?? '-' }}</p>
            <p>{{ Str::of(auth()->user()?->primaryRoleName() ?? 'user')->replace('_', ' ')->squish()->upper() }}</p>
        </div>
    </div>
</body>
</html>
