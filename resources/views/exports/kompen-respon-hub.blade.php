<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            margin: 11mm 7mm;
            size: A4 landscape;
        }

        * {
            box-sizing: border-box;
        }

        body {
            color: #1f2937;
            font-family: DejaVu Sans, sans-serif;
            font-size: 7px;
        }

        h1 {
            background: #1f4e78;
            color: #ffffff;
            font-size: 14px;
            margin: 0 0 8px;
            padding: 9px 10px;
        }

        .context {
            background: #dce6f1;
            font-size: 8px;
            font-weight: bold;
            margin-bottom: 10px;
            padding: 5px 7px;
        }

        table {
            border-collapse: collapse;
            table-layout: fixed;
            width: 100%;
        }

        thead {
            display: table-header-group;
        }

        th {
            background: #1f4e78;
            color: #ffffff;
            font-weight: bold;
            padding: 5px 3px;
            text-align: center;
            vertical-align: middle;
        }

        td {
            border-bottom: 0.35px solid #e5e7eb;
            padding: 4px 3px;
            vertical-align: middle;
            word-wrap: break-word;
        }

        td.integer,
        td.hours {
            text-align: right;
        }

        td.date {
            text-align: center;
        }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="context">Periode: {{ $period }}</div>

    <table>
        <thead>
            <tr>
                @foreach ($columns as $column)
                    <th>{{ $column['heading'] }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($records as $record)
                <tr>
                    @foreach ($columns as $column)
                        @php($value = data_get($record, $column['field']))
                        <td class="{{ $column['type'] }}">
                            @if ($column['type'] === 'hours')
                                {{ number_format((float) $value, 2, ',', '.') }}
                            @elseif ($column['type'] === 'integer')
                                {{ number_format((float) $value, 0, ',', '.') }}
                            @else
                                {{ $value ?? '—' }}
                            @endif
                        </td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
