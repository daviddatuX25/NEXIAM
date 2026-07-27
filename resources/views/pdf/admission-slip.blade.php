<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Admission Slip — {{ $referenceNumber }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; padding: 24px; }
        .header { text-align: center; margin-bottom: 24px; border-bottom: 2px solid #1e40af; padding-bottom: 12px; }
        .header h1 { margin: 0; color: #1e40af; font-size: 18px; }
        .section { margin: 16px 0; }
        .section h2 { font-size: 14px; margin: 0 0 8px; color: #374151; }
        .photo-placeholder { width: 120px; height: 150px; border: 2px dashed #9ca3af; display: inline-block; text-align: center; line-height: 150px; color: #9ca3af; font-size: 10px; }

        .ref { font-family: monospace; font-size: 16px; font-weight: bold; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 8px; }
        .label { color: #6b7280; width: 140px; }
    </style>
</head>
<body>
    <div class="header">
        <h1>SecureCAT — Computerized Admission & Testing</h1>
        <p style="margin: 8px 0 0;">ADMISSION SLIP</p>
    </div>

    <table>
        <tr>
            <td class="label">Reference Number</td>
            <td class="ref">{{ $referenceNumber }}</td>
            <td rowspan="4" style="text-align: right;">
                <div class="photo-placeholder">Photo</div>
            </td>
        </tr>
        <tr>
            <td class="label">Name</td>
            <td>{{ $fullName }}</td>
        </tr>
        <tr>
            <td class="label">Birthdate</td>
            <td>{{ $birthdate }}</td>
        </tr>
        <tr>
            <td class="label">Sex</td>
            <td>{{ $sex }}</td>
        </tr>
    </table>

    <div class="section">
        <h2>Course Preferences</h2>
        <ol>
            @foreach($courseLabels as $label)
                <li>{{ $label }}</li>
            @endforeach
        </ol>
    </div>

    <div class="section" style="margin-top: 32px;">
        <p style="color: #6b7280; font-size: 10px;">Exam schedule and room assignment will be provided after publication.</p>
    </div>
</body>
</html>
