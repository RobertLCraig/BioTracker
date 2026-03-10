<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>BioTracker API</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: ui-sans-serif, system-ui, -apple-system, sans-serif;
            background: #0f172a;
            color: #e2e8f0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }
        .card {
            background: #1e293b;
            border: 1px solid #334155;
            border-radius: 12px;
            padding: 2.5rem;
            max-width: 680px;
            width: 100%;
        }
        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            margin-bottom: 1.5rem;
        }
        .logo-icon {
            width: 44px; height: 44px;
            background: #3b82f6;
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
            font-size: 22px;
        }
        h1 { font-size: 1.75rem; font-weight: 700; color: #f1f5f9; }
        .tagline { color: #94a3b8; font-size: 0.95rem; margin-bottom: 2rem; line-height: 1.6; }
        .badge {
            display: inline-block;
            background: #1d4ed8;
            color: #bfdbfe;
            font-size: 0.7rem;
            font-weight: 600;
            padding: 2px 8px;
            border-radius: 99px;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-left: 8px;
            vertical-align: middle;
        }
        h2 { font-size: 0.8rem; font-weight: 600; color: #64748b; text-transform: uppercase; letter-spacing: 0.08em; margin-bottom: 0.75rem; }
        .endpoint-list { list-style: none; margin-bottom: 1.75rem; }
        .endpoint-list li {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 6px 0;
            border-bottom: 1px solid #1e293b;
            font-size: 0.85rem;
        }
        .method {
            font-size: 0.7rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 4px;
            min-width: 52px;
            text-align: center;
        }
        .get    { background: #064e3b; color: #6ee7b7; }
        .post   { background: #1e3a5f; color: #93c5fd; }
        .put    { background: #3b1f06; color: #fcd34d; }
        .delete { background: #3b0606; color: #fca5a5; }
        .path { font-family: ui-monospace, monospace; color: #cbd5e1; }
        .desc { color: #64748b; font-size: 0.78rem; margin-left: auto; }
        .features {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.6rem;
            margin-bottom: 1.75rem;
        }
        .feature {
            background: #0f172a;
            border: 1px solid #334155;
            border-radius: 8px;
            padding: 0.7rem 1rem;
            font-size: 0.82rem;
            color: #94a3b8;
        }
        .feature strong { color: #e2e8f0; display: block; margin-bottom: 2px; }
        .footer { color: #475569; font-size: 0.75rem; text-align: center; margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid #1e293b; }
        a { color: #60a5fa; text-decoration: none; }
        a:hover { text-decoration: underline; }
        .status-dot { width: 8px; height: 8px; background: #22c55e; border-radius: 50%; display: inline-block; margin-right: 6px; animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100%{opacity:1} 50%{opacity:0.4} }
    </style>
</head>
<body>
<div class="card">
    <div class="logo">
        <div class="logo-icon">🫀</div>
        <div>
            <h1>BioTracker</h1>
            <div style="color:#64748b; font-size:0.8rem;">Personal Health Journal API</div>
        </div>
    </div>

    <p class="tagline">
        <span class="status-dot"></span>API is running &nbsp;·&nbsp;
        All endpoints are under <code style="color:#7dd3fc; font-family:monospace;">/api/v1/</code>
        &nbsp;·&nbsp; Authenticate with Sanctum Bearer tokens.
    </p>

    <h2>Authentication</h2>
    <ul class="endpoint-list">
        <li><span class="method post">POST</span><span class="path">/api/v1/register</span><span class="desc">Create account</span></li>
        <li><span class="method post">POST</span><span class="path">/api/v1/login</span><span class="desc">Login (returns token or TOTP challenge)</span></li>
        <li><span class="method post">POST</span><span class="path">/api/v1/login/totp</span><span class="desc">Complete MFA verification</span></li>
        <li><span class="method post">POST</span><span class="path">/api/v1/logout</span><span class="desc">Revoke token</span></li>
    </ul>

    <h2>Health Tracking <span class="badge">Protected</span></h2>
    <ul class="endpoint-list">
        <li><span class="method get">GET</span><span class="path">/api/v1/activity-logs</span><span class="desc">Food, drink, exercise, sleep</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/excretion-logs</span><span class="desc">Excretion with Bristol scale</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/vital-logs</span><span class="desc">Weight, BP, heart rate, SpO₂</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/symptom-logs</span><span class="desc">Symptoms with severity</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/medications</span><span class="desc">Medication registry</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/medication-logs</span><span class="desc">Dose history</span></li>
    </ul>

    <h2>Gamification <span class="badge">Protected</span></h2>
    <ul class="endpoint-list">
        <li><span class="method get">GET</span><span class="path">/api/v1/points</span><span class="desc">Balance + recent entries</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/streaks</span><span class="desc">Current &amp; longest streak</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/achievements</span><span class="desc">All achievements + unlock status</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/tasks</span><span class="desc">User tasks</span></li>
    </ul>

    <h2>Analytics &amp; Reports <span class="badge">Protected</span></h2>
    <ul class="endpoint-list">
        <li><span class="method get">GET</span><span class="path">/api/v1/analytics/dashboard</span><span class="desc">Today's snapshot</span></li>
        <li><span class="method get">GET</span><span class="path">/api/v1/analytics/trends</span><span class="desc">7d/30d/90d chart data</span></li>
        <li><span class="method post">POST</span><span class="path">/api/v1/reports/export</span><span class="desc">PDF or CSV health report</span></li>
    </ul>

    <h2>GDPR <span class="badge">Protected</span></h2>
    <ul class="endpoint-list">
        <li><span class="method get">GET</span><span class="path">/api/v1/user/data-export</span><span class="desc">Export all your data (JSON)</span></li>
        <li><span class="method delete">DEL</span><span class="path">/api/v1/user/account</span><span class="desc">Permanently delete account</span></li>
    </ul>

    <h2>Security Features</h2>
    <div class="features">
        <div class="feature"><strong>TOTP MFA</strong>Optional TOTP (Google Authenticator) with 8 recovery codes</div>
        <div class="feature"><strong>Field Encryption</strong>AES-256 on health notes, medication names, symptoms</div>
        <div class="feature"><strong>Data Isolation</strong>Global scope — users can never see others' data</div>
        <div class="feature"><strong>Audit Logging</strong>HIPAA-compliant audit trail on every data access</div>
        <div class="feature"><strong>GDPR Ready</strong>Export + erasure endpoints, consent timestamps</div>
        <div class="feature"><strong>Session Timeout</strong>Auto-revoke tokens after 15 minutes of inactivity</div>
    </div>

    <div class="footer">
        BioTracker v1 &nbsp;·&nbsp;
        <a href="https://github.com/RobertLCraig/BioTracker">GitHub</a> &nbsp;·&nbsp;
        Laravel {{ app()->version() }} &nbsp;·&nbsp;
        PHP {{ PHP_VERSION }}
    </div>
</div>
</body>
</html>
