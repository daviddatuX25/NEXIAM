<!DOCTYPE html>
<html lang="en" prefix="og: https://ogp.me/ns#">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>NEXIAM Evaluation Instrument | First-Year College Student &bull; ISPSC Tagudin</title>
  <meta name="description" content="Technology Acceptance Model (TAM) Evaluation for NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus." />
  <meta name="author" content="David Datu N. Sarmiento, Jaypee G. Pagaduan, Christine M. Lopez" />

  <!-- Favicon and App Icons -->
  <link rel="icon" type="image/png" href="{{ asset('assets/branding/nexiam/nexiam-xiam-icon-512.png') }}" />
  <link rel="apple-touch-icon" href="{{ asset('assets/branding/nexiam/nexiam-xiam-icon-512.png') }}" />
  <link rel="shortcut icon" href="{{ asset('assets/branding/nexiam/nexiam-logo-only.png') }}" />
  <link rel="image_src" href="{{ asset('assets/branding/nexiam/nexiam-tam-og-share.png') }}" />
  <meta itemprop="image" content="{{ asset('assets/branding/nexiam/nexiam-tam-og-share.png') }}" />
  <!-- Open Graph Meta Tags (Facebook, Messenger, WhatsApp, Viber, Discord, LinkedIn) -->
  <meta property="og:type" content="website" />
  <meta property="og:url" content="{{ url()->current() }}" />
  <meta property="og:title" content="NEXIAM Evaluation Instrument | First-Year College Students" />
  <meta property="og:description" content="Help evaluate NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus. Participate in our TAM research study." />
  <meta property="og:site_name" content="NEXIAM &bull; ISPSC Tagudin Campus" />
  <meta property="og:locale" content="en_PH" />

  <!-- Open Graph Primary High-Resolution Image (1200x630) -->
  <meta property="og:image" content="{{ asset('assets/branding/nexiam/nexiam-tam-og-share.png') }}" />
  <meta property="og:image:secure_url" content="{{ asset('assets/branding/nexiam/nexiam-tam-og-share.png') }}" />
  <meta property="og:image:type" content="image/png" />
  <meta property="og:image:width" content="1200" />
  <meta property="og:image:height" content="630" />
  <meta property="og:image:alt" content="NEXIAM TAM Evaluation with Xiam Mascot - ISPSC Tagudin Campus" />



  <!-- Twitter / X Card Meta Tags -->
  <meta name="twitter:card" content="summary_large_image" />
  <meta name="twitter:url" content="{{ url()->current() }}" />
  <meta name="twitter:title" content="NEXIAM Evaluation Instrument | First-Year College Students" />
  <meta name="twitter:description" content="Technology Acceptance Model (TAM) Evaluation for NEXIAM: A Role-Based College Admission Testing System at ISPSC Tagudin Campus." />
  <meta name="twitter:image" content="{{ asset('assets/branding/nexiam/nexiam-tam-og-share.png') }}" />
  <meta name="twitter:image:alt" content="NEXIAM TAM Evaluation with Xiam Mascot" />

  <!-- Theme Color for Mobile Browsers -->
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
      --warning-bg: #fffbeb;
      --warning-border: #f59e0b;
      --warning-text: #92400e;
      --bg: #f1f5f9;
      --card-bg: #ffffff;
      --text: #0f172a;
      --text-secondary: #475569;
      --text-muted: #64748b;
      --border: #e2e8f0;
      --radius-lg: 16px;
      --radius-md: 10px;
      --radius-sm: 6px;
      --shadow: 0 10px 30px -5px rgba(15, 23, 42, 0.08), 0 4px 12px -2px rgba(15, 23, 42, 0.04);
    }

    * {
      box-sizing: border-box;
      margin: 0;
      padding: 0;
      font-family: 'Inter', -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
    }

    body {
      background: linear-gradient(135deg, #e2e8f0 0%, #f8fafc 100%);
      color: var(--text);
      line-height: 1.6;
      min-height: 100vh;
      padding: 32px 16px 60px;
    }

    .app-shell {
      max-width: 900px;
      margin: 0 auto;
    }

    /* Top Banner & Header */
    .top-card {
      background: linear-gradient(135deg, var(--primary-dark) 0%, var(--primary) 60%, var(--accent) 100%);
      color: #ffffff;
      border-radius: var(--radius-lg) var(--radius-lg) 0 0;
      padding: 36px 32px 28px;
      text-align: center;
      position: relative;
      overflow: hidden;
    }

    .top-card::after {
      content: "";
      position: absolute;
      top: -50%;
      right: -20%;
      width: 380px;
      height: 380px;
      background: radial-gradient(circle, rgba(255,255,255,0.12) 0%, transparent 70%);
      border-radius: 50%;
      pointer-events: none;
    }

    .badge-role {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: rgba(255, 255, 255, 0.16);
      backdrop-filter: blur(6px);
      border: 1px solid rgba(255, 255, 255, 0.25);
      color: #fff;
      font-size: 0.78rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.8px;
      padding: 6px 14px;
      border-radius: 999px;
      margin-bottom: 14px;
    }

    .top-card h1 {
      font-size: 1.65rem;
      font-weight: 800;
      letter-spacing: -0.3px;
      line-height: 1.25;
      margin-bottom: 6px;
    }

    .top-card p {
      font-size: 0.95rem;
      color: rgba(255, 255, 255, 0.85);
      max-width: 600px;
      margin: 0 auto;
    }

    /* Success Alert */
    .alert-success {
      background: var(--success-bg);
      border: 1px solid #a7f3d0;
      border-left: 4px solid var(--success);
      color: #065f46;
      padding: 18px 22px;
      border-radius: var(--radius-md);
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      box-shadow: 0 4px 12px rgba(5, 150, 105, 0.1);
    }
    .alert-success strong {
      font-size: 1rem;
      display: block;
      margin-bottom: 2px;
    }
    .alert-success p {
      font-size: 0.88rem;
      color: #047857;
    }

    /* Stepper Navigation Bar */
    .stepper-bar {
      background: #ffffff;
      border-bottom: 1px solid var(--border);
      padding: 20px 32px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      position: relative;
    }

    .step-item {
      display: flex;
      align-items: center;
      gap: 10px;
      z-index: 2;
      background: #fff;
      padding: 0 8px;
    }

    .step-circle {
      width: 32px;
      height: 32px;
      border-radius: 50%;
      display: flex;
      align-items: center;
      justify-content: center;
      font-weight: 700;
      font-size: 0.85rem;
      background: #f1f5f9;
      color: var(--text-muted);
      border: 2px solid #cbd5e1;
      transition: all 0.25s ease;
    }

    .step-label {
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--text-muted);
      transition: color 0.25s ease;
    }

    .step-item.active .step-circle {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
      box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.15);
    }

    .step-item.active .step-label {
      color: var(--text);
      font-weight: 700;
    }

    .step-item.completed .step-circle {
      background: var(--success);
      border-color: var(--success);
      color: #fff;
    }

    .step-item.completed .step-label {
      color: var(--success);
    }

    .step-connector {
      flex: 1;
      height: 2px;
      background: var(--border);
      margin: 0 8px;
      position: relative;
      overflow: hidden;
    }

    .step-connector-fill {
      position: absolute;
      top: 0;
      left: 0;
      bottom: 0;
      width: 0%;
      background: var(--success);
      transition: width 0.35s ease;
    }

    /* Main Container Body */
    .main-card {
      background: var(--card-bg);
      border-radius: 0 0 var(--radius-lg) var(--radius-lg);
      box-shadow: var(--shadow);
      padding: 36px 32px;
    }

    .step-section {
      display: none;
    }

    .step-section.active {
      display: block;
      animation: slideFade 0.35s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @keyframes slideFade {
      from { opacity: 0; transform: translateY(10px); }
      to { opacity: 1; transform: translateY(0); }
    }

    .step-header {
      margin-bottom: 22px;
    }

    .step-header h2 {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 6px;
    }

    .step-header p {
      color: var(--text-secondary);
      font-size: 0.95rem;
    }

    /* Video Player Frame */
    .video-frame-card {
      background: #0f172a;
      border-radius: var(--radius-md);
      padding: 8px;
      box-shadow: 0 12px 24px -6px rgba(15, 23, 42, 0.25);
      margin-bottom: 22px;
    }

    .video-wrapper {
      position: relative;
      padding-bottom: 56.25%;
      height: 0;
      overflow: hidden;
      border-radius: 8px;
      background: #000;
    }

    .video-wrapper iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
      border: 0;
    }

    .video-fallback-bar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      padding: 10px 12px 4px;
      color: #94a3b8;
      font-size: 0.82rem;
    }

    .video-fallback-bar a {
      color: #60a5fa;
      text-decoration: none;
      font-weight: 500;
    }

    .video-fallback-bar a:hover {
      text-decoration: underline;
    }

    /* Interactive Check Card */
    .watch-check-card {
      display: flex;
      align-items: center;
      gap: 16px;
      background: var(--primary-light);
      border: 2px solid #bfdbfe;
      border-radius: var(--radius-md);
      padding: 18px 20px;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }

    .watch-check-card:hover {
      border-color: #93c5fd;
      background: #e0f2fe;
    }

    .watch-check-card.checked {
      background: var(--success-bg);
      border-color: #6ee7b7;
    }

    .watch-check-card input[type="checkbox"] {
      width: 22px;
      height: 22px;
      accent-color: var(--success);
      cursor: pointer;
      flex-shrink: 0;
    }

    .watch-check-text {
      flex: 1;
    }

    .watch-check-text strong {
      display: block;
      font-size: 0.98rem;
      color: var(--text);
      margin-bottom: 2px;
    }

    .watch-check-text span {
      font-size: 0.86rem;
      color: var(--text-secondary);
    }

    /* Step 2: Portal Exploration Card */
    .portal-showcase {
      background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
      border: 2px dashed #93c5fd;
      border-radius: var(--radius-lg);
      padding: 36px 28px;
      text-align: center;
      margin: 24px 0;
      position: relative;
    }

    .portal-icon-wrap {
      width: 64px;
      height: 64px;
      border-radius: 16px;
      background: var(--accent);
      color: #fff;
      display: flex;
      align-items: center;
      justify-content: center;
      margin: 0 auto 18px;
      box-shadow: 0 8px 20px -4px rgba(37, 99, 235, 0.45);
    }

    .portal-showcase h3 {
      font-size: 1.25rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 8px;
    }

    .portal-showcase p {
      color: var(--text-secondary);
      max-width: 520px;
      margin: 0 auto 22px;
      font-size: 0.94rem;
    }

    .portal-url-pill {
      display: inline-block;
      background: #ffffff;
      border: 1px solid var(--border);
      padding: 6px 14px;
      border-radius: 999px;
      font-family: monospace;
      font-size: 0.85rem;
      color: var(--primary);
      margin-bottom: 20px;
    }

    .portal-launch-btn {
      display: inline-flex;
      align-items: center;
      gap: 10px;
      background: var(--text);
      color: #ffffff;
      text-decoration: none;
      padding: 13px 26px;
      border-radius: var(--radius-md);
      font-weight: 600;
      font-size: 0.96rem;
      transition: all 0.2s ease;
      box-shadow: 0 4px 12px rgba(15, 23, 42, 0.18);
    }

    .portal-launch-btn:hover {
      background: var(--accent);
      transform: translateY(-2px);
      box-shadow: 0 8px 18px rgba(37, 99, 235, 0.3);
    }

    /* Step 3: Questionnaire Styles */
    .instruction-banner {
      display: flex;
      gap: 14px;
      align-items: flex-start;
      background: var(--warning-bg);
      border: 1px solid #fde68a;
      border-left: 4px solid var(--warning-border);
      color: var(--warning-text);
      padding: 16px 18px;
      border-radius: var(--radius-md);
      font-size: 0.9rem;
      margin-bottom: 24px;
    }

    .progress-sticky {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 14px 18px;
      margin-bottom: 28px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 16px;
      flex-wrap: wrap;
    }

    .progress-info {
      font-size: 0.88rem;
      font-weight: 600;
      color: var(--text-secondary);
    }

    .progress-info span {
      color: var(--accent);
      font-weight: 700;
    }

    .progress-track {
      flex: 1;
      min-width: 180px;
      height: 8px;
      background: #e2e8f0;
      border-radius: 999px;
      overflow: hidden;
    }

    .progress-bar-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, var(--accent), var(--success));
      border-radius: 999px;
      transition: width 0.3s ease;
    }

    .part-heading {
      display: flex;
      align-items: center;
      justify-content: space-between;
      border-bottom: 2px solid var(--primary);
      padding-bottom: 10px;
      margin: 32px 0 18px;
    }

    .part-heading h3 {
      font-size: 1.12rem;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: 0.2px;
    }

    .sub-construct {
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #f8fafc;
      border-left: 4px solid var(--accent);
      padding: 10px 14px;
      border-radius: 4px;
      margin: 22px 0 14px;
    }

    .sub-construct h4 {
      font-size: 0.98rem;
      font-weight: 700;
      color: var(--text);
    }

    .sub-construct span {
      font-size: 0.8rem;
      font-weight: 600;
      color: var(--text-muted);
    }

    /* Likert Table & Interactive Pills */
    .table-responsive {
      overflow-x: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      margin-bottom: 24px;
    }

    table.tam-table {
      width: 100%;
      border-collapse: collapse;
      min-width: 640px;
    }

    table.tam-table th,
    table.tam-table td {
      padding: 14px 12px;
      border-bottom: 1px solid var(--border);
      vertical-align: middle;
    }

    table.tam-table tbody tr:last-child td {
      border-bottom: none;
    }

    table.tam-table tbody tr:nth-child(even) {
      background-color: #fafaf9;
    }

    table.tam-table tbody tr:hover {
      background-color: #f1f5f9;
    }

    table.tam-table tbody tr.answered-row {
      background-color: #f0fdf4;
    }

    table.tam-table th {
      background: #f8fafc;
      font-size: 0.8rem;
      font-weight: 700;
      color: var(--text-secondary);
      text-align: center;
      border-bottom: 2px solid var(--border);
    }

    table.tam-table th.col-statement {
      text-align: left;
      width: 44%;
      font-size: 0.85rem;
    }

    .scale-num {
      display: block;
      font-size: 1rem;
      font-weight: 800;
      color: var(--primary);
      margin-bottom: 2px;
    }

    .item-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 4px;
    }

    .item-code {
      display: inline-block;
      font-size: 0.75rem;
      font-weight: 800;
      color: var(--accent);
      background: var(--primary-light);
      padding: 2px 8px;
      border-radius: 4px;
      letter-spacing: 0.4px;
    }

    .clear-choice-btn {
      background: none;
      border: none;
      font-size: 0.72rem;
      color: var(--text-muted);
      cursor: pointer;
      text-decoration: underline;
      display: none;
    }

    tr.answered-row .clear-choice-btn {
      display: inline-block;
    }

    .clear-choice-btn:hover {
      color: #dc2626;
    }

    .item-statement {
      font-size: 0.92rem;
      color: var(--text);
      font-weight: 500;
    }

    td.scale-cell {
      text-align: center;
      width: 11.2%;
    }

    /* Custom Radio Button Label Pill */
    .radio-pill {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      justify-content: center;
      width: 44px;
      height: 44px;
      border-radius: 10px;
      border: 2px solid #cbd5e1;
      background: #ffffff;
      cursor: pointer;
      transition: all 0.15s ease;
      position: relative;
      user-select: none;
    }

    .radio-pill:hover {
      border-color: var(--accent);
      background: var(--primary-light);
      transform: scale(1.05);
    }

    .radio-pill input[type="radio"] {
      position: absolute;
      opacity: 0;
      width: 0;
      height: 0;
    }

    .radio-pill span {
      font-size: 0.92rem;
      font-weight: 700;
      color: var(--text-secondary);
    }

    .radio-pill:has(input:checked) {
      background: var(--accent);
      border-color: var(--accent);
      box-shadow: 0 4px 10px rgba(37, 99, 235, 0.3);
      transform: scale(1.06);
    }

    .radio-pill:has(input:checked) span {
      color: #ffffff;
    }

    /* Qualitative Open Questions */
    .qualitative-grid {
      display: grid;
      gap: 20px;
      margin-top: 16px;
    }

    .field-card {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 18px 20px;
      transition: border-color 0.2s;
    }

    .field-card:focus-within {
      border-color: var(--accent);
      background: #ffffff;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .field-card label {
      display: block;
      font-weight: 700;
      font-size: 0.94rem;
      color: var(--text);
      margin-bottom: 10px;
    }

    .field-card textarea,
    .field-card input[type="text"] {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      font-size: 0.94rem;
      color: var(--text);
      background: #fff;
      outline: none;
      transition: border 0.2s;
    }

    .field-card textarea:focus,
    .field-card input[type="text"]:focus {
      border-color: var(--accent);
    }

    .field-card textarea {
      min-height: 95px;
      resize: vertical;
    }

    /* Part I & Part II Profile & Consent Styles */
    .consent-card {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: var(--radius-md);
      padding: 18px 20px;
      margin-bottom: 20px;
    }
    .consent-title {
      display: flex;
      align-items: center;
      gap: 8px;
      font-weight: 700;
      color: var(--primary);
      margin-bottom: 8px;
      font-size: 0.95rem;
    }
    .consent-card p {
      font-size: 0.88rem;
      color: #1e3a8a;
      line-height: 1.6;
    }

    .profile-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
      gap: 16px;
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 20px;
      margin-bottom: 28px;
    }
    .profile-field label {
      display: block;
      font-weight: 700;
      font-size: 0.88rem;
      color: var(--text);
      margin-bottom: 6px;
    }
    .profile-field input[type="text"],
    .profile-field input[type="date"] {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-sm);
      padding: 10px 14px;
      font-size: 0.92rem;
      color: var(--text);
      background: #fff;
      outline: none;
      transition: border 0.2s;
    }
    .profile-field input:focus {
      border-color: var(--accent);
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
    }

    .dept-options {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
      gap: 10px;
      margin-top: 6px;
    }
    .dept-pill {
      display: flex;
      align-items: center;
      gap: 12px;
      background: #fff;
      border: 2px solid #cbd5e1;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      cursor: pointer;
      transition: all 0.2s ease;
      user-select: none;
    }
    .dept-pill:hover {
      border-color: var(--accent);
      background: var(--primary-light);
    }
    .dept-pill:has(input:checked) {
      border-color: var(--accent);
      background: #eff6ff;
      box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.15);
    }
    .dept-pill input[type="radio"] {
      accent-color: var(--accent);
      width: 18px;
      height: 18px;
      flex-shrink: 0;
    }
    .dept-content strong {
      display: block;
      font-size: 0.95rem;
      color: var(--primary);
      line-height: 1.2;
    }
    .dept-content span {
      display: block;
      font-size: 0.78rem;
      color: var(--text-secondary);
      margin-top: 2px;
    }

    .benchmark-card {
      background: #fafaf9;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 20px;
      margin-bottom: 28px;
    }
    .benchmark-scale-grid {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
      gap: 8px;
      margin: 14px 0;
    }
    .scale-benchmark-item {
      background: #fff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 8px 10px;
      font-size: 0.82rem;
      text-align: center;
      color: var(--text-secondary);
    }
    .scale-benchmark-item strong {
      color: var(--primary);
      font-size: 0.95rem;
      display: block;
    }
    .benchmark-note {
      background: var(--warning-bg);
      border-left: 3px solid var(--warning-border);
      padding: 10px 14px;
      border-radius: 4px;
      font-size: 0.85rem;
      color: var(--warning-text);
      line-height: 1.5;
    }

    /* Footer Buttons */
    .nav-footer {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin-top: 32px;
      padding-top: 20px;
      border-top: 1px solid var(--border);
      flex-wrap: wrap;
    }

    .btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      padding: 12px 24px;
      font-size: 0.95rem;
      font-weight: 600;
      border-radius: var(--radius-md);
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-primary {
      background: var(--accent);
      color: #ffffff;
      box-shadow: 0 4px 12px rgba(37, 99, 235, 0.25);
    }

    .btn-primary:hover:not(:disabled) {
      background: var(--accent-hover);
      transform: translateY(-1px);
    }

    .btn-primary:disabled {
      background: #cbd5e1;
      color: #64748b;
      box-shadow: none;
      cursor: not-allowed;
    }

    .btn-secondary {
      background: #f1f5f9;
      color: var(--text-secondary);
      border: 1px solid var(--border);
    }

    .btn-secondary:hover {
      background: #e2e8f0;
      color: var(--text);
    }

    .btn-outline {
      background: #ffffff;
      color: var(--primary);
      border: 1px solid #bfdbfe;
    }

    .btn-outline:hover {
      background: var(--primary-light);
    }

    .thank-you-footer {
      text-align: center;
      margin-top: 24px;
      font-size: 0.86rem;
      color: var(--text-muted);
      font-weight: 500;
    }

    @media (max-width: 640px) {
      .top-card, .main-card {
        padding: 24px 18px;
      }
      .stepper-bar {
        padding: 16px 14px;
      }
      .step-label {
        display: none;
      }
      .nav-footer {
        flex-direction: column-reverse;
        align-items: stretch;
      }
      .nav-footer .btn {
        width: 100%;
      }
    }
    /* Floating Circle Email Support Widget */
    .fab-container {
      position: fixed;
      bottom: 24px;
      right: 24px;
      z-index: 9999;
      display: flex;
      flex-direction: column;
      align-items: flex-end;
    }

    .fab-btn {
      width: 58px;
      height: 58px;
      border-radius: 50%;
      background: linear-gradient(135deg, var(--primary) 0%, var(--accent) 100%);
      color: #ffffff;
      border: none;
      cursor: pointer;
      display: flex;
      align-items: center;
      justify-content: center;
      box-shadow: 0 10px 25px -4px rgba(37, 99, 235, 0.45), 0 4px 10px -2px rgba(15, 23, 42, 0.15);
      transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
      position: relative;
    }

    .fab-btn:hover {
      transform: translateY(-3px) scale(1.06);
      box-shadow: 0 14px 30px -4px rgba(37, 99, 235, 0.55), 0 6px 14px -2px rgba(15, 23, 42, 0.2);
    }

    .fab-pulse-dot {
      position: absolute;
      top: 2px;
      right: 2px;
      width: 13px;
      height: 13px;
      border-radius: 50%;
      background: #10b981;
      border: 2px solid #ffffff;
    }

    .fab-pulse-dot::after {
      content: '';
      position: absolute;
      top: -2px;
      left: -2px;
      width: 13px;
      height: 13px;
      border-radius: 50%;
      background: #10b981;
      animation: fabPulse 2s infinite ease-out;
      opacity: 0.7;
    }

    @keyframes fabPulse {
      0% { transform: scale(1); opacity: 0.8; }
      100% { transform: scale(2.4); opacity: 0; }
    }

    /* Support Popup Card */
    .support-modal {
      display: none;
      position: fixed;
      bottom: 84px;
      right: 20px;
      width: 380px;
      max-width: calc(100vw - 32px);
      max-height: min(560px, calc(100vh - 96px), calc(100dvh - 96px));
      overflow-y: auto;
      overscroll-behavior: contain;
      -webkit-overflow-scrolling: touch;
      background: #ffffff;
      border-radius: var(--radius-lg);
      box-shadow: 0 20px 40px -10px rgba(15, 23, 42, 0.25), 0 8px 16px -4px rgba(15, 23, 42, 0.1);
      border: 1px solid var(--border);
      z-index: 10000;
      padding: 20px 18px;
      animation: fabSlideUp 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    }

    @media (max-height: 700px) {
      .support-modal {
        bottom: 74px;
        max-height: calc(100vh - 84px);
        padding: 16px 14px;
      }
    }

    @media (max-width: 480px) {
      .support-modal {
        right: 12px;
        left: 12px;
        width: auto;
        max-width: calc(100vw - 24px);
        bottom: 78px;
        max-height: calc(100dvh - 90px);
        padding: 18px 16px;
      }
      .fab-container {
        bottom: 16px;
        right: 16px;
      }
    }

    .support-modal.active {
      display: block;
    }

    @keyframes fabSlideUp {
      from { opacity: 0; transform: translateY(12px) scale(0.97); }
      to { opacity: 1; transform: translateY(0) scale(1); }
    }

    .support-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 14px;
      padding-bottom: 12px;
      border-bottom: 1px solid var(--border);
    }

    .support-header h3 {
      font-size: 1.02rem;
      font-weight: 800;
      color: var(--primary);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .support-close-btn {
      background: none;
      border: none;
      font-size: 1.35rem;
      color: var(--text-muted);
      cursor: pointer;
      line-height: 1;
      padding: 4px;
      border-radius: 4px;
    }

    .support-close-btn:hover {
      color: var(--text);
      background: #f1f5f9;
    }

    .email-display-card {
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: var(--radius-md);
      padding: 12px 14px;
      margin-bottom: 16px;
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 10px;
    }

    .email-text-wrap {
      overflow: hidden;
    }

    .email-label {
      font-size: 0.72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.4px;
      color: var(--accent);
      display: block;
    }

    .email-address {
      font-family: monospace;
      font-size: 0.85rem;
      font-weight: 700;
      color: var(--primary-dark);
      word-break: break-all;
    }

    .btn-copy-email {
      background: #ffffff;
      border: 1px solid #93c5fd;
      color: var(--accent);
      padding: 6px 10px;
      border-radius: var(--radius-sm);
      font-size: 0.75rem;
      font-weight: 700;
      cursor: pointer;
      flex-shrink: 0;
      transition: all 0.15s ease;
    }

    .btn-copy-email:hover {
      background: var(--accent);
      color: #ffffff;
    }

    .topic-selection-title {
      font-size: 0.82rem;
      font-weight: 700;
      color: var(--text-secondary);
      margin-bottom: 8px;
      display: block;
    }

    .topic-chips {
      display: flex;
      flex-direction: column;
      gap: 6px;
      margin-bottom: 14px;
    }

    .topic-chip {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-sm);
      padding: 8px 12px;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text);
      cursor: pointer;
      text-align: left;
      transition: all 0.15s ease;
      display: flex;
      align-items: center;
      justify-content: space-between;
    }

    .topic-chip:hover {
      border-color: var(--accent);
      background: var(--primary-light);
      color: var(--accent);
    }

    .topic-chip.selected {
      border-color: var(--accent);
      background: #eff6ff;
      color: var(--accent);
      font-weight: 700;
    }

    .support-field {
      margin-bottom: 12px;
    }

    .support-field label {
      display: block;
      font-size: 0.78rem;
      font-weight: 700;
      color: var(--text-secondary);
      margin-bottom: 4px;
    }

    .support-field input,
    .support-field textarea {
      width: 100%;
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-sm);
      padding: 8px 10px;
      font-size: 0.84rem;
      color: var(--text);
      outline: none;
      font-family: inherit;
    }

    .support-field input:focus,
    .support-field textarea:focus {
      border-color: var(--accent);
    }

    .support-field textarea {
      min-height: 65px;
      resize: vertical;
    }

    .support-actions {
      display: flex;
      flex-direction: column;
      gap: 8px;
      margin-top: 14px;
    }

    .btn-send-email {
      background: var(--accent);
      color: #ffffff;
      padding: 10px 14px;
      border-radius: var(--radius-md);
      font-size: 0.88rem;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 8px;
      border: none;
      cursor: pointer;
      transition: all 0.2s ease;
    }

    .btn-send-email:hover {
      background: var(--accent-hover);
    }

    .btn-copy-all {
      background: #f1f5f9;
      color: var(--text-secondary);
      padding: 8px 12px;
      border-radius: var(--radius-md);
      font-size: 0.8rem;
      font-weight: 600;
      text-align: center;
      border: 1px solid var(--border);
      cursor: pointer;
      transition: all 0.15s ease;
    }

    .btn-copy-all:hover {
      background: #e2e8f0;
      color: var(--text);
    }

    /* Official Authority Pill & Modal */
    .btn-authority {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      background: rgba(255, 255, 255, 0.16);
      border: 1px solid rgba(255, 255, 255, 0.35);
      color: #ffffff;
      padding: 7px 18px;
      border-radius: 999px;
      font-size: 0.82rem;
      font-weight: 600;
      cursor: pointer;
      backdrop-filter: blur(8px);
      transition: all 0.2s ease;
      text-decoration: none;
      margin-top: 14px;
    }
    .btn-authority:hover {
      background: rgba(255, 255, 255, 0.28);
      border-color: rgba(255, 255, 255, 0.55);
      transform: translateY(-1px);
      color: #ffffff;
    }
    .conduct-modal {
      position: fixed;
      top: 0;
      left: 0;
      width: 100vw;
      height: 100vh;
      background: rgba(15, 23, 42, 0.78);
      backdrop-filter: blur(5px);
      z-index: 99999;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 16px;
    }
    .conduct-modal-dialog {
      background: #ffffff;
      border-radius: var(--radius-lg);
      max-width: 680px;
      width: 100%;
      max-height: 94vh;
      display: flex;
      flex-direction: column;
      box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.4);
      overflow: hidden;
    }
    .conduct-modal-header {
      padding: 16px 22px;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #ffffff;
    }
    .conduct-modal-header h3 {
      font-size: 1.12rem;
      font-weight: 700;
      color: var(--text);
    }
    .conduct-modal-header p {
      font-size: 0.82rem;
      color: var(--text-muted);
      margin-top: 2px;
    }
    .btn-close-modal {
      background: none;
      border: none;
      font-size: 1.6rem;
      cursor: pointer;
      color: var(--text-muted);
      line-height: 1;
      padding: 4px 8px;
      transition: color 0.15s ease;
    }
    .btn-close-modal:hover {
      color: var(--text);
    }
    .conduct-modal-body {
      padding: 16px;
      overflow-y: auto;
      text-align: center;
      background: #f8fafc;
    }
    .conduct-modal-footer {
      padding: 12px 22px;
      border-top: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: space-between;
      background: #ffffff;
    }

    /* ================= STEP 1: WELCOME & RESEARCH CONTEXT ================= */
    .welcome-card-hero {
      background: linear-gradient(135deg, #ffffff 0%, #f0f7ff 100%);
      border: 1px solid #bfdbfe;
      border-radius: var(--radius-lg);
      padding: 28px 24px;
      margin-bottom: 28px;
      box-shadow: 0 8px 24px -4px rgba(30, 58, 138, 0.08);
      position: relative;
      overflow: hidden;
    }

    .welcome-mascot-row {
      display: flex;
      align-items: center;
      gap: 24px;
    }

    @media (max-width: 640px) {
      .welcome-mascot-row {
        flex-direction: column;
        text-align: center;
      }
    }

    .xiam-mascot-wrap {
      flex-shrink: 0;
      position: relative;
      width: 130px;
      height: 130px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle, rgba(191, 219, 254, 0.6) 0%, rgba(239, 246, 255, 0.25) 70%, transparent 100%);
      border-radius: 50%;
    }

    .xiam-mascot-img {
      width: 110px;
      height: 110px;
      object-fit: contain;
      filter: drop-shadow(0 8px 16px rgba(37, 99, 235, 0.25));
      animation: xiamFloat 3.2s ease-in-out infinite, xiamGentleBounce 6s ease-in-out infinite;
      transform-origin: bottom center;
    }

    @keyframes xiamFloat {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-7px); }
    }

    @keyframes xiamGentleBounce {
      0%, 100% { rotate: 0deg; }
      25% { rotate: -2.5deg; }
      75% { rotate: 2.5deg; }
    }

    .xiam-speech-card {
      flex: 1;
    }

    .xiam-badge-pill {
      display: inline-flex;
      align-items: center;
      gap: 6px;
      background: #dbeafe;
      color: #1e40af;
      font-size: 0.74rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.6px;
      padding: 4px 10px;
      border-radius: 999px;
      margin-bottom: 8px;
    }

    .xiam-badge-dot {
      width: 7px;
      height: 7px;
      border-radius: 50%;
      background: #2563eb;
      animation: pulseDot 1.8s infinite;
    }

    @keyframes pulseDot {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.4; transform: scale(1.3); }
    }

    .xiam-speech-card h2 {
      font-size: 1.25rem;
      font-weight: 800;
      color: var(--primary-dark);
      line-height: 1.3;
      margin-bottom: 8px;
    }

    .xiam-speech-card p {
      font-size: 0.92rem;
      color: var(--text-secondary);
      line-height: 1.55;
      margin-bottom: 8px;
    }

    .xiam-highlight-box {
      background: #ffffff;
      border-left: 3px solid var(--accent);
      padding: 10px 14px;
      border-radius: 0 var(--radius-sm) var(--radius-sm) 0;
      margin-top: 10px;
      font-size: 0.86rem;
      color: #1e293b;
      box-shadow: 0 1px 3px rgba(0,0,0,0.04);
    }

    /* Section Headings */
    .context-block-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      gap: 12px;
      margin: 28px 0 16px;
      padding-bottom: 8px;
      border-bottom: 2px solid #e2e8f0;
    }

    .context-block-title {
      font-size: 1.05rem;
      font-weight: 700;
      color: var(--text);
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .context-block-subtitle {
      font-size: 0.8rem;
      color: var(--text-muted);
    }

    /* Researchers Grid */
    .researchers-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 20px;
    }

    @media (max-width: 768px) {
      .researchers-grid {
        grid-template-columns: 1fr;
      }
    }

    .researcher-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 20px 16px;
      text-align: center;
      transition: all 0.25s ease;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
      display: flex;
      flex-direction: column;
      align-items: center;
      position: relative;
    }

    .researcher-card:hover {
      transform: translateY(-3px);
      box-shadow: 0 8px 20px -4px rgba(30, 58, 138, 0.12);
      border-color: #93c5fd;
    }

    .researcher-avatar-frame {
      width: 82px;
      height: 82px;
      border-radius: 50%;
      border: 3px solid #eff6ff;
      box-shadow: 0 4px 10px rgba(37, 99, 235, 0.18);
      overflow: hidden;
      margin-bottom: 12px;
      background: #f1f5f9;
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .researcher-avatar-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
    }

    .researcher-name {
      font-size: 0.98rem;
      font-weight: 700;
      color: var(--text);
      margin-bottom: 4px;
      line-height: 1.25;
    }

    .researcher-role-tag {
      display: inline-block;
      font-size: 0.72rem;
      font-weight: 700;
      color: #1e40af;
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: 999px;
      padding: 2px 8px;
      margin-bottom: 6px;
    }

    .researcher-desc {
      font-size: 0.78rem;
      color: var(--text-muted);
      line-height: 1.4;
    }

    /* Academic Leadership & Supervision Strip */
    .adviser-strip {
      background: #f8fafc;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px 16px;
      margin-bottom: 24px;
      display: flex;
      align-items: center;
      justify-content: space-around;
      gap: 16px;
      flex-wrap: wrap;
      font-size: 0.8rem;
      color: var(--text-secondary);
    }

    .adviser-item {
      display: flex;
      align-items: center;
      gap: 8px;
    }

    .adviser-item strong {
      color: var(--text);
    }

    /* Official Documents Cards */
    .docs-prior-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 16px;
      margin-bottom: 28px;
    }

    @media (max-width: 768px) {
      .docs-prior-grid {
        grid-template-columns: 1fr;
      }
    }

    .doc-preview-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      overflow: hidden;
      display: flex;
      flex-direction: column;
      transition: all 0.25s ease;
      cursor: pointer;
      box-shadow: 0 2px 6px rgba(0,0,0,0.03);
    }

    .doc-preview-card:hover {
      border-color: var(--accent);
      transform: translateY(-2px);
      box-shadow: 0 8px 18px -4px rgba(37, 99, 235, 0.15);
    }

    .doc-thumb-wrap {
      height: 140px;
      background: #f1f5f9;
      position: relative;
      overflow: hidden;
      border-bottom: 1px solid var(--border);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .doc-thumb-img {
      width: 100%;
      height: 100%;
      object-fit: cover;
      object-position: top center;
      transition: transform 0.3s ease;
    }

    .doc-preview-card:hover .doc-thumb-img {
      transform: scale(1.04);
    }

    .doc-thumb-overlay {
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, transparent 40%, rgba(15, 23, 42, 0.6) 100%);
      display: flex;
      align-items: flex-end;
      padding: 8px 12px;
      color: #fff;
      font-size: 0.72rem;
      font-weight: 600;
    }

    .doc-card-body {
      padding: 12px 14px 14px;
      display: flex;
      flex-direction: column;
      flex: 1;
    }

    .doc-card-badge {
      font-size: 0.68rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: 0.5px;
      color: #047857;
      background: #ecfdf5;
      padding: 2px 6px;
      border-radius: 4px;
      align-self: flex-start;
      margin-bottom: 6px;
    }

    .doc-card-title {
      font-size: 0.88rem;
      font-weight: 700;
      color: var(--text);
      line-height: 1.3;
      margin-bottom: 4px;
    }

    .doc-card-meta {
      font-size: 0.76rem;
      color: var(--text-muted);
      line-height: 1.35;
      margin-bottom: 10px;
      flex: 1;
    }

    .doc-card-btn {
      display: inline-flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
      font-size: 0.78rem;
      font-weight: 600;
      color: var(--accent);
      background: #eff6ff;
      border: 1px solid #bfdbfe;
      border-radius: var(--radius-sm);
      padding: 6px 10px;
      transition: all 0.2s ease;
    }

    .doc-preview-card:hover .doc-card-btn {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }

    /* Video Watching Progress and Gating */
    .video-watch-progress-wrap {
      background: #f8fafc;
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-md);
      padding: 14px 16px;
      margin-bottom: 18px;
    }

    .video-watch-progress-top {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 8px;
      font-size: 0.84rem;
      color: var(--text);
      font-weight: 600;
    }

    .video-progress-track {
      height: 8px;
      background: #e2e8f0;
      border-radius: 999px;
      overflow: hidden;
      position: relative;
    }

    .video-progress-fill {
      height: 100%;
      width: 0%;
      background: linear-gradient(90deg, var(--accent) 0%, #3b82f6 100%);
      border-radius: 999px;
      transition: width 0.4s ease, background 0.3s ease;
    }

    .video-progress-fill.completed {
      background: linear-gradient(90deg, #10b981 0%, #059669 100%);
    }

    .video-watch-status-hint {
      margin-top: 6px;
      font-size: 0.78rem;
      color: var(--text-muted);
      display: flex;
      align-items: center;
      gap: 6px;
    }

    .watch-check-card.locked {
      background: #f8fafc;
      border-color: #cbd5e1;
      opacity: 0.85;
      cursor: not-allowed;
    }

    .watch-check-card.unlocked {
      background: #ecfdf5;
      border-color: #34d399;
      cursor: pointer;
      box-shadow: 0 0 0 3px rgba(16, 185, 129, 0.15);
    }

    /* Tabbed Document Modal */
    .doc-modal-tabs {
      display: flex;
      background: #f1f5f9;
      border-bottom: 1px solid var(--border);
      padding: 8px 16px 0;
      gap: 6px;
      overflow-x: auto;
    }

    .doc-tab-btn {
      padding: 9px 16px;
      font-size: 0.82rem;
      font-weight: 600;
      color: var(--text-secondary);
      background: transparent;
      border: 1px solid transparent;
      border-bottom: none;
      border-radius: 8px 8px 0 0;
      cursor: pointer;
      display: flex;
      align-items: center;
      gap: 6px;
      transition: all 0.2s ease;
      white-space: nowrap;
    }

    .doc-tab-btn:hover {
      color: var(--text);
      background: rgba(255, 255, 255, 0.6);
    }

    .doc-tab-btn.active {
      color: var(--primary);
      background: #ffffff;
      border-color: var(--border);
      border-bottom: 2px solid #ffffff;
      margin-bottom: -1px;
    }

    .doc-tab-pane {
      display: none;
    }

    .doc-tab-pane.active {
      display: block;
    }

    .glow-pulse {
      animation: btnGlow 2s infinite alternate;
    }

    @keyframes btnGlow {
      0% { box-shadow: 0 0 0 0 rgba(37, 99, 235, 0.4); }
      100% { box-shadow: 0 0 0 10px rgba(37, 99, 235, 0); }
    }

    /* ================= SHARE CARD & XIAM PLEADING BANNER ================= */
    .xiam-plead-card {
      background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 50%, #eff6ff 100%);
      border: 2px solid #86efac;
      border-radius: var(--radius-lg);
      padding: 24px;
      margin-top: 20px;
      box-shadow: 0 10px 25px -5px rgba(5, 150, 105, 0.12);
      position: relative;
      overflow: hidden;
    }

    .xiam-plead-row {
      display: flex;
      align-items: center;
      gap: 20px;
    }

    @media (max-width: 640px) {
      .xiam-plead-row {
        flex-direction: column;
        text-align: center;
      }
    }

    .xiam-plead-wrap {
      flex-shrink: 0;
      width: 105px;
      height: 105px;
      display: flex;
      align-items: center;
      justify-content: center;
      background: radial-gradient(circle, rgba(167, 243, 208, 0.5) 0%, rgba(209, 250, 229, 0.2) 70%, transparent 100%);
      border-radius: 50%;
    }

    .xiam-plead-img {
      width: 95px;
      height: 95px;
      object-fit: contain;
      filter: drop-shadow(0 6px 12px rgba(5, 150, 105, 0.25));
      animation: xiamPlead 2.4s ease-in-out infinite;
      transform-origin: bottom center;
    }

    @keyframes xiamPlead {
      0%, 100% { transform: translateY(0) scale(1); }
      50% { transform: translateY(-6px) scale(1.04); }
    }

    .xiam-plead-content {
      flex: 1;
    }

    .xiam-plead-title {
      font-size: 1.15rem;
      font-weight: 800;
      color: #065f46;
      margin-bottom: 6px;
      line-height: 1.3;
    }

    .xiam-plead-text {
      font-size: 0.88rem;
      color: #047857;
      line-height: 1.5;
      margin-bottom: 14px;
    }

    .xiam-share-btn-group {
      display: flex;
      gap: 10px;
      flex-wrap: wrap;
    }

    @media (max-width: 640px) {
      .xiam-share-btn-group {
        justify-content: center;
      }
    }

    .btn-share-messenger {
      background: linear-gradient(135deg, #00B2FF 0%, #006AFF 100%);
      color: #ffffff;
      border: none;
      padding: 9px 16px;
      border-radius: var(--radius-sm);
      font-weight: 700;
      font-size: 0.84rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(0, 106, 255, 0.25);
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-share-messenger:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(0, 106, 255, 0.35);
    }

    .btn-share-fb {
      background: #1877F2;
      color: #ffffff;
      border: none;
      padding: 9px 16px;
      border-radius: var(--radius-sm);
      font-weight: 700;
      font-size: 0.84rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      box-shadow: 0 4px 12px rgba(24, 119, 242, 0.25);
      transition: all 0.2s ease;
      text-decoration: none;
    }

    .btn-share-fb:hover {
      transform: translateY(-1px);
      box-shadow: 0 6px 16px rgba(24, 119, 242, 0.35);
    }

    .btn-copy-snippet {
      background: #ffffff;
      color: #1e40af;
      border: 1.5px solid #93c5fd;
      padding: 9px 16px;
      border-radius: var(--radius-sm);
      font-weight: 700;
      font-size: 0.84rem;
      cursor: pointer;
      display: inline-flex;
      align-items: center;
      gap: 8px;
      transition: all 0.2s ease;
    }

    .btn-copy-snippet:hover {
      background: #eff6ff;
      border-color: #3b82f6;
      transform: translateY(-1px);
    }

    /* ================= DEPARTMENT TRACTION & MILESTONE METER ================= */
    .dept-traction-card {
      background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
      border: 1px solid #cbd5e1;
      border-radius: var(--radius-md);
      padding: 22px 20px;
      margin-top: 24px;
      box-shadow: 0 4px 14px rgba(0, 0, 0, 0.04);
      text-align: left;
    }

    .dept-traction-header {
      display: flex;
      align-items: center;
      gap: 16px;
      margin-bottom: 16px;
    }

    @media (max-width: 640px) {
      .dept-traction-header {
        flex-direction: column;
        text-align: center;
      }
    }

    .sad-xiam-wrap {
      flex-shrink: 0;
      width: 90px;
      height: 90px;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(254, 205, 211, 0.5) 0%, rgba(254, 242, 242, 0.2) 70%, transparent 100%);
      display: flex;
      align-items: center;
      justify-content: center;
    }

    .sad-xiam-img {
      width: 82px;
      height: 82px;
      object-fit: contain;
      filter: drop-shadow(0 4px 10px rgba(225, 29, 72, 0.2));
      animation: xiamSadBounce 3s ease-in-out infinite;
    }

    @keyframes xiamSadBounce {
      0%, 100% { transform: translateY(0); }
      50% { transform: translateY(-5px); }
    }

    .dept-traction-bubble {
      flex: 1;
    }

    .dept-traction-bubble h3 {
      font-size: 1.1rem;
      font-weight: 800;
      color: #1e293b;
      margin-bottom: 4px;
      display: flex;
      align-items: center;
      gap: 6px;
    }

    @media (max-width: 640px) {
      .dept-traction-bubble h3 {
        justify-content: center;
      }
    }

    .dept-traction-bubble p {
      font-size: 0.86rem;
      color: var(--text-secondary);
      line-height: 1.45;
    }

    .dept-bars-grid {
      display: grid;
      grid-template-columns: repeat(3, 1fr);
      gap: 12px;
      margin: 16px 0;
    }

    @media (max-width: 768px) {
      .dept-bars-grid {
        grid-template-columns: 1fr;
      }
    }

    .dept-bar-card {
      background: #ffffff;
      border: 1px solid var(--border);
      border-radius: var(--radius-sm);
      padding: 12px 14px;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    .dept-bar-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      margin-bottom: 6px;
    }

    .dept-bar-tag {
      font-weight: 700;
      font-size: 0.82rem;
      color: var(--text);
    }

    .dept-bar-quota {
      font-size: 0.72rem;
      font-weight: 700;
      color: var(--text-muted);
      background: #e2e8f0;
      padding: 2px 6px;
      border-radius: 4px;
    }

    .dept-progress-track {
      height: 8px;
      background: #e2e8f0;
      border-radius: 999px;
      overflow: hidden;
      margin: 6px 0 4px;
    }

    .dept-progress-fill {
      height: 100%;
      border-radius: 999px;
      transition: width 0.5s ease;
    }

    .dept-bar-counts {
      display: flex;
      justify-content: space-between;
      font-size: 0.76rem;
      color: var(--text-secondary);
    }
  </style>
</head>
<body>

  <div class="app-shell">
    <!-- Top Academic Header -->
    <header class="top-card">
      <div style="font-size: 0.78rem; text-transform: uppercase; letter-spacing: 1.2px; opacity: 0.88; margin-bottom: 8px;">
        ILOCOS SUR POLYTECHNIC STATE COLLEGE &bull; Tagudin Campus
      </div>
      <div class="badge-role">
        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
        First-Year College Student
      </div>
      <h1>EVALUATION INSTRUMENT: TECHNOLOGY ACCEPTANCE OF NEXIAM</h1>
      <p style="font-size: 0.88rem; max-width: 680px; margin: 8px auto 0; opacity: 0.92;">
        <strong>Project Title:</strong> NEXIAM: A Role-Based College Admission Testing System for the Guidance and Registrar Offices at ISPSC Tagudin Campus
      </p>
      <div>
        <button type="button" class="btn-authority" onclick="openConductModal()" aria-label="View official signed letter of conduct">
          <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>Official Institutional Authority &bull; View Signed Letters &amp; Clearances &nearr;</span>
        </button>
      </div>
    </header>

    @if ($submitted || session('status'))
      <div class="alert-success" style="margin-top: 20px;">
        <div>
          <strong>Submission Received Successfully!</strong>
          <p>{{ session('status') ?? 'Thank you for your valuable response. Your feedback has been recorded in the research database.' }}</p>
        </div>
        <button type="button" class="btn btn-outline" style="padding: 6px 12px; font-size: 0.85rem;" onclick="downloadBackupJSON()">
          Download JSON Copy
        </button>
      </div>

      <div class="xiam-plead-card" style="margin-top: 16px;">
        <div class="xiam-plead-row">
          <div class="xiam-plead-wrap">
            <img
              src="/assets/xiam/emotions/v2/star-eyes-smooth.webp"
              alt="Xiam begging starry eyes"
              class="xiam-plead-img"
              onerror="this.src='/assets/xiam/emotions/v2/hi-smooth.webp'"
            />
          </div>
          <div class="xiam-plead-content">
            <div class="xiam-plead-title">
              Help us further by sharing this to someone first year you know... 🥺🙏
            </div>
            <p class="xiam-plead-text">
              Reaching our target sample size of <strong>276+ first-year college students</strong> (Slovin formula from $N = 890, e = 0.05$) is essential for our capstone research. Surcharge oversampling is fully welcomed to ensure the best statistical results—if you know blockmates, friends, or classmates who haven't taken the evaluation yet, kindly send or forward this to them!
            </p>
            <div class="xiam-share-btn-group">
              <button type="button" class="btn-share-messenger" onclick="shareToMessenger()">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.477 2 2 6.145 2 11.258c0 2.914 1.455 5.518 3.734 7.21V22l3.39-1.86c.915.254 1.884.39 2.876.39 5.523 0 10-4.145 10-9.258S17.523 2 12 2zm1.045 12.443l-2.673-2.853-5.215 2.853 5.736-6.09 2.741 2.853 5.147-2.853-5.736 6.09z"/></svg>
                <span>Share to Messenger</span>
              </button>
              <button type="button" class="btn-share-fb" onclick="shareOnFacebook()">
                <svg width="17" height="17" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                <span>Share on Facebook</span>
              </button>
              <button type="button" class="btn-copy-snippet" onclick="copyFullSnippetToClipboard()">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><rect x="9" y="9" width="13" height="13" rx="2" ry="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                <span>Copy GC Message &amp; Link</span>
              </button>
            </div>
            <div id="snippetCopiedToastSuccess" style="display:none; margin-top: 10px; font-size: 0.82rem; color: #065f46; font-weight: 700;">
              ✓ Ready-made GC message copied! Paste it directly into your 1st-year block GCs. Thank you!
            </div>
          </div>
        </div>
      </div>
    @endif

    <!-- Visual Stepper (4 Steps) -->
    <div class="stepper-bar" aria-label="Evaluation Progress">
      <div class="step-item active" id="indicator-1" onclick="goToStep(1)" style="cursor: pointer;">
        <div class="step-circle" id="circle-1">1</div>
        <span class="step-label">1. Welcome</span>
      </div>
      <div class="step-connector"><div class="step-connector-fill" id="fill-1"></div></div>
      <div class="step-item" id="indicator-2" onclick="goToStep(2)" style="cursor: pointer;">
        <div class="step-circle" id="circle-2">2</div>
        <span class="step-label">2. Watch Video</span>
      </div>
      <div class="step-connector"><div class="step-connector-fill" id="fill-2"></div></div>
      <div class="step-item" id="indicator-3" onclick="goToStep(3)" style="cursor: pointer;">
        <div class="step-circle" id="circle-3">3</div>
        <span class="step-label">3. Try NEXIAM</span>
      </div>
      <div class="step-connector"><div class="step-connector-fill" id="fill-3"></div></div>
      <div class="step-item" id="indicator-4" onclick="goToStep(4)" style="cursor: pointer;">
        <div class="step-circle" id="circle-4">4</div>
        <span class="step-label">4. TAM Survey</span>
      </div>
    </div>

    <!-- Main Content Card -->
    <main class="main-card">

      <!-- ================= STEP 1: WELCOME & RESEARCH CONTEXT ================= -->
      <section id="step-1" class="step-section active">
        <!-- Mascot Greeting Hero -->
        <div class="welcome-card-hero">
          <div class="welcome-mascot-row">
            <div class="xiam-mascot-wrap">
              <img
                src="/assets/xiam/emotions/v2/hi-smooth.webp"
                alt="Xiam virtual mascot waving hello"
                class="xiam-mascot-img"
                onerror="this.src='/assets/branding/nexiam/nexiam-logo-only.png'"
              />
            </div>
            <div class="xiam-speech-card">
              <div class="xiam-badge-pill">
                <span class="xiam-badge-dot"></span>
                <span>Meet Xiam &bull; Institutional Admissions Guide</span>
              </div>
              <h2>Welcome, ISPSC Aspirants &amp; First-Year Students! 👋</h2>
              <p>
                Thank you so much for setting aside a few minutes of your valuable time to participate in our study.
                As first-year students who have walked the path of college admissions at ISPSC Tagudin Campus, your honest perspective and lived experience are essential.
              </p>
              <div class="xiam-highlight-box">
                <strong>Why your evaluation matters:</strong> NEXIAM was designed specifically to elevate, simplify, and dignify the admission testing and enrollment journey for all future ISPSC aspirants. Every response you provide directly guides how our campus enhances admissions, guidance counseling, and technological support.
              </div>
            </div>
          </div>
        </div>

        <!-- Section: Meet the Researchers -->
        <div class="context-block-header">
          <div>
            <div class="context-block-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
              <span>Meet the Capstone Researchers</span>
            </div>
            <div class="context-block-subtitle">4th-Year Bachelor of Science in Information Technology (BSIT) &bull; College of Arts and Sciences</div>
          </div>
        </div>

        <div class="researchers-grid">
          <!-- Researcher 1: David Datu Sarmiento -->
          <div class="researcher-card">
            <div class="researcher-avatar-frame">
              <img
                src="/assets/researchers/daviddatusarmiento.png"
                alt="David Datu N. Sarmiento"
                class="researcher-avatar-img"
                onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'font-weight:700;color:#1e40af;font-size:1.1rem;\'>DS</div>';"
              />
            </div>
            <div class="researcher-name">David Datu N. Sarmiento</div>
            <span class="researcher-role-tag">Lead Architecture &amp; System Developer</span>
            <p class="researcher-desc">Technical Writer &amp; Documenter &bull; BSIT Researcher</p>
          </div>

          <!-- Researcher 2: Jaypee Pagaduan -->
          <div class="researcher-card">
            <div class="researcher-avatar-frame">
              <img
                src="/assets/researchers/jaypeepagaduan.png"
                alt="Jaypee G. Pagaduan"
                class="researcher-avatar-img"
                onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'font-weight:700;color:#1e40af;font-size:1.1rem;\'>JP</div>';"
              />
            </div>
            <div class="researcher-name">Jaypee G. Pagaduan</div>
            <span class="researcher-role-tag">Lead QA &amp; Systems Analyst</span>
            <p class="researcher-desc">Technical Writer &amp; Documenter &bull; BSIT Researcher</p>
          </div>

          <!-- Researcher 3: Christine Lopez -->
          <div class="researcher-card">
            <div class="researcher-avatar-frame">
              <img
                src="/assets/researchers/christinelopez.png"
                alt="Christine M. Lopez"
                class="researcher-avatar-img"
                onerror="this.onerror=null; this.parentElement.innerHTML='<div style=\'font-weight:700;color:#1e40af;font-size:1.1rem;\'>CL</div>';"
              />
            </div>
            <div class="researcher-name">Christine M. Lopez</div>
            <span class="researcher-role-tag">Instrument &amp; Data Validation</span>
            <p class="researcher-desc">Technical Writer &amp; Documenter &bull; BSIT Researcher</p>
          </div>
        </div>

        <!-- Academic Leadership & Supervision Strip -->
        <div class="adviser-strip">
          <div class="adviser-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 10v6M2 10l10-5 10 5-10 5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/></svg>
            <span>Capstone Adviser: <strong>Dr. Zeus Vincent Mendoza</strong></span>
          </div>
          <div class="adviser-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
            <span>Language Critic / English Expert: <strong>Imelda Binay-an, PhD</strong></span>
          </div>
          <div class="adviser-item">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M4 10l8-6 8 6"/></svg>
            <span>Campus Director: <strong>Dr. Daniel Juan B. Ramirez</strong></span>
          </div>
        </div>

        <!-- Section: Official Institutional Documents & Endorsements -->
        <div class="context-block-header">
          <div>
            <div class="context-block-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
              <span>Official Institutional Clearance &amp; Endorsements</span>
            </div>
            <div class="context-block-subtitle">Our research is administered under formal administrative permission and rigorous academic validation:</div>
          </div>
        </div>

        <div class="docs-prior-grid">
          <!-- Doc 1: Letter of Conduct -->
          <div class="doc-preview-card" onclick="openDocumentModal(1)">
            <div class="doc-thumb-wrap">
              <img
                src="/assets/docs/letter-of-conduct.png"
                alt="Permit to Conduct Research thumbnail"
                class="doc-thumb-img"
              />
              <div class="doc-thumb-overlay">
                <span>Appendix A &bull; Click to Expand</span>
              </div>
            </div>
            <div class="doc-card-body">
              <span class="doc-card-badge">Institutional Clearance</span>
              <div class="doc-card-title">Permit to Conduct Research</div>
              <p class="doc-card-meta">Signed permission from Campus Leadership to conduct student and office evaluation.</p>
              <div class="doc-card-btn">
                <span>View Signed Permit &nearr;</span>
              </div>
            </div>
          </div>

          <!-- Doc 2: Deployment Letter -->
          <div class="doc-preview-card" onclick="openDocumentModal(2)">
            <div class="doc-thumb-wrap">
              <img
                src="/assets/docs/deployment-letter.jpg"
                alt="Official Deployment Letter thumbnail"
                class="doc-thumb-img"
              />
              <div class="doc-thumb-overlay">
                <span>Office Authority &bull; Click to Expand</span>
              </div>
            </div>
            <div class="doc-card-body">
              <span class="doc-card-badge" style="background:#e0f2fe;color:#0369a1;">Deployment Authority</span>
              <div class="doc-card-title">Official Deployment Letter</div>
              <p class="doc-card-meta">Approval for Guidance and Registrar office installation, account setup, and field testing.</p>
              <div class="doc-card-btn">
                <span>View Deployment Letter &nearr;</span>
              </div>
            </div>
          </div>

          <!-- Doc 3: English Critique Letter -->
          <div class="doc-preview-card" onclick="openDocumentModal(3)">
            <div class="doc-thumb-wrap">
              <img
                src="/assets/docs/english-critique.jpg"
                alt="English Critique Letter thumbnail"
                class="doc-thumb-img"
              />
              <div class="doc-thumb-overlay">
                <span>Scholarly Review &bull; Click to Expand</span>
              </div>
            </div>
            <div class="doc-card-body">
              <span class="doc-card-badge" style="background:#fef3c7;color:#92400e;">Instrument Validation</span>
              <div class="doc-card-title">English Critique Certification</div>
              <p class="doc-card-meta">Scholarly evaluation verifying grammatical precision and research instrument validity.</p>
              <div class="doc-card-btn">
                <span>View Certification &nearr;</span>
              </div>
            </div>
          </div>
        </div>

        <!-- Call-to-Action to Step 2 -->
        <div class="nav-footer" style="justify-content: flex-end; margin-top: 24px;">
          <button type="button" class="btn btn-primary" onclick="goToStep(2)" style="padding: 12px 28px; font-size: 0.98rem;">
            Proceed to Step 2: Watch System Walkthrough &rarr;
          </button>
        </div>
      </section>

      <!-- ================= STEP 2: VIDEO BRIEFING (WITH REAL WATCH GATING) ================= -->
      <section id="step-2" class="step-section">
        <div class="step-header">
          <h2>Step 2: Watch the NEXIAM Walkthrough Video</h2>
          <p>Please watch the complete system demonstration below. To ensure valid, high-quality research feedback, watching the walkthrough is required before proceeding to the survey.</p>
        </div>

        <div class="video-frame-card" id="videoFrameCard">
          <div class="video-wrapper">
            <iframe
              id="youtubeVideoFrame"
              src="https://www.youtube-nocookie.com/embed/6P1jCN4LWnM?enablejsapi=1&rel=0"
              allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
              allowfullscreen
              title="NEXIAM Walkthrough Video">
            </iframe>
          </div>
          <div class="video-fallback-bar">
            <span>Having trouble loading the embedded player?</span>
            <a href="https://youtu.be/6P1jCN4LWnM" target="_blank" rel="noopener noreferrer" onclick="handleExternalYouTubeClick()">
              Open video directly on YouTube &nearr;
            </a>
          </div>
        </div>

        <!-- Live Video Watch Progress Meter -->
        <div class="video-watch-progress-wrap" id="videoWatchProgressWrap">
          <div class="video-watch-progress-top">
            <span id="videoWatchStatusTitle">Walkthrough Progress Requirement</span>
            <span id="videoProgressTime">0:00 / --:--</span>
          </div>
          <div class="video-progress-track">
            <div class="video-progress-fill" id="videoProgressFill"></div>
          </div>
          <div class="video-watch-status-hint" id="videoWatchStatusMsg">
            <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>Please press Play on the video above. The confirmation and next step will unlock once you complete watching.</span>
          </div>
        </div>

        <!-- Gated Confirmation Card -->
        <label class="watch-check-card locked" id="watchCheckCard" for="videoWatched" onclick="handleCheckCardClick(event)">
          <input type="checkbox" id="videoWatched" disabled onchange="handleVideoCheck()" />
          <div class="watch-check-text">
            <strong id="watchCheckTitle">I confirm that I have finished watching the entire video walkthrough.</strong>
            <span id="watchCheckSub">Watching is required to unlock this verification and proceed to the evaluation questionnaire.</span>
          </div>
        </label>

        <!-- In-person or External presentation unlock option -->
        <div style="margin-top: 10px; display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 8px;">
          <button type="button" onclick="confirmInPersonPresentationWatch()" style="background:none; border:none; color:var(--text-muted); font-size:0.78rem; text-decoration:underline; cursor:pointer; padding:2px;">
            Attended live classroom presentation / already watched beforehand?
          </button>
          <span style="font-size:0.76rem; color:var(--text-muted);">Required for valid TAM methodology</span>
        </div>

        <div class="nav-footer" style="justify-content: space-between; margin-top: 24px;">
          <button type="button" class="btn btn-secondary" onclick="goToStep(1)">
            &larr; Back to Welcome
          </button>
          <button type="button" id="toStep3Btn" class="btn btn-primary" disabled onclick="handleNextFromVideo()">
            Next: System Link &rarr;
          </button>
        </div>
      </section>

      <!-- ================= STEP 3: OPTIONAL SITE EXPLORATION ================= -->
      <section id="step-3" class="step-section">
        <div class="step-header">
          <h2>Step 3: Explore the Live NEXIAM Website (Optional)</h2>
          <p>Before answering the evaluation items, you may visit the live NEXIAM portal to test the applicant features yourself.</p>
        </div>

        <div class="portal-showcase">
          <div class="portal-icon-wrap">
            <svg width="30" height="30" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="10"></circle>
              <line x1="2" y1="12" x2="22" y2="12"></line>
              <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
            </svg>
          </div>
          <h3>Try the NEXIAM Portal Firsthand</h3>
          <p>Click the button below to open the live system in a new browser tab. When you are ready, return to this tab and proceed to the questionnaire.</p>
          <div>
            <span class="portal-url-pill">{{ url('/') }}</span>
          </div>
          <a href="{{ url('/') }}" target="_blank" rel="noopener noreferrer" class="portal-launch-btn">
            Visit NEXIAM Website
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
          </a>
        </div>

        <div class="nav-footer">
          <button type="button" class="btn btn-secondary" onclick="goToStep(2)">
            &larr; Back to Video
          </button>
          <button type="button" class="btn btn-primary" onclick="goToStep(4)">
            Proceed to Questionnaire &rarr;
          </button>
        </div>
      </section>

      <!-- ================= STEP 4: TAM QUESTIONNAIRE ================= -->
      <section id="step-4" class="step-section">
        <form id="tamEvaluationForm" action="{{ route('tam.first-years.store') }}" method="POST">
          @csrf

          <input type="hidden" name="evaluator_role" value="First-Year College Student" />
          <input type="hidden" name="video_watched_confirmed" id="hiddenVideoConfirmed" value="true" />

          <!-- PART I: EVALUATOR PROFILE AND INFORMED CONSENT -->
          <div class="part-heading" style="margin-top: 8px;">
            <h3>PART I: EVALUATOR PROFILE AND INFORMED CONSENT</h3>
          </div>

          <div class="consent-card">
            <div class="consent-title">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
              Participant Informed Consent
            </div>
            <p>
              This evaluation seeks to measure your operational experience and user acceptance of the newly developed NEXIAM platform. Your participation involves completing a hands-on simulated workflow scenario followed by this evaluation questionnaire. In strict compliance with <strong>Republic Act No. 10173 (Data Privacy Act of 2012)</strong>, all responses will be kept confidential, analyzed only in aggregate form, and used solely for academic research. Participation is voluntary.
            </p>
          </div>

          <div class="profile-grid">
            <div class="profile-field">
              <label for="eval_date">Evaluation Date:</label>
              <input type="date" id="eval_date" name="evaluation_date" value="{{ $defaultDate ?? date('Y-m-d') }}" required />
            </div>

            <div class="profile-field">
              <label for="eval_name">Evaluator Name (Optional):</label>
              <input type="text" id="eval_name" name="evaluator_name" placeholder="Full name (Leave blank for anonymity)" />
            </div>

            <div class="profile-field" style="grid-column: 1 / -1;">
              <label>Academic Program / Department Affiliation (For Students / Faculty) &bull; Please select your College Department:</label>
              <div class="dept-options">
                <label class="dept-pill">
                  <input type="radio" name="department" value="CAS" required />
                  <div class="dept-content">
                    <strong>CAS</strong>
                    <span>College of Arts and Sciences</span>
                  </div>
                </label>
                <label class="dept-pill">
                  <input type="radio" name="department" value="CBME" required />
                  <div class="dept-content">
                    <strong>CBME</strong>
                    <span>College of Business Management &amp; Entrepreneurship</span>
                  </div>
                </label>
                <label class="dept-pill">
                  <input type="radio" name="department" value="CTE" required />
                  <div class="dept-content">
                    <strong>CTE</strong>
                    <span>College of Teacher Education</span>
                  </div>
                </label>
              </div>
            </div>

            <div class="profile-field" style="grid-column: 1 / -1;">
              <label for="acad_prog">Specific Degree Program / Course (Optional):</label>
              <input type="text" id="acad_prog" name="academic_program" placeholder="e.g., BS Information Technology (BSIT), BS Hospitality Management (BSHM), BEEd, BSEd, etc." />
            </div>
          </div>

          <!-- PART II: EVALUATION INSTRUCTIONS AND RATING BENCHMARK -->
          <div class="part-heading">
            <h3>PART II: EVALUATION INSTRUCTIONS AND RATING BENCHMARK</h3>
          </div>

          <div class="benchmark-card">
            <p style="font-weight: 600; color: var(--text); font-size: 0.92rem; margin-bottom: 8px;">
              Rate only the functions or result sheets you used during the assigned tasks. Check one response per statement. Answer only the questionnaire for your assigned role.
            </p>

            <div class="benchmark-scale-grid">
              <div class="scale-benchmark-item"><strong>5</strong> Strongly Agree (SA)</div>
              <div class="scale-benchmark-item"><strong>4</strong> Agree (A)</div>
              <div class="scale-benchmark-item"><strong>3</strong> Neutral (N)</div>
              <div class="scale-benchmark-item"><strong>2</strong> Disagree (D)</div>
              <div class="scale-benchmark-item"><strong>1</strong> Strongly Disagree (SD)</div>
            </div>

            <div class="benchmark-note">
              <strong>Important Notice on Neutral (3):</strong> Neutral means neither agreement nor disagreement. If a function was not available, not permitted, or not tested, tell the facilitator and leave its item unanswered. If you tried a function but could not complete the task, you may rate it based on that experience. Do not use Neutral to report an untested function. Task problems and help received are recorded separately.
            </div>
          </div>
          <div class="instruction-banner">
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="flex-shrink:0; margin-top:2px;">
              <circle cx="12" cy="12" r="10"></circle><line x1="12" y1="16" x2="12" y2="12"></line><line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
            <div>
              <strong>Directions:</strong> Check one response per statement. Rate only the functions you attempted. Leave untested, unavailable, or unauthorized items unanswered.
            </div>
          </div>

          <!-- Live Completion Counter -->
          <div class="progress-sticky">
            <div class="progress-info">
              Items Rated: <span id="answeredCount">0</span> of 9 statements (Optional / Rate attempted functions)
            </div>
            <div class="progress-track">
              <div class="progress-bar-fill" id="surveyProgressBar"></div>
            </div>
          </div>

          <div class="part-heading">
            <h3>PART III: ROLE-SPECIFIC EVALUATION QUESTIONNAIRE</h3>
          </div>

          <!-- Construct 1: Perceived Usefulness -->
          <div class="sub-construct">
            <h4>Perceived Usefulness (STU-PU)</h4>
            <span>5 = Strongly Agree &bull; 1 = Strongly Disagree</span>
          </div>

          <div class="table-responsive">
            <table class="tam-table">
              <thead>
                <tr>
                  <th class="col-statement">Item Code / Evaluation Indicator Statement</th>
                  <th><span class="scale-num">5</span>Strongly Agree</th>
                  <th><span class="scale-num">4</span>Agree</th>
                  <th><span class="scale-num">3</span>Neutral</th>
                  <th><span class="scale-num">2</span>Disagree</th>
                  <th><span class="scale-num">1</span>Strongly Disagree</th>
                </tr>
              </thead>
              <tbody>
                <tr data-item="STU-PU-01">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PU-01</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PU-01')">Clear</button>
                    </div>
                    <div class="item-statement">The applicant portal provides useful information about my simulated application status.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-01" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-01" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-01" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-01" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-01" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PU-02">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PU-02</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PU-02')">Clear</button>
                    </div>
                    <div class="item-statement">The inquiry reply provides useful information about admission requirements.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-02" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-02" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-02" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-02" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-02" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PU-03">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PU-03</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PU-03')">Clear</button>
                    </div>
                    <div class="item-statement">The portal notices provide useful information and announcements regarding my application.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-03" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-03" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-03" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-03" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-03" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PU-04">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PU-04</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PU-04')">Clear</button>
                    </div>
                    <div class="item-statement">The result sheet provides useful information about my examination results.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-04" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-04" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-04" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-04" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-04" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PU-05">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PU-05</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PU-05')">Clear</button>
                    </div>
                    <div class="item-statement">The examination schedule provides useful information to help me prepare for testing.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-05" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-05" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-05" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-05" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PU-05" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- Construct 2: Perceived Ease of Use -->
          <div class="sub-construct">
            <h4>First-Year College Student: Questionnaire Continued &mdash; Perceived Ease of Use (STU-PEOU)</h4>
            <span>5 = Strongly Agree &bull; 1 = Strongly Disagree</span>
          </div>

          <div class="table-responsive">
            <table class="tam-table">
              <thead>
                <tr>
                  <th class="col-statement">Item Code / Evaluation Indicator Statement</th>
                  <th><span class="scale-num">5</span>Strongly Agree</th>
                  <th><span class="scale-num">4</span>Agree</th>
                  <th><span class="scale-num">3</span>Neutral</th>
                  <th><span class="scale-num">2</span>Disagree</th>
                  <th><span class="scale-num">1</span>Strongly Disagree</th>
                </tr>
              </thead>
              <tbody>
                <tr data-item="STU-PEOU-01">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PEOU-01</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PEOU-01')">Clear</button>
                    </div>
                    <div class="item-statement">Navigating the applicant portal is easy.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-01" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-01" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-01" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-01" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-01" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PEOU-02">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PEOU-02</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PEOU-02')">Clear</button>
                    </div>
                    <div class="item-statement">Using the inquiry function is easy.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-02" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-02" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-02" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-02" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-02" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PEOU-03">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PEOU-03</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PEOU-03')">Clear</button>
                    </div>
                    <div class="item-statement">Understanding the examination scores on the result sheet is easy.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-03" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-03" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-03" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-03" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-03" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>

                <tr data-item="STU-PEOU-04">
                  <td>
                    <div class="item-header">
                      <span class="item-code">STU-PEOU-04</span>
                      <button type="button" class="clear-choice-btn" onclick="clearRow('STU-PEOU-04')">Clear</button>
                    </div>
                    <div class="item-statement">Logging in with the assigned access key and birthdate is easy.</div>
                  </td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-04" value="5" onchange="updateProgress()"><span>5</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-04" value="4" onchange="updateProgress()"><span>4</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-04" value="3" onchange="updateProgress()"><span>3</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-04" value="2" onchange="updateProgress()"><span>2</span></label></td>
                  <td class="scale-cell"><label class="radio-pill"><input type="radio" name="STU-PEOU-04" value="1" onchange="updateProgress()"><span>1</span></label></td>
                </tr>
              </tbody>
            </table>
          </div>

          <!-- PART IV: Qualitative Feedback -->
          <div class="part-heading">
            <h3>PART IV: QUALITATIVE FEEDBACK AND OPERATIONAL OBSERVATIONS</h3>
          </div>

          <div class="qualitative-grid">
            <div class="field-card">
              <label for="q_useful">1. What features of NEXIAM did you find most useful or beneficial?</label>
              <textarea id="q_useful" name="useful_features" placeholder="Write your observations here..."></textarea>
            </div>

            <div class="field-card">
              <label for="q_difficulty">2. What aspects or features of NEXIAM caused difficulty, confusion, or delays?</label>
              <textarea id="q_difficulty" name="difficulties_encountered" placeholder="Describe any issues or delays experienced..."></textarea>
            </div>

            <div class="field-card">
              <label for="q_recommendations">3. What specific recommendations or improvements would you suggest before full institutional deployment?</label>
              <textarea id="q_recommendations" name="recommendations" placeholder="Share your suggestions for improvement..."></textarea>
            </div>

            <div class="field-card">
              <label for="evaluator_signature">Evaluator Signature / Full Name (Optional):</label>
              <input type="text" id="evaluator_signature" name="evaluator_signature" placeholder="Type your name or digital signature (Optional)" />
            </div>
          </div>

          <div class="nav-footer">
            <button type="button" class="btn btn-secondary" onclick="goToStep(3)">
              &larr; Back to Portal Link
            </button>
            <div style="display: flex; gap: 10px; flex-wrap: wrap;">
              <button type="button" class="btn btn-outline" onclick="downloadBackupJSON()" title="Save a local backup copy of your answers">
                Download Backup Copy
              </button>
              <button type="submit" class="btn btn-primary" id="submitBtn">
                Submit Evaluation to NEXIAM &rarr;
              </button>
            </div>
          </div>

          <p class="thank-you-footer">Thank you for your valuable contribution to the evaluation of NEXIAM.</p>
        </form>
      </section>
    </main>

    <!-- Bottom Emphasized Survey Scope & Department Traction Tracker -->
    <footer style="margin-top: 28px; text-align: center; color: var(--text-muted); font-size: 0.84rem; line-height: 1.6;">
      <div class="dept-traction-card">
        <div class="dept-traction-header">
          <div class="sad-xiam-wrap">
            <img
              src="/assets/xiam/emotions/v2/sad-smooth.webp"
              alt="Sad pleading Xiam mascot"
              class="sad-xiam-img"
              onerror="this.src='/assets/xiam/emotions/v2/hi-smooth.webp'"
            />
          </div>
          <div class="dept-traction-bubble">
            <h3>
              <span>Help your College reach its Milestone! 🥺🙏</span>
            </h3>
            <p>
              We are still gathering first-year participants to reach our target of <strong>276+ respondents</strong> (with oversampling welcomed). See how your department is progressing below and help your college reach 100% by forwarding this link to your block GCs!
            </p>
          </div>
        </div>

        <!-- Overall Milestone Track -->
        <div style="background: #f1f5f9; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px;">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; font-size: 0.82rem; font-weight: 700;">
            <span style="color: var(--text);">Overall Milestone Progress</span>
            <span style="color: var(--primary);">{{ $totalCount ?? 0 }} / {{ $targetSample ?? 276 }} Responses ({{ min(100, round((($totalCount ?? 0) / ($targetSample ?? 276)) * 100)) }}%)</span>
          </div>
          <div style="height: 10px; background: #e2e8f0; border-radius: 999px; overflow: hidden;">
            <div style="height: 100%; width: {{ min(100, round((($totalCount ?? 0) / ($targetSample ?? 276)) * 100)) }}%; background: linear-gradient(90deg, #3b82f6 0%, #10b981 100%); border-radius: 999px;"></div>
          </div>
        </div>

        <!-- Department Breakdown (50% CAS, 25% CBME, 25% CTE) -->
        <div class="dept-bars-grid">
          <!-- CAS -->
          <div class="dept-bar-card">
            <div class="dept-bar-header">
              <span class="dept-bar-tag" style="color: #1d4ed8;">CAS</span>
              <span class="dept-bar-quota">Target: 50% ({{ $departmentStats['CAS']['target'] ?? 138 }})</span>
            </div>
            <div class="dept-progress-track">
              <div class="dept-progress-fill" style="width: {{ $departmentStats['CAS']['percent'] ?? 0 }}%; background: #2563eb;"></div>
            </div>
            <div class="dept-bar-counts">
              <span>College of Arts &amp; Sciences</span>
              <strong>{{ $departmentStats['CAS']['count'] ?? 0 }} / {{ $departmentStats['CAS']['target'] ?? 138 }} ({{ $departmentStats['CAS']['percent'] ?? 0 }}%)</strong>
            </div>
          </div>

          <!-- CBME -->
          <div class="dept-bar-card">
            <div class="dept-bar-header">
              <span class="dept-bar-tag" style="color: #047857;">CBME</span>
              <span class="dept-bar-quota">Target: 25% ({{ $departmentStats['CBME']['target'] ?? 69 }})</span>
            </div>
            <div class="dept-progress-track">
              <div class="dept-progress-fill" style="width: {{ $departmentStats['CBME']['percent'] ?? 0 }}%; background: #059669;"></div>
            </div>
            <div class="dept-bar-counts">
              <span>Business &amp; Entrepreneurship</span>
              <strong>{{ $departmentStats['CBME']['count'] ?? 0 }} / {{ $departmentStats['CBME']['target'] ?? 69 }} ({{ $departmentStats['CBME']['percent'] ?? 0 }}%)</strong>
            </div>
          </div>

          <!-- CTE -->
          <div class="dept-bar-card" style="border-color: #fde68a; background: #fffdf5;">
            <div class="dept-bar-header">
              <span class="dept-bar-tag" style="color: #b45309;">CTE</span>
              <span class="dept-bar-quota" style="background: #fef3c7; color: #92400e;">Target: 25% ({{ $departmentStats['CTE']['target'] ?? 69 }})</span>
            </div>
            <div class="dept-progress-track">
              <div class="dept-progress-fill" style="width: {{ $departmentStats['CTE']['percent'] ?? 0 }}%; background: #d97706;"></div>
            </div>
            <div class="dept-bar-counts">
              <span>Teacher Education</span>
              <strong>{{ $departmentStats['CTE']['count'] ?? 0 }} / {{ $departmentStats['CTE']['target'] ?? 69 }} ({{ $departmentStats['CTE']['percent'] ?? 0 }}%)</strong>
            </div>
          </div>
        </div>

        <!-- Sharing CTA Row -->
        <div style="border-top: 1px dashed var(--border); padding-top: 14px; margin-top: 12px; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
          <div style="font-size: 0.82rem; color: var(--text-secondary); text-align: left;">
            <strong style="color: #1e40af;">Target Respondents: First-Year College Students Only</strong> (ISPSC Tagudin Campus).
          </div>
          <div style="display: flex; gap: 8px; flex-wrap: wrap;">
            <button type="button" class="btn btn-outline" style="padding: 7px 14px; font-size: 0.82rem; border-color: #bfdbfe; color: #1e40af; background: #eff6ff;" onclick="copyShareLink()">
              📋 Copy Link for GCs
            </button>
            <button type="button" class="btn btn-primary" style="padding: 7px 16px; font-size: 0.82rem;" onclick="copyFullSnippetToClipboard()">
              💌 Copy Ready GC Message
            </button>
            <button type="button" class="btn-share-messenger" style="padding: 7px 14px; font-size: 0.82rem;" onclick="shareToMessenger()">
              💬 Share to Messenger
            </button>
          </div>
        </div>
        <div id="copyLinkSuccessMsg" style="display:none; color: #059669; font-weight: 700; font-size: 0.82rem; margin-top: 8px; text-align: center;">✓ Link copied to clipboard!</div>
      </div>
      <p style="margin-top: 14px; font-size: 0.78rem; color: var(--text-muted);">
        Ilocos Sur Polytechnic State College &bull; Tagudin Campus &bull; Bachelor of Science in Information Technology (BSIT) &bull; 2026
      </p>
    </footer>

  </div>

  <script>
    const itemCodes = [
      'STU-PU-01', 'STU-PU-02', 'STU-PU-03', 'STU-PU-04', 'STU-PU-05',
      'STU-PEOU-01', 'STU-PEOU-02', 'STU-PEOU-03', 'STU-PEOU-04'
    ];

    let videoWatchCompleted = false;
    let ytPlayer = null;
    let ytProgressInterval = null;
    let ytDuration = 0;
    let ytHighestPercent = 0;
    let cumulativeWatchedSeconds = 0;
    let lastObservedCurrentTime = -1;

    function handleVideoCheck() {
      const checkbox = document.getElementById('videoWatched');
      const card = document.getElementById('watchCheckCard');
      const nextBtn = document.getElementById('toStep3Btn');

      if (!videoWatchCompleted && checkbox.disabled) {
        showVideoRequiredNotification();
        return;
      }

      if (nextBtn) nextBtn.disabled = !checkbox.checked;
      if (checkbox.checked) {
        card.classList.add('checked');
      } else {
        card.classList.remove('checked');
      }
    }

    function handleCheckCardClick(e) {
      if (!videoWatchCompleted) {
        e.preventDefault();
        showVideoRequiredNotification();
      }
    }

    function handleNextFromVideo() {
      if (!videoWatchCompleted && !document.getElementById('videoWatched').checked) {
        showVideoRequiredNotification();
        return;
      }
      goToStep(3);
    }

    function showVideoRequiredNotification() {
      const statusMsg = document.getElementById('videoWatchStatusMsg');
      const videoFrame = document.getElementById('videoFrameCard');
      if (videoFrame && typeof videoFrame.scrollIntoView === 'function') {
        videoFrame.scrollIntoView({ behavior: 'smooth', block: 'center' });
      }
      if (statusMsg) {
        statusMsg.innerHTML = '<span style="color:#b91c1c; font-weight:700;">⚠️ Please play and watch the walkthrough video first! This ensures your evaluation feedback is accurate and informed.</span>';
      }
      alert('Walkthrough Required:\nPlease watch the video demonstration to understand NEXIAM before proceeding to the survey questions.');
    }

    function unlockVideoRequirement(ended = false) {
      if (videoWatchCompleted) return;
      videoWatchCompleted = true;

      const checkbox = document.getElementById('videoWatched');
      const card = document.getElementById('watchCheckCard');
      const nextBtn = document.getElementById('toStep3Btn');
      const fill = document.getElementById('videoProgressFill');
      const statusMsg = document.getElementById('videoWatchStatusMsg');
      const title = document.getElementById('watchCheckTitle');
      const sub = document.getElementById('watchCheckSub');

      if (checkbox) {
        checkbox.disabled = false;
        checkbox.checked = true;
      }
      if (card) {
        card.classList.remove('locked');
        card.classList.add('unlocked', 'checked');
      }
      if (nextBtn) {
        nextBtn.disabled = false;
        nextBtn.classList.add('glow-pulse');
      }
      if (fill) {
        fill.classList.add('completed');
        fill.style.width = '100%';
      }
      if (title) {
        title.innerHTML = '✓ Video Walkthrough Confirmed as Watched';
      }
      if (sub) {
        sub.innerHTML = 'Thank you for watching! You can now proceed to explore the system or start the survey.';
      }
      if (statusMsg) {
        statusMsg.innerHTML = '<span style="color:#059669; font-weight:700;">✓ Video Walkthrough requirement fulfilled! You may now proceed.</span>';
      }
    }

    function handleExternalYouTubeClick() {
      const statusMsg = document.getElementById('videoWatchStatusMsg');
      let countdown = 60;
      if (statusMsg) {
        statusMsg.innerHTML = `<span>Walkthrough opened in YouTube. Verification active: <strong>${countdown}s</strong> remaining...</span>`;
      }
      const interval = setInterval(() => {
        countdown--;
        if (statusMsg && countdown > 0) {
          statusMsg.innerHTML = `<span>Walkthrough opened in YouTube. Verification active: <strong>${countdown}s</strong> remaining before unlock...</span>`;
        }
        const pct = Math.min(100, Math.round(((60 - countdown) / 60) * 100));
        const fill = document.getElementById('videoProgressFill');
        if (fill) fill.style.width = pct + '%';

        if (countdown <= 0) {
          clearInterval(interval);
          unlockVideoRequirement(false);
        }
      }, 1000);
    }

    function confirmInPersonPresentationWatch() {
      const confirmed = confirm(
        "Did you attend the live classroom/office presentation or already watch the complete NEXIAM demonstration?\n\nClick OK if you have seen the demonstration."
      );
      if (confirmed) {
        unlockVideoRequirement(true);
      }
    }

    function formatTime(seconds) {
      if (!seconds || isNaN(seconds)) return '0:00';
      const m = Math.floor(seconds / 60);
      const s = Math.floor(seconds % 60);
      return `${m}:${s < 10 ? '0' : ''}${s}`;
    }

    // YouTube API Callbacks
    window.onYouTubeIframeAPIReady = function() {
      try {
        ytPlayer = new YT.Player('youtubeVideoFrame', {
          events: {
            'onReady': onPlayerReady,
            'onStateChange': onPlayerStateChange
          }
        });
      } catch (err) {}
    };

    function onPlayerReady(event) {
      try {
        ytDuration = ytPlayer.getDuration();
        updateTimeDisplay(0, ytDuration);
      } catch (e) {}
    }

    function onPlayerStateChange(event) {
      if (event.data === YT.PlayerState.PLAYING) {
        lastObservedCurrentTime = ytPlayer.getCurrentTime();
        if (ytProgressInterval) clearInterval(ytProgressInterval);
        ytProgressInterval = setInterval(() => {
          if (!ytPlayer || typeof ytPlayer.getCurrentTime !== 'function') return;
          try {
            const current = ytPlayer.getCurrentTime();
            const dur = ytPlayer.getDuration() || ytDuration;
            if (dur > 0) {
              if (lastObservedCurrentTime >= 0) {
                const delta = current - lastObservedCurrentTime;
                // Natural playback advances between 0.5s and 2.2s per 1s tick
                if (delta > 0 && delta <= 2.2) {
                  cumulativeWatchedSeconds += delta;
                } else if (delta > 2.2) {
                  // Fast-forward detected!
                  const statusMsg = document.getElementById('videoWatchStatusMsg');
                  if (statusMsg && !videoWatchCompleted) {
                    const currentPct = Math.min(100, Math.round((cumulativeWatchedSeconds / dur) * 100));
                    statusMsg.innerHTML = '<span style="color:#d97706; font-weight:600;">⏩ Fast-forward detected: Progress tracks genuine watch time (' + currentPct + '% watched). Please let the video play to finish the requirement.</span>';
                  }
                }
              }
              lastObservedCurrentTime = current;

              // The progress bar tracks REAL cumulative watch time!
              const realWatchPercent = Math.min(100, Math.round((cumulativeWatchedSeconds / dur) * 100));
              const fill = document.getElementById('videoProgressFill');
              if (fill && !fill.classList.contains('completed')) {
                fill.style.width = realWatchPercent + '%';
              }
              updateTimeDisplay(current, dur, cumulativeWatchedSeconds, realWatchPercent);

              // Unlocks ONLY when user genuinely watches through to the end:
              // Either reaches the end (within 3 seconds) with >= 75% watched, or watched >= 88% total time
              if ((current >= dur - 3 && cumulativeWatchedSeconds >= dur * 0.75) || cumulativeWatchedSeconds >= dur * 0.88) {
                unlockVideoRequirement(false);
              }
            }
          } catch (e) {}
        }, 1000);
      } else if (event.data === YT.PlayerState.PAUSED) {
        if (ytProgressInterval) clearInterval(ytProgressInterval);
        lastObservedCurrentTime = -1;
      } else if (event.data === YT.PlayerState.ENDED) {
        if (ytProgressInterval) clearInterval(ytProgressInterval);
        lastObservedCurrentTime = -1;
        const dur = ytPlayer ? (ytPlayer.getDuration() || ytDuration) : ytDuration;
        if (cumulativeWatchedSeconds >= dur * 0.70) {
          unlockVideoRequirement(true);
        } else {
          // Skipping detected; keep locked
          const statusMsg = document.getElementById('videoWatchStatusMsg');
          if (statusMsg) {
            statusMsg.innerHTML = '<span style="color:#b91c1c; font-weight:700;">⚠️ Video ended, but skipping was detected (' + Math.round(cumulativeWatchedSeconds) + 's watched of ' + Math.round(dur) + 's). Please watch the demonstration through to unlock the survey.</span>';
          }
        }
      }
    }

    function updateTimeDisplay(current, duration, watchedSec, percent) {
      const el = document.getElementById('videoProgressTime');
      if (el) {
        const pct = percent !== undefined ? percent : Math.min(100, Math.round((current / duration) * 100));
        el.innerHTML = `${formatTime(current)} / ${formatTime(duration)} &bull; <strong>${pct}% Watched</strong>`;
      }
    }

    function goToStep(stepNumber) {
      // Gating: If trying to navigate to Step 3 or Step 4 without fulfilling video requirement
      if (stepNumber > 2 && !videoWatchCompleted && !document.getElementById('videoWatched').checked) {
        showVideoRequiredNotification();
        return;
      }

      document.querySelectorAll('.step-section').forEach(sec => sec.classList.remove('active'));
      const targetSec = document.getElementById('step-' + stepNumber);
      if (targetSec) {
        targetSec.classList.add('active');
      }

      // Update Top Stepper UI (4 Steps)
      for (let i = 1; i <= 4; i++) {
        const indicator = document.getElementById('indicator-' + i);
        const circle = document.getElementById('circle-' + i);
        if (!indicator || !circle) continue;
        indicator.classList.remove('active', 'completed');

        if (i < stepNumber) {
          indicator.classList.add('completed');
          circle.innerHTML = '&#10003;';
        } else if (i === stepNumber) {
          indicator.classList.add('active');
          circle.textContent = i;
        } else {
          circle.textContent = i;
        }
      }

      if (document.getElementById('fill-1')) document.getElementById('fill-1').style.width = stepNumber >= 2 ? '100%' : '0%';
      if (document.getElementById('fill-2')) document.getElementById('fill-2').style.width = stepNumber >= 3 ? '100%' : '0%';
      if (document.getElementById('fill-3')) document.getElementById('fill-3').style.width = stepNumber >= 4 ? '100%' : '0%';

      if (typeof window.scrollTo === 'function') {
        window.scrollTo({ top: 0, behavior: 'smooth' });
      }
    }

    window.goToStep = goToStep;
    window.handleVideoCheck = handleVideoCheck;
    window.unlockVideoRequirement = unlockVideoRequirement;
    window.handleCheckCardClick = handleCheckCardClick;
    window.handleNextFromVideo = handleNextFromVideo;

    function copyShareLink() {
      const url = window.location.origin + '/tam-evaluation/first-years?v=2';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(url).then(() => {
          showCopySuccess();
        }).catch(() => {
          fallbackCopyText(url);
        });
      } else {
        fallbackCopyText(url);
      }
    }

    function showCopySuccess() {
      const msg = document.getElementById('copyLinkSuccessMsg');
      if (msg) {
        msg.style.display = 'inline';
        setTimeout(() => { msg.style.display = 'none'; }, 3000);
      }
    }

    function fallbackCopyText(text) {
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.left = '-9999px';
      document.body.appendChild(ta);
      ta.select();
      try {
        document.execCommand('copy');
        showCopySuccess();
      } catch (e) {
        prompt('Copy this link:', text);
      }
      document.body.removeChild(ta);
    }

    window.copyShareLink = copyShareLink;

    function shareToMessenger() {
      const url = window.location.origin + '/tam-evaluation/first-years?v=2';
      const isMobile = /Android|iPhone|iPad|iPod/i.test(navigator.userAgent);
      if (isMobile && navigator.share) {
        navigator.share({
          title: 'NEXIAM Evaluation Instrument | First-Year Students',
          text: '👋 Hi everyone! Please take 3–5 minutes to evaluate NEXIAM for ISPSC Tagudin admissions (1st-year students). Kindly share to our block GCs too! 🥺🙏',
          url: url
        }).catch(() => {});
      } else {
        const fbSendUrl = 'https://www.facebook.com/dialog/send?link=' + encodeURIComponent(url) + '&app_id=966242223397117&redirect_uri=' + encodeURIComponent(url);
        window.open(fbSendUrl, '_blank', 'width=620,height=520');
      }
    }

    function shareOnFacebook() {
      const url = window.location.origin + '/tam-evaluation/first-years?v=2';
      const fbShareUrl = 'https://www.facebook.com/sharer/sharer.php?u=' + encodeURIComponent(url);
      window.open(fbShareUrl, '_blank', 'width=620,height=520');
    }

    function copyFullSnippetToClipboard() {
      const snippet = `👋 Hi everyone! We need your help! Kindly take 3–5 minutes to evaluate NEXIAM, our BSIT capstone admission system for ISPSC Tagudin. As 1st-year students, your feedback is super valuable to improve campus admissions.

🔗 Survey Link: ${window.location.origin}/tam-evaluation/first-years?v=2

📌 Target: All 1st-Year College Students (ISPSC Tagudin)
🙏 Paki-forward na rin po sa iba nating 1st-year block GCs—malaking tulong po ito sa study namin. Thank you so much!`;

      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(snippet).then(() => {
          showSnippetToast();
        }).catch(() => {
          fallbackCopyText(snippet);
        });
      } else {
        fallbackCopyText(snippet);
      }
    }

    function showSnippetToast() {
      const el1 = document.getElementById('snippetCopiedToastSuccess');
      const el2 = document.getElementById('snippetCopiedToastForm');
      if (el1) {
        el1.style.display = 'block';
        setTimeout(() => { el1.style.display = 'none'; }, 4000);
      }
      if (el2) {
        el2.style.display = 'block';
        setTimeout(() => { el2.style.display = 'none'; }, 4000);
      }
      showCopySuccess();
    }

    window.shareToMessenger = shareToMessenger;
    window.shareOnFacebook = shareOnFacebook;
    window.copyFullSnippetToClipboard = copyFullSnippetToClipboard;

    function updateProgress() {
      let count = 0;
      itemCodes.forEach(code => {
        const row = document.querySelector(`tr[data-item="${code}"]`);
        const checked = document.querySelector(`input[name="${code}"]:checked`);
        if (checked) {
          count++;
          if (row) row.classList.add('answered-row');
        } else {
          if (row) row.classList.remove('answered-row');
        }
      });

      document.getElementById('answeredCount').textContent = count;
      const pct = Math.round((count / itemCodes.length) * 100);
      document.getElementById('surveyProgressBar').style.width = pct + '%';
    }

    function clearRow(code) {
      const radios = document.querySelectorAll(`input[name="${code}"]`);
      radios.forEach(r => r.checked = false);
      updateProgress();
    }

    function collectFormData() {
      const form = document.getElementById('tamEvaluationForm');
      const formData = new FormData(form);
      const data = {};
      formData.forEach((value, key) => {
        if (key !== '_token') {
          data[key] = value;
        }
      });
      data.submitted_at = new Date().toISOString();
      return data;
    }

    function downloadBackupJSON() {
      const data = collectFormData();
      const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
      const url = URL.createObjectURL(blob);
      const a = document.createElement('a');
      a.href = url;
      a.download = `nexiam-tam-first-year-${Date.now()}.json`;
      document.body.appendChild(a);
      a.click();
      document.body.removeChild(a);
      URL.revokeObjectURL(url);
    }

    // Intercept form submission to try JSON/FormData fetch with graceful fallback to standard POST
    document.getElementById('tamEvaluationForm').addEventListener('submit', async function(e) {
      const submitBtn = document.getElementById('submitBtn');
      submitBtn.textContent = 'Submitting...';
    });

    // ================= Floating Email Support & Inquiry Logic =================
    const SUPPORT_EMAIL = 'nexiamispsctagudin@gmail.com';

    function toggleSupportModal() {
      const modal = document.getElementById('supportModal');
      const isVisible = modal.classList.toggle('active');
      if (isVisible) {
        document.getElementById('emailBodyInput').focus();
      }
    }

    function selectSubject(chipEl, subjectText) {
      document.querySelectorAll('.topic-chip').forEach(c => c.classList.remove('selected'));
      chipEl.classList.add('selected');
      const prefix = '[NEXIAM TAM] ';
      const input = document.getElementById('emailSubjectInput');
      input.value = prefix + subjectText;
      if (subjectText.includes('Custom')) {
        input.focus();
        input.select();
      }
    }

    function copySupportEmail() {
      navigator.clipboard.writeText(SUPPORT_EMAIL).then(() => {
        const btn = document.getElementById('copyEmailBtn');
        const orig = btn.textContent;
        btn.textContent = 'Copied!';
        btn.style.background = '#059669';
        btn.style.color = '#fff';
        setTimeout(() => {
          btn.textContent = orig;
          btn.style.background = '';
          btn.style.color = '';
        }, 2000);
      }).catch(() => {
        alert('Email: ' + SUPPORT_EMAIL);
      });
    }

    function launchEmailClient() {
      const subject = encodeURIComponent(document.getElementById('emailSubjectInput').value || '[NEXIAM TAM] Inquiry');
      const bodyText = document.getElementById('emailBodyInput').value.trim();
      const body = encodeURIComponent(bodyText ? bodyText + '\n\n--\nSent from NEXIAM TAM Evaluation Participant' : 'Hello NEXIAM Team,\n\nI have an inquiry regarding:\n');
      window.location.href = `mailto:${SUPPORT_EMAIL}?subject=${subject}&body=${body}`;
    }

    function copyFullInquiryDetails() {
      const subject = document.getElementById('emailSubjectInput').value;
      const body = document.getElementById('emailBodyInput').value;
      const textToCopy = `To: ${SUPPORT_EMAIL}\nSubject: ${subject}\n\n${body}`;
      navigator.clipboard.writeText(textToCopy).then(() => {
        const btn = document.getElementById('copyAllDetailsBtn');
        const orig = btn.textContent;
        btn.textContent = 'Copied to Clipboard!';
        btn.style.background = '#059669';
        btn.style.color = '#fff';
        setTimeout(() => {
          btn.textContent = orig;
          btn.style.background = '';
          btn.style.color = '';
        }, 2000);
      }).catch(() => {
        alert('Could not copy automatically. You can email: ' + SUPPORT_EMAIL);
      });
    }

    // Close on escape
    document.addEventListener('keydown', function(e) {
      if (e.key === 'Escape') {
        const modal = document.getElementById('supportModal');
        if (modal && modal.classList.contains('active')) {
          modal.classList.remove('active');
        }
      }
    });

  </script>
  <!-- Floating Circle Email Support Widget -->
  <div class="fab-container" id="fabContainer">
    <button type="button" class="fab-btn" id="fabBtn" onclick="toggleSupportModal()" title="Need Help or Have Inquiries? Email Us" aria-label="Open Email Support Window">
      <span class="fab-pulse-dot"></span>
      <!-- Email / Chat SVG Icon -->
      <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
        <line x1="8" y1="9" x2="16" y2="9"></line>
        <line x1="8" y1="13" x2="14" y2="13"></line>
      </svg>
    </button>
  </div>

  <!-- Support & Inquiries Modal Popup -->
  <div class="support-modal" id="supportModal" role="dialog" aria-labelledby="supportModalTitle">
    <div class="support-header">
      <h3 id="supportModalTitle">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
        Need Help or Have Inquiries?
      </h3>
      <button type="button" class="support-close-btn" onclick="toggleSupportModal()" aria-label="Close Support Window">&times;</button>
    </div>

    <!-- Direct Email Display & Copy -->
    <div class="email-display-card">
      <div class="email-text-wrap">
        <span class="email-label">Official Research Contact</span>
        <span class="email-address" id="supportEmailAddr">nexiamispsctagudin@gmail.com</span>
      </div>
      <button type="button" class="btn-copy-email" id="copyEmailBtn" onclick="copySupportEmail()">
        Copy Email
      </button>
    </div>

    <!-- Suggested Topics / Subjects -->
    <span class="topic-selection-title">Select an Inquiry Topic:</span>
    <div class="topic-chips" id="topicChips">
      <button type="button" class="topic-chip selected" onclick="selectSubject(this, 'System Operation: How to Navigate & Use NEXIAM')">
        <span>&bull; How to operate parts of the system</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">&rarr;</span>
      </button>
      <button type="button" class="topic-chip" onclick="selectSubject(this, 'Simulated Login: Assigned Access Key & Birthday Assistance')">
        <span>&bull; Access key / birthdate simulated login</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">&rarr;</span>
      </button>
      <button type="button" class="topic-chip" onclick="selectSubject(this, 'Evaluation Clarification: Questions on TAM Indicators')">
        <span>&bull; TAM evaluation question clarification</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">&rarr;</span>
      </button>
      <button type="button" class="topic-chip" onclick="selectSubject(this, 'Technical Issue: Video Playback or Portal Loading')">
        <span>&bull; Video playback or page loading issue</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">&rarr;</span>
      </button>
      <button type="button" class="topic-chip" onclick="selectSubject(this, 'Custom Inquiry: Question for NEXIAM Team')">
        <span>&bull; Other custom question / feedback</span>
        <span style="font-size:0.75rem; color:var(--text-muted);">&rarr;</span>
      </button>
    </div>

    <!-- Subject Preview & Edit -->
    <div class="support-field">
      <label for="emailSubjectInput">Email Subject:</label>
      <input type="text" id="emailSubjectInput" value="[NEXIAM TAM] System Operation: How to Navigate & Use NEXIAM" />
    </div>

    <!-- Message Note -->
    <div class="support-field">
      <label for="emailBodyInput">Your Question / Message (Optional):</label>
      <textarea id="emailBodyInput" placeholder="Describe what you need assistance with..."></textarea>
    </div>

    <div class="support-actions">
      <button type="button" class="btn-send-email" onclick="launchEmailClient()">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
        Send via Email App
      </button>
      <button type="button" class="btn-copy-all" id="copyAllDetailsBtn" onclick="copyFullInquiryDetails()">
        Copy Details for Webmail (Gmail/Yahoo)
      </button>
    </div>
  </div>

  <!-- Modal: Multi-Tab Official Institutional Documents -->
  <div id="documentViewerModal" class="conduct-modal" style="display: none;" onclick="closeDocumentModalOnBackdrop(event)">
    <div class="conduct-modal-dialog" style="max-width: 900px; width: 95%;">
      <div class="conduct-modal-header">
        <div>
          <h3 id="docModalMainTitle">Official Institutional Documents &amp; Research Clearance</h3>
          <p id="docModalSubtitle">Ilocos Sur Polytechnic State College &bull; Tagudin Campus &bull; Capstone Study</p>
        </div>
        <button type="button" class="btn-close-modal" onclick="closeDocumentModal()">&times;</button>
      </div>

      <!-- Tab Buttons -->
      <div class="doc-modal-tabs">
        <button type="button" class="doc-tab-btn active" id="docTabBtn1" onclick="switchDocTab(1)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
          <span>1. Permit to Conduct Research</span>
        </button>
        <button type="button" class="doc-tab-btn" id="docTabBtn2" onclick="switchDocTab(2)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M3 21h18M3 10h18M5 10v11M19 10v11M9 10v11M15 10v11M4 10l8-6 8 6"/></svg>
          <span>2. Official Deployment Letter</span>
        </button>
        <button type="button" class="doc-tab-btn" id="docTabBtn3" onclick="switchDocTab(3)">
          <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
          <span>3. English Critique Certification</span>
        </button>
      </div>

      <!-- Tab Panes -->
      <div class="conduct-modal-body" style="padding: 16px; max-height: 72vh; overflow-y: auto;">
        <!-- Tab 1: Permit to Conduct Research -->
        <div class="doc-tab-pane active" id="docTabPane1">
          <div style="margin-bottom: 12px; text-align: left; background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--border); font-size: 0.82rem; color: var(--text-secondary);">
            <strong style="color: var(--text);">Permit to Conduct Research:</strong> Formal authorization issued by Campus Leadership allowing the capstone researchers to administer student evaluation sessions and staff workflow trials at ISPSC Tagudin Campus.
          </div>
          <a href="/assets/docs/letter-of-conduct.png" target="_blank" title="Click to open original scan in new tab">
            <img src="/assets/docs/letter-of-conduct.png" alt="Signed Permit to Conduct Research - ISPSC Tagudin Campus" style="width: 100%; max-height: 60vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,0.12);" />
          </a>
        </div>

        <!-- Tab 2: Deployment Letter -->
        <div class="doc-tab-pane" id="docTabPane2">
          <div style="margin-bottom: 12px; text-align: left; background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--border); font-size: 0.82rem; color: var(--text-secondary);">
            <strong style="color: var(--text);">Official Deployment Letter:</strong> Formal request and approval for institutional system deployment, account provisioning, and operational evaluation at the Guidance and Registrar Offices. Addressed to Campus Director Dr. Daniel Juan B. Ramirez, OIC Registrar II Alexander B. Ligawad, and Guidance Counselor Raveena Galope, RGC, RPm.
          </div>
          <a href="/assets/docs/deployment-letter.jpg" target="_blank" title="Click to open original scan in new tab">
            <img src="/assets/docs/deployment-letter.jpg" alt="Official Deployment Letter - ISPSC Tagudin Guidance and Registrar" style="width: 100%; max-height: 60vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,0.12);" />
          </a>
        </div>

        <!-- Tab 3: English Critique Letter -->
        <div class="doc-tab-pane" id="docTabPane3">
          <div style="margin-bottom: 12px; text-align: left; background: #fff; padding: 10px 14px; border-radius: 6px; border: 1px solid var(--border); font-size: 0.82rem; color: var(--text-secondary);">
            <strong style="color: var(--text);">Research Instrument English Critique &amp; Certification:</strong> Academic certification issued by Language Critic / English Expert Imelda Binay-an, PhD, validating grammatical accuracy, stylistic clarity, and readability of the TAM evaluation instruments.
          </div>
          <a href="/assets/docs/english-critique.jpg" target="_blank" title="Click to open original scan in new tab">
            <img src="/assets/docs/english-critique.jpg" alt="English Critique and Validation Letter - Dr. Imelda Binay-an" style="width: 100%; max-height: 60vh; object-fit: contain; border-radius: 6px; box-shadow: 0 4px 14px rgba(0,0,0,0.12);" />
          </a>
        </div>
      </div>

      <div class="conduct-modal-footer">
        <span id="docModalFooterNote" style="font-size: 0.8rem; color: var(--text-muted);">Official Institutional Document &bull; ISPSC Tagudin Campus</span>
        <div style="display: flex; gap: 8px;">
          <a id="docModalOpenScanBtn" href="/assets/docs/letter-of-conduct.png" target="_blank" class="btn btn-outline" style="padding: 7px 14px; font-size: 0.84rem; text-decoration: none;">
            Open High-Res Scan &nearr;
          </a>
          <button type="button" class="btn btn-primary" style="padding: 7px 16px; font-size: 0.84rem;" onclick="closeDocumentModal()">Close</button>
        </div>
      </div>
    </div>
  </div>

  <script src="https://www.youtube.com/iframe_api"></script>
  <script>
    const docFiles = {
      1: { url: '/assets/docs/letter-of-conduct.png', title: 'Permit to Conduct Research', note: 'Appendix A: Signed Letter of Conduct' },
      2: { url: '/assets/docs/deployment-letter.jpg', title: 'Official Deployment Letter', note: 'Deployment Authority &bull; Guidance & Registrar Offices' },
      3: { url: '/assets/docs/english-critique.jpg', title: 'English Critique Certification', note: 'Instrument Validation &bull; Imelda Binay-an, PhD' }
    };

    function openDocumentModal(tabIndex = 1) {
      const modal = document.getElementById('documentViewerModal');
      if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        switchDocTab(tabIndex);
      }
    }

    function closeDocumentModal() {
      const modal = document.getElementById('documentViewerModal');
      if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = '';
      }
    }

    function closeDocumentModalOnBackdrop(e) {
      if (e.target && e.target.id === 'documentViewerModal') {
        closeDocumentModal();
      }
    }

    function switchDocTab(tabIndex) {
      document.querySelectorAll('.doc-tab-btn').forEach(btn => btn.classList.remove('active'));
      document.querySelectorAll('.doc-tab-pane').forEach(pane => pane.classList.remove('active'));

      const activeBtn = document.getElementById('docTabBtn' + tabIndex);
      const activePane = document.getElementById('docTabPane' + tabIndex);
      if (activeBtn) activeBtn.classList.add('active');
      if (activePane) activePane.classList.add('active');

      const info = docFiles[tabIndex] || docFiles[1];
      const openBtn = document.getElementById('docModalOpenScanBtn');
      const footerNote = document.getElementById('docModalFooterNote');
      if (openBtn) openBtn.href = info.url;
      if (footerNote) footerNote.innerHTML = info.note;
    }

    // Maintain backwards compatibility with conductModal callers
    function openConductModal() {
      openDocumentModal(1);
    }
    function closeConductModal() {
      closeDocumentModal();
    }

    window.openDocumentModal = openDocumentModal;
    window.closeDocumentModal = closeDocumentModal;
    window.switchDocTab = switchDocTab;
    window.openConductModal = openConductModal;
    window.closeConductModal = closeConductModal;
  </script>
</body>
</html>
