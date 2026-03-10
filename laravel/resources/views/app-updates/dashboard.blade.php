<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>App Updates</title>
    <style>
        :root {
            --bg: #f3efe6;
            --panel: rgba(255, 252, 247, 0.9);
            --ink: #1f2937;
            --muted: #6b7280;
            --line: rgba(31, 41, 55, 0.12);
            --accent: #c2410c;
            --accent-soft: rgba(194, 65, 12, 0.12);
            --ok: #166534;
            --warn: #9a3412;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Georgia, "Times New Roman", serif;
            color: var(--ink);
            background:
                radial-gradient(circle at top left, rgba(194, 65, 12, 0.16), transparent 28%),
                radial-gradient(circle at bottom right, rgba(21, 128, 61, 0.12), transparent 24%),
                linear-gradient(135deg, #f7f1e7, #efe7da 60%, #e7dcc7);
            min-height: 100vh;
        }

        .wrap {
            width: min(1120px, calc(100% - 32px));
            margin: 0 auto;
            padding: 40px 0 56px;
        }

        .hero {
            display: grid;
            gap: 12px;
            padding: 24px 28px;
            border: 1px solid var(--line);
            border-radius: 28px;
            background: linear-gradient(145deg, rgba(255, 255, 255, 0.72), rgba(255, 248, 240, 0.92));
            box-shadow: 0 18px 50px rgba(31, 41, 55, 0.08);
            margin-bottom: 24px;
        }

        .eyebrow {
            margin: 0;
            font-size: 12px;
            letter-spacing: 0.18em;
            text-transform: uppercase;
            color: var(--accent);
        }

        h1 {
            margin: 0;
            font-size: clamp(32px, 5vw, 58px);
            line-height: 0.95;
            font-weight: 700;
        }

        .sub {
            margin: 0;
            color: var(--muted);
            max-width: 700px;
            font-size: 16px;
            line-height: 1.6;
        }

        .stats {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 8px;
        }

        .stat {
            padding: 10px 14px;
            border-radius: 999px;
            border: 1px solid var(--line);
            background: rgba(255, 255, 255, 0.55);
            font-size: 14px;
        }

        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 18px;
        }

        .card {
            border: 1px solid var(--line);
            border-radius: 24px;
            background: var(--panel);
            padding: 20px;
            box-shadow: 0 14px 36px rgba(31, 41, 55, 0.08);
            backdrop-filter: blur(10px);
        }

        .card-head {
            display: flex;
            justify-content: space-between;
            gap: 12px;
            align-items: start;
            margin-bottom: 14px;
        }

        .title {
            margin: 0;
            font-size: 24px;
        }

        .channel {
            display: inline-block;
            margin-top: 6px;
            padding: 4px 10px;
            border-radius: 999px;
            background: var(--accent-soft);
            color: var(--accent);
            font-size: 12px;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .version {
            font-size: 26px;
            font-weight: 700;
            white-space: nowrap;
        }

        .meta {
            display: grid;
            gap: 8px;
            margin: 16px 0;
            font-size: 14px;
            color: var(--muted);
        }

        .meta strong {
            color: var(--ink);
        }

        .notes {
            margin: 0 0 18px;
            padding: 14px;
            border-radius: 16px;
            background: rgba(255, 255, 255, 0.75);
            border: 1px solid rgba(31, 41, 55, 0.08);
            font-size: 14px;
            line-height: 1.6;
        }

        .actions {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            min-height: 42px;
            padding: 0 14px;
            border-radius: 999px;
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
            border: 1px solid var(--line);
            color: var(--ink);
            background: #fff;
        }

        .btn-primary {
            background: var(--ink);
            color: #fff;
            border-color: var(--ink);
        }

        .mandatory {
            color: var(--warn);
            font-weight: 700;
        }

        .optional {
            color: var(--ok);
            font-weight: 700;
        }

        .empty {
            padding: 28px;
            border-radius: 24px;
            border: 1px dashed var(--line);
            background: rgba(255, 255, 255, 0.5);
            color: var(--muted);
        }

        code {
            font-family: "Consolas", "Courier New", monospace;
            font-size: 0.95em;
        }
    </style>
</head>
<body>
    <div class="wrap">
        <section class="hero">
            <p class="eyebrow">Release dashboard</p>
            <h1>App update inventory</h1>
            <p class="sub">Trang nay doc truc tiep metadata trong storage va hien thi ban cap nhat moi nhat theo tung <code>app_slug</code> va <code>channel</code>.</p>
            <div class="stats">
                <div class="stat">{{ $releases->count() }} release channel</div>
                <div class="stat">{{ $releases->pluck('app_slug')->unique()->count() }} app</div>
            </div>
        </section>

        @if ($releases->isEmpty())
            <div class="empty">Chua co metadata update nao trong <code>storage/app/public/app-updates</code>.</div>
        @else
            <section class="grid">
                @foreach ($releases as $release)
                    <article class="card">
                        <div class="card-head">
                            <div>
                                <h2 class="title">{{ $release['app_slug'] }}</h2>
                                <span class="channel">{{ $release['channel'] }}</span>
                            </div>
                            <div class="version">v{{ $release['version'] }}</div>
                        </div>

                        <div class="meta">
                            <div><strong>Published:</strong> {{ $release['published_at'] ?: '-' }}</div>
                            <div><strong>Size:</strong> {{ number_format($release['size'] / 1048576, 2) }} MB</div>
                            <div><strong>Mode:</strong> <span class="{{ $release['mandatory'] ? 'mandatory' : 'optional' }}">{{ $release['mandatory'] ? 'Mandatory' : 'Optional' }}</span></div>
                            <div><strong>SHA256:</strong> <code>{{ $release['sha256'] ?: '-' }}</code></div>
                        </div>

                        <p class="notes">{{ $release['notes'] !== '' ? $release['notes'] : 'Khong co release note.' }}</p>

                        <div class="actions">
                            <a class="btn btn-primary" href="{{ $release['download_url'] }}">Download</a>
                            <a class="btn" href="{{ $release['latest_url'] }}">Latest JSON</a>
                        </div>
                    </article>
                @endforeach
            </section>
        @endif
    </div>
</body>
</html>
