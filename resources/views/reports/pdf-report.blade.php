<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>{{ $title }}</title>
    @php
        $cols = $columnCount ?? count($headers);
        $useCards = $cols > 10;
    @endphp
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 9px;
            color: #1a1a2e;
            line-height: 1.3;
        }
        .header {
            background: linear-gradient(135deg, #1e40af 0%, #3b82f6 100%);
            color: white;
            padding: 16px 24px;
            margin-bottom: 0;
        }
        .header h1 {
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 2px;
        }
        .header .subtitle {
            font-size: 9px;
            opacity: 0.85;
        }
        .meta-bar {
            background: #f0f4ff;
            border-bottom: 2px solid #dbeafe;
            padding: 6px 24px;
            font-size: 8px;
            color: #475569;
        }
        .meta-bar span {
            margin-right: 24px;
        }
        .content {
            padding: 12px 16px;
        }

        /* ── Table layout (for narrow reports) ── */
        table.report-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 8px;
        }
        table.report-table thead th {
            background: #1e40af;
            color: white;
            padding: 8px 6px;
            text-align: left;
            font-weight: 600;
            font-size: 8px;
            text-transform: uppercase;
            letter-spacing: 0.3px;
            white-space: nowrap;
        }
        table.report-table tbody td {
            padding: 6px 6px;
            border-bottom: 1px solid #e2e8f0;
            vertical-align: top;
            word-break: break-word;
        }
        table.report-table tbody tr:nth-child(even) {
            background: #f8fafc;
        }

        /* ── Card layout (for wide reports) ── */
        .record-card {
            border: 1px solid #94a3b8;
            border-radius: 4px;
            margin-bottom: 8px;
            page-break-inside: avoid;
            overflow: hidden;
        }
        .record-card-header {
            background: #1e40af;
            color: white;
            padding: 3px 8px;
            font-size: 8px;
            font-weight: 700;
        }
        .record-number {
            color: rgba(255,255,255,0.6);
            font-weight: 400;
            margin-left: 4px;
            font-size: 7px;
        }
        .record-card-body {
            padding: 4px 6px;
        }
        .fields-grid {
            display: table;
            width: 100%;
            font-size: 7.5px;
        }
        .fields-row {
            display: table-row;
        }
        .field-cell {
            display: table-cell;
            padding: 1.5px 4px;
            vertical-align: top;
            width: 50%;
        }
        .field-label {
            color: #64748b;
            font-weight: 600;
            font-size: 6.5px;
            text-transform: uppercase;
            letter-spacing: 0.2px;
            display: inline;
        }
        .field-value {
            color: #1a1a2e;
            display: inline;
            margin-left: 3px;
        }
        .field-sep {
            border-bottom: 1px solid #f1f5f9;
        }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 7px;
            color: #94a3b8;
            padding: 4px;
            border-top: 1px solid #e2e8f0;
        }
        .row-count {
            margin-top: 8px;
            font-size: 8px;
            color: #64748b;
            text-align: right;
        }
        .empty-state {
            text-align: center;
            padding: 24px;
            color: #94a3b8;
        }
        @page {
            margin: 10mm 8mm 14mm 8mm;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>{{ $title }}</h1>
        <div class="subtitle">SecureCAT &mdash; Admission & Assessment System</div>
    </div>

    <div class="meta-bar">
        <span><strong>Academic Year:</strong> {{ $academicYear }}</span>
        <span><strong>Generated:</strong> {{ $generatedAt }}</span>
        <span><strong>Records:</strong> {{ number_format(count($rows)) }}</span>
    </div>

    <div class="content">
        @if(count($rows) === 0)
            <div class="empty-state">No data available for this report.</div>
        @elseif($useCards)
            {{-- Compact card layout: 2-column fields, ~4 per page --}}
            @foreach($rows as $index => $row)
                @php
                    // Build field pairs (skip index 0 which is the card title)
                    $fields = [];
                    for ($i = 1; $i < count($headers); $i++) {
                        $fields[] = ['label' => $headers[$i], 'value' => $row[$i] ?? '—'];
                    }
                    $pairs = array_chunk($fields, 2);
                @endphp
                <div class="record-card">
                    <div class="record-card-header">
                        {{ $row[0] ?? 'Record' }}
                        <span class="record-number">#{{ $index + 1 }}</span>
                    </div>
                    <div class="record-card-body">
                        <div class="fields-grid">
                            @foreach($pairs as $pair)
                                <div class="fields-row">
                                    @foreach($pair as $field)
                                        <div class="field-cell field-sep">
                                            <span class="field-label">{{ $field['label'] }}:</span>
                                            <span class="field-value">{{ $field['value'] }}</span>
                                        </div>
                                    @endforeach
                                    @if(count($pair) === 1)
                                        <div class="field-cell"></div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                </div>
            @endforeach
        @else
            {{-- Standard table layout for narrow reports --}}
            <table class="report-table">
                <thead>
                    <tr>
                        @foreach($headers as $header)
                            <th>{{ $header }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach($rows as $row)
                        <tr>
                            @foreach($row as $cell)
                                <td>{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif

        <div class="row-count">
            Total: {{ number_format(count($rows)) }} record(s)
        </div>
    </div>

    <div class="footer">
        {{ $title }} &mdash; {{ $academicYear }} &mdash; Generated {{ $generatedAt }} &mdash; SecureCAT
    </div>
</body>
</html>
