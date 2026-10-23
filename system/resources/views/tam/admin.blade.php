<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NEXIAM - TAM Evaluation Responses &amp; Analytics</title>
  <link rel="icon" type="image/png" href="{{ asset('assets/branding/nexiam/nexiam-xiam-icon-512.png') }}" />
  <link rel="apple-touch-icon" href="{{ asset('assets/branding/nexiam/nexiam-xiam-icon-512.png') }}" />
  <link rel="shortcut icon" href="{{ asset('assets/branding/nexiam/nexiam-logo-only.png') }}" />
  <meta name="theme-color" content="#1e3a8a" />
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    :root {
      --primary: #1e3a8a;
      --primary-dark: #172554;
      --primary-light: #eff6ff;
      --accent: #2563eb;
      --accent-hover: #1d4ed8;
      --success: #059669;
      --success-bg: #ecfdf5;
      --warning: #d97706;
      --warning-bg: #fffbeb;
      --bg: #f8fafc;
      --card-bg: #ffffff;
      --text: #0f172a;
      --text-secondary: #475569;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --radius-lg: 14px;
      --radius-md: 8px;
      --radius-sm: 6px;
      --shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    body {
      background: var(--bg);
      color: var(--text);
      line-height: 1.5;
      padding: 24px 20px 60px;
    }

    .container {
      max-width: 1200px;
      margin: 0 auto;
    }

    /* Top Bar */
    .header-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 24px;
      flex-wrap: wrap;
      gap: 16px;
    }

    .header-title h1 {
      font-size: 1.5rem;
      font-weight: 800;
      color: var(--primary-dark);
      letter-spacing: -0.3px;
    }

    .header-title p {
      font-size: 0.88rem;
      color: var(--text-secondary);
      margin-top: 2px;
    }

    .header-actions {
      display: flex;
      gap: 10px;
      align-items: center;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      padding: 9px 16px;
      font-size: 0.88rem;
      font-weight: 600;
      border-radius: var(--radius-md);
      text-decoration: none;
      border: 1px solid transparent;
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .btn-primary {
      background: var(--primary);
      color: #fff;
    }
    .btn-primary:hover {
      background: var(--primary-dark);
    }

    .btn-success {
      background: var(--success);
      color: #fff;
    }
    .btn-success:hover {
      background: #047857;
    }

    .btn-outline {
      background: #fff;
      color: var(--text-secondary);
      border-color: var(--border);
    }
    .btn-outline:hover {
      background: #f1f5f9;
      color: var(--text);
    }

    /* Summary Stats Cards Grid */
    .stats-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
      gap: 16px;
      margin-bottom: 28px;
    }

    .stat-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      padding: 20px;
      box-shadow: var(--shadow);
    }

    .stat-label {
      font-size: 0.82rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: var(--text-muted);
      margin-bottom: 8px;
    }

    .stat-value {
      font-size: 1.85rem;
      font-weight: 800;
      color: var(--text);
      line-height: 1.2;
    }

    .stat-subtext {
      font-size: 0.82rem;
      color: var(--text-secondary);
      margin-top: 6px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .badge-interp {
      display: inline-block;
      font-size: 0.72rem;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 4px;
      background: var(--primary-light);
      color: var(--accent);
    }

    .progress-bar-wrap {
      width: 100%;
      height: 8px;
      background: #e2e8f0;
      border-radius: 999px;
      margin-top: 10px;
      overflow: hidden;
    }

    .progress-bar-fill {
      height: 100%;
      background: var(--accent);
      border-radius: 999px;
    }

    /* Section Headings */
    .section-card {
      background: var(--card-bg);
      border: 1px solid var(--border);
      border-radius: var(--radius-lg);
      box-shadow: var(--shadow);
      margin-bottom: 28px;
      overflow: hidden;
    }

    .section-header {
      padding: 16px 20px;
      border-bottom: 1px solid var(--border);
      display: flex;
      justify-content: space-between;
      align-items: center;
      background: #fafafa;
    }

    .section-header h2 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--text);
    }

    /* Tables */
    .table-wrap {
      overflow-x: auto;
    }

    table {
      width: 100%;
      border-collapse: collapse;
      font-size: 0.88rem;
    }

    th, td {
      padding: 12px 16px;
      border-bottom: 1px solid var(--border);
      text-align: left;
    }

    th {
      background: #f8fafc;
      font-weight: 700;
      color: var(--text-secondary);
      font-size: 0.8rem;
      text-transform: uppercase;
      letter-spacing: 0.4px;
    }

    tr:last-child td {
      border-bottom: none;
    }

    tr:hover td {
      background: #fbfcfd;
    }

    .code-pill {
      font-family: monospace;
      font-weight: 700;
      color: var(--primary);
      background: var(--primary-light);
      padding: 2px 6px;
      border-radius: 4px;
      font-size: 0.8rem;
    }

    .mean-badge {
      display: inline-block;
      font-weight: 800;
      font-size: 0.95rem;
      color: var(--text);
    }

    /* Distribution Pills Mini */
    .dist-bar {
      display: flex;
      align-items: center;
      gap: 6px;
      font-size: 0.75rem;
      color: var(--text-muted);
    }

    .dist-segment {
      padding: 2px 6px;
      border-radius: 3px;
      font-weight: 600;
    }

    .dist-5 { background: #dcfce7; color: #15803d; }
    .dist-4 { background: #e0f2fe; color: #0369a1; }
    .dist-3 { background: #fef3c7; color: #b45309; }
    .dist-2 { background: #fee2e2; color: #b91c1c; }
    .dist-1 { background: #f3f4f6; color: #4b5563; }

    /* Qualitative Cards Grid */
    .feedback-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
      gap: 16px;
      padding: 20px;
    }

    .feedback-card {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 16px;
    }

    .feedback-card h3 {
      font-size: 0.85rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      color: var(--text-secondary);
      margin-bottom: 8px;
    }

    .feedback-meta {
      display: flex;
      justify-content: space-between;
      font-size: 0.78rem;
      color: var(--text-muted);
      margin-top: 12px;
      padding-top: 8px;
      border-top: 1px dashed var(--border);
    }

    .isolation-notice {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-left: 4px solid var(--accent);
      padding: 12px 16px;
      border-radius: var(--radius-sm);
      font-size: 0.84rem;
      color: #1e40af;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      gap: 10px;
    }
  </style>
</head>
<body>

  <div class="container">
    <!-- Header -->
    <header class="header-bar">
      <div class="header-title">
        <h1>NEXIAM &bull; TAM Evaluation Response Management</h1>
        <p>Technology Acceptance Model (Davis, 1989) &bull; Evaluator Group: First-Year College Students</p>
      </div>
      <div class="header-actions">
        <a href="{{ route('tam.first-years') }}" target="_blank" class="btn btn-outline">
          &nearr; Open Student Form
        </a>
        <a href="{{ route('staff.tam-responses.export', ['key' => $adminKey]) }}" class="btn btn-success">
          <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
          Export CSV (SPSS / Excel)
        </a>
        <form method="POST" action="{{ route('tam.responses.reset') }}" onsubmit="return confirm('WARNING: Are you sure you want to clear all evaluation responses? This will reset the database for fresh student submissions.');" style="display:inline;">
          @csrf
          @if ($adminKey)
            <input type="hidden" name="key" value="{{ $adminKey }}">
          @endif
          <button type="submit" class="btn btn-outline" style="color: #b91c1c; border-color: #fca5a5;">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path></svg>
            Reset Responses
          </button>
        </form>
    </header>


    @if (session('status'))
      <div style="background: #ecfdf5; border-left: 4px solid #059669; padding: 12px 18px; border-radius: var(--radius-sm); margin-bottom: 20px; color: #065f46; font-size: 0.9rem; font-weight: 600;">
        {{ session('status') }}
      </div>
    @endif

    <!-- Isolation Notice -->
    <div class="isolation-notice">
      <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"/><path d="M7 11V7a5 5 0 0 1 10 0v4"/></svg>
      <div>
        <strong>Database Separation Active:</strong> Responses are stored exclusively in the dedicated <code>tam_evaluations.sqlite</code> database. Production PostgreSQL database is completely untouched.
      </div>
    </div>

    <!-- Summary Stats Grid -->
    <section class="stats-grid">
      <!-- Total Responses -->
      <div class="stat-card">
        <div class="stat-label">Total Submissions</div>
        <div class="stat-value">{{ $summary['total_responses'] }} <span style="font-size:1rem; font-weight:500; color:var(--text-muted);">/ {{ $summary['target_sample'] }}</span></div>
        <div class="stat-subtext">
          <span>Target Sample (Slovin $n={{ $summary['target_sample'] }}$, $N={{ $summary['population_total'] ?? 890 }}$): <strong>{{ $summary['progress_percent'] }}%</strong></span>
        </div>
        <div class="progress-bar-wrap">
          <div class="progress-bar-fill" style="width: {{ $summary['progress_percent'] }}%;"></div>
        </div>
        <div style="margin-top: 8px; font-size: 0.76rem; color: var(--text-muted); display: flex; justify-content: space-between;">
          <span>Surcharge Target: <strong>{{ $summary['surcharge_target'] ?? 290 }}+</strong> ({{ $summary['surcharge_progress_percent'] ?? 0 }}%)</span>
          <span style="color: #059669; font-weight: 600;">Oversampling Active</span>
        </div>
      </div>

      <!-- Overall TAM Mean -->
      <div class="stat-card">
        <div class="stat-label">Overall TAM Grand Mean</div>
        <div class="stat-value">
          {{ $summary['overall_mean'] !== null ? number_format($summary['overall_mean'], 2) : '0.00' }}
          @if ($summary['overall_sd'] !== null)
            <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-muted);">(SD: &plusmn;{{ number_format($summary['overall_sd'], 2) }})</span>
          @endif
        </div>
        <div class="stat-subtext">
          <span class="badge-interp">{{ $summary['overall_interpretation'] }}</span>
        </div>

      <!-- PU Mean -->
      <div class="stat-card">
        <div class="stat-label">Perceived Usefulness (STU-PU)</div>
        <div class="stat-value">
          {{ $summary['pu_mean'] !== null ? number_format($summary['pu_mean'], 2) : '0.00' }}
          @if ($summary['pu_sd'] !== null)
            <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-muted);">(SD: &plusmn;{{ number_format($summary['pu_sd'], 2) }})</span>
          @endif
        </div>
        <div class="stat-subtext">
          <span class="badge-interp">{{ $summary['pu_interpretation'] }}</span>
        </div>

      <!-- PEOU Mean -->
      <div class="stat-card">
        <div class="stat-label">Perceived Ease of Use (STU-PEOU)</div>
        <div class="stat-value">
          {{ $summary['peou_mean'] !== null ? number_format($summary['peou_mean'], 2) : '0.00' }}
          @if ($summary['peou_sd'] !== null)
            <span style="font-size: 0.88rem; font-weight: 600; color: var(--text-muted);">(SD: &plusmn;{{ number_format($summary['peou_sd'], 2) }})</span>
          @endif
        </div>
        <div class="stat-subtext">
          <span class="badge-interp">{{ $summary['peou_interpretation'] }}</span>
        </div>
    </section>

    <!-- Department Distribution Stratification -->
    <section class="section-card">
      <div class="section-header">
        <h2>Department Affiliation Breakdown (Target: CAS, CBME, CTE)</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">ISPSC Tagudin Campus &bull; First-Year Evaluator Stratification</span>
      </div>
      <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 14px; padding: 18px 20px;">
        <div style="background: #eff6ff; border: 1px solid #bfdbfe; border-radius: var(--radius-md); padding: 14px 16px;">
          <div style="font-size: 0.78rem; font-weight: 700; color: #1e40af; text-transform: uppercase;">College of Arts and Sciences</div>
          <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 4px;">
            <span style="font-size: 1.6rem; font-weight: 800; color: #1e3a8a;">{{ $departmentCounts['CAS'] }}</span>
            <span style="font-size: 0.82rem; font-weight: 600; color: #3b82f6;">CAS ({{ $summary['total_responses'] > 0 ? round(($departmentCounts['CAS'] / $summary['total_responses']) * 100, 1) : 0 }}%)</span>
          </div>
        </div>

        <div style="background: #f0fdf4; border: 1px solid #bbf7d0; border-radius: var(--radius-md); padding: 14px 16px;">
          <div style="font-size: 0.78rem; font-weight: 700; color: #166534; text-transform: uppercase;">Business Management &amp; Entrep.</div>
          <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 4px;">
            <span style="font-size: 1.6rem; font-weight: 800; color: #14532d;">{{ $departmentCounts['CBME'] }}</span>
            <span style="font-size: 0.82rem; font-weight: 600; color: #16a34a;">CBME ({{ $summary['total_responses'] > 0 ? round(($departmentCounts['CBME'] / $summary['total_responses']) * 100, 1) : 0 }}%)</span>
          </div>
        </div>

        <div style="background: #fefce8; border: 1px solid #fef08a; border-radius: var(--radius-md); padding: 14px 16px;">
          <div style="font-size: 0.78rem; font-weight: 700; color: #854d0e; text-transform: uppercase;">Teacher Education</div>
          <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 4px;">
            <span style="font-size: 1.6rem; font-weight: 800; color: #713f12;">{{ $departmentCounts['CTE'] }}</span>
            <span style="font-size: 0.82rem; font-weight: 600; color: #ca8a04;">CTE ({{ $summary['total_responses'] > 0 ? round(($departmentCounts['CTE'] / $summary['total_responses']) * 100, 1) : 0 }}%)</span>
          </div>
        </div>

        <div style="background: #f8fafc; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 14px 16px;">
          <div style="font-size: 0.78rem; font-weight: 700; color: var(--text-muted); text-transform: uppercase;">Other / Unspecified</div>
          <div style="display: flex; align-items: baseline; gap: 8px; margin-top: 4px;">
            <span style="font-size: 1.6rem; font-weight: 800; color: var(--text);">{{ $departmentCounts['Other'] + $departmentCounts['Unspecified'] }}</span>
            <span style="font-size: 0.82rem; font-weight: 600; color: var(--text-muted);">({{ $summary['total_responses'] > 0 ? round((($departmentCounts['Other'] + $departmentCounts['Unspecified']) / $summary['total_responses']) * 100, 1) : 0 }}%)</span>
          </div>
        </div>
      </div>
    </section>

    <!-- Academic Likert Scale Legend -->
    <div style="background: #ffffff; border: 1px solid var(--border); border-radius: var(--radius-md); padding: 12px 18px; margin-bottom: 24px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px; font-size: 0.82rem;">
      <span style="font-weight: 700; color: var(--primary);">Academic Likert Benchmark:</span>
      <div style="display: flex; gap: 8px; flex-wrap: wrap;">
        <span style="background: #ecfdf5; color: #065f46; padding: 3px 8px; border-radius: 4px; font-weight: 600;">4.50 – 5.00: Strongly Agree (Very High)</span>
        <span style="background: #eff6ff; color: #1e40af; padding: 3px 8px; border-radius: 4px; font-weight: 600;">3.50 – 4.49: Agree (High)</span>
        <span style="background: #fffbeb; color: #92400e; padding: 3px 8px; border-radius: 4px; font-weight: 600;">2.50 – 3.49: Neutral (Moderate)</span>
        <span style="background: #fef2f2; color: #991b1b; padding: 3px 8px; border-radius: 4px; font-weight: 600;">1.50 – 2.49: Disagree (Low)</span>
        <span style="background: #f3f4f6; color: #374151; padding: 3px 8px; border-radius: 4px; font-weight: 600;">1.00 – 1.49: Strongly Disagree (Very Low)</span>
      </div>
    </div>


    <!-- Likert Items Breakdown Table -->
    <section class="section-card">
      <div class="section-header">
        <h2>TAM Construct Indicator Breakdown (Likert 1 to 5)</h2>
        <span style="font-size: 0.8rem; color: var(--text-muted);">Scale: 5=Strongly Agree &bull; 1=Strongly Disagree</span>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 120px;">Code</th>
              <th style="width: 180px;">Construct</th>
              <th>Evaluation Indicator Statement</th>
              <th style="width: 60px; text-align: center;">$n$</th>
              <th style="width: 80px; text-align: center;">Mean</th>
              <th style="width: 75px; text-align: center;">SD</th>
              <th style="width: 200px;">Verbal Interpretation</th>
            </tr>
          </thead>
          <tbody>
            @foreach ($itemStats as $stat)
              <tr>
                <td><span class="code-pill">{{ $stat['code'] }}</span></td>
                <td><strong style="color: var(--text-secondary); font-size: 0.82rem;">{{ $stat['construct'] }}</strong></td>
                <td>{{ $stat['statement'] }}</td>
                <td style="text-align: center;">{{ $stat['count'] }}</td>
                <td style="text-align: center;"><span class="mean-badge">{{ $stat['mean'] !== null ? number_format($stat['mean'], 2) : '-' }}</span></td>
                <td style="text-align: center; color: var(--text-secondary); font-size: 0.84rem;">{{ $stat['sd'] !== null ? number_format($stat['sd'], 2) : '-' }}</td>
                <td><span class="badge-interp">{{ $stat['interpretation'] }}</span></td>
                  <div class="dist-bar">
                    <span class="dist-segment dist-5" title="Rated 5: {{ $stat['distribution'][5] }}">{{ $stat['distribution'][5] }}</span>
                    <span class="dist-segment dist-4" title="Rated 4: {{ $stat['distribution'][4] }}">{{ $stat['distribution'][4] }}</span>
                    <span class="dist-segment dist-3" title="Rated 3: {{ $stat['distribution'][3] }}">{{ $stat['distribution'][3] }}</span>
                    <span class="dist-segment dist-2" title="Rated 2: {{ $stat['distribution'][2] }}">{{ $stat['distribution'][2] }}</span>
                    <span class="dist-segment dist-1" title="Rated 1: {{ $stat['distribution'][1] }}">{{ $stat['distribution'][1] }}</span>
                  </div>
                </td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
    </section>

    <!-- Recent Submissions Table -->
    <section class="section-card">
      <div class="section-header">
        <h2>Individual Evaluation Submissions ({{ $evaluations->count() }})</h2>
        <a href="{{ route('staff.tam-responses.export', ['key' => $adminKey]) }}" class="btn btn-outline" style="font-size:0.8rem; padding: 4px 10px;">
          Download Full Dataset (.csv)
        </a>
      </div>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th style="width: 50px;">#ID</th>
              <th style="width: 110px;">Date</th>
              <th>Evaluator Name / Signature</th>
              <th style="width: 90px; text-align: center;">Dept</th>
              <th style="width: 160px;">Program</th>
              <th style="width: 80px; text-align: center;">Mean PU</th>
              <th style="width: 80px; text-align: center;">Mean PEOU</th>
              <th style="width: 90px; text-align: center;">Overall</th>
              <th style="width: 100px;">Video</th>
            </tr>
          <tbody>
            @forelse ($evaluations as $e)
              <tr>
                <td><strong>#{{ $e->id }}</strong></td>
                <td style="font-size: 0.8rem; color: var(--text-muted);">{{ $e->evaluation_date?->format('Y-m-d') ?? ($e->created_at?->format('Y-m-d') ?? '-') }}</td>
                <td>
                  <strong>{{ $e->evaluator_name ?: ($e->evaluator_signature ?: '(Anonymous Student)') }}</strong>
                  @if ($e->evaluator_name && $e->evaluator_signature && $e->evaluator_name !== $e->evaluator_signature)
                    <div style="font-size: 0.74rem; color: var(--text-muted);">Sig: {{ $e->evaluator_signature }}</div>
                  @endif
                </td>
                <td style="text-align: center;">
                  @if ($e->department)
                    <span class="code-pill" style="font-weight: 800; font-size: 0.78rem;">{{ $e->department }}</span>
                  @else
                    <span style="color: var(--text-muted); font-size: 0.78rem;">-</span>
                  @endif
                </td>
                <td style="font-size: 0.82rem; color: var(--text-secondary);">{{ $e->academic_program ?: '-' }}</td>
                <td style="text-align: center;"><span class="mean-badge">{{ $e->averagePu() !== null ? number_format($e->averagePu(), 2) : '-' }}</span></td>
                <td style="text-align: center;"><span class="mean-badge">{{ $e->averagePeou() !== null ? number_format($e->averagePeou(), 2) : '-' }}</span></td>
                <td style="text-align: center;"><span class="mean-badge" style="color: var(--accent);">{{ $e->overallScore() !== null ? number_format($e->overallScore(), 2) : '-' }}</span></td>
                <td>
                  @if ($e->video_watched_confirmed)
                    <span style="color: var(--success); font-weight: 700; font-size: 0.78rem;">&#10003; Watched</span>
                  @else
                    <span style="color: var(--text-muted); font-size: 0.78rem;">No</span>
                  @endif
                </td>
              </tr>
            @empty
              <tr>
                <td colspan="9" style="text-align: center; padding: 32px; color: var(--text-muted);">
                  No evaluation responses submitted yet. Evaluators can submit via the <a href="{{ route('tam.first-years') }}">evaluation portal</a>.
                </td>
              </tr>
            @endforelse
          </tbody>
        </table>
      </div>
    </section>

    <!-- Qualitative Feedback Highlights -->
    <section class="section-card">
      <div class="section-header">
        <h2>Qualitative Operational Observations &amp; Suggestions</h2>
      </div>
      <div class="feedback-grid">
        @php
          $qualitativeEntries = $evaluations->filter(fn($e) => !empty($e->useful_features) || !empty($e->difficulties_encountered) || !empty($e->recommendations));
        @endphp

        @forelse ($qualitativeEntries as $e)
          <div class="feedback-card">
            @if ($e->useful_features)
              <h3>Useful Features:</h3>
              <p style="font-size: 0.86rem; color: var(--text); margin-bottom: 10px;">{{ $e->useful_features }}</p>
            @endif

            @if ($e->difficulties_encountered)
              <h3 style="color: var(--warning);">Difficulties / Confusions:</h3>
              <p style="font-size: 0.86rem; color: var(--text); margin-bottom: 10px;">{{ $e->difficulties_encountered }}</p>
            @endif

            @if ($e->recommendations)
              <h3 style="color: var(--accent);">Recommendations:</h3>
              <p style="font-size: 0.86rem; color: var(--text); margin-bottom: 10px;">{{ $e->recommendations }}</p>
            @endif

            <div class="feedback-meta">
              <span>By: <strong>{{ $e->evaluator_signature ?: 'Anonymous Student' }}</strong></span>
              <span>{{ $e->created_at?->diffForHumans() }}</span>
            </div>
          </div>
        @empty
          <div style="grid-column: 1 / -1; text-align: center; padding: 24px; color: var(--text-muted); font-size: 0.88rem;">
            No written qualitative comments submitted yet.
          </div>
        @endforelse
      </div>
    </section>

  </div>

</body>
</html>
