<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <script>
            (function() {
                var t = null;
                try { t = localStorage.getItem('theme'); } catch (e) {}
                var m = false;
                try { m = Boolean(window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches); } catch (e) {}
                var d = t === 'dark' || (t !== 'light' && m);
                try { document.documentElement.classList.toggle('dark', d); } catch (e) {}

                var s = null;
                try { s = localStorage.getItem('app_scale'); } catch (e) {}
                if (s === '115') { document.documentElement.style.fontSize = '16.1px'; }
                else if (s === '125') { document.documentElement.style.fontSize = '17.5px'; }
                else if (s === '100') { document.documentElement.style.fontSize = '14px'; }
            })();
        </script>

        <title inertia>{{ config('app.name', 'NEXIAM System') }}</title>
        <link rel="icon" type="image/x-icon" href="/favicon.ico">
        <link rel="icon" type="image/png" sizes="32x32" href="/assets/branding/nexiam/nexiam-logo-only.png">
        <link rel="apple-touch-icon" href="/assets/branding/nexiam/nexiam-logo-only.png">
        <link rel="preload" as="image" href="/assets/branding/nexiam/nexiam-logo-only.webp" type="image/webp">
        <link rel="manifest" href="/manifest.webmanifest">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-status-bar-style" content="default">
        <meta name="apple-mobile-web-app-title" content="NEXIAM">
        <script>
            if ('serviceWorker' in navigator) {
                if (document.readyState === 'complete') {
                    navigator.serviceWorker.register('/sw.js').catch(function() {});
                } else {
                    window.addEventListener('load', function() {
                        navigator.serviceWorker.register('/sw.js').catch(function() {});
                    });
                }
            }
        </script>

        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Sans:ital,wght@0,400;0,500;0,600;0,700;1,400;1,600&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">

        <!-- Preload institutional backdrops into browser memory cache -->
        <link rel="preload" as="image" href="/assets/branding/ispsc/backgrounds/campus-hero.webp" type="image/webp" media="(min-width: 640px)">
        <link rel="preload" as="image" href="/assets/branding/ispsc/backgrounds/campus-hero-mobile.webp" type="image/webp" media="(max-width: 639px)">
        @vite(['resources/js/app.ts'])
        @inertiaHead
    </head>
    <body class="antialiased">
        <!-- NEXIAM Cold-Start Splash (First visit per session, self-dismissing, non-blocking) -->
        <div id="nexiam-app-splash" aria-hidden="true" style="display:none;position:fixed;inset:0;z-index:99999;flex-direction:column;align-items:center;justify-content:center;background-color:#ffffff;pointer-events:none;transition:opacity 0.35s ease-out, visibility 0.35s ease-out;">
            <style>
                html.dark #nexiam-app-splash { background-color: #09090b !important; }
                @keyframes nexiamSplashPulse {
                    0%, 100% { transform: scale(1); opacity: 0.92; }
                    50% { transform: scale(1.05); opacity: 1; }
                }
                @keyframes nexiamSplashBar {
                    0% { transform: translateX(-100%); }
                    50% { transform: translateX(50%); }
                    100% { transform: translateX(200%); }
                }
                .nexiam-splash-icon {
                    width: 68px;
                    height: 68px;
                    animation: nexiamSplashPulse 1.8s ease-in-out infinite;
                }
                .nexiam-splash-bar {
                    width: 48px;
                    height: 2px;
                    background: rgba(37,99,235,0.18);
                    border-radius: 9999px;
                    overflow: hidden;
                    position: relative;
                }
                .nexiam-splash-bar-inner {
                    position: absolute;
                    top: 0;
                    left: 0;
                    bottom: 0;
                    width: 50%;
                    background: #2563eb;
                    border-radius: 9999px;
                    animation: nexiamSplashBar 1.2s cubic-bezier(0.4, 0, 0.2, 1) infinite;
                }
                @media (prefers-reduced-motion: reduce) {
                    .nexiam-splash-icon, .nexiam-splash-bar-inner { animation: none !important; }
                }
                #nexiam-app-splash.splash-dismissed {
                    opacity: 0 !important;
                    visibility: hidden !important;
                }
            </style>
            <div style="display:flex;flex-direction:column;align-items:center;gap:14px;">
                <img src="/assets/branding/nexiam/nexiam-logo-only.webp" onerror="this.onerror=null;this.src='/assets/branding/nexiam/nexiam-logo-only.png'" alt="" class="nexiam-splash-icon" style="object-fit:contain;" />
                <div class="nexiam-splash-bar">
                    <div class="nexiam-splash-bar-inner"></div>
                </div>
            </div>
            <script>
                (function() {
                    try {
                        var seen = sessionStorage.getItem('nexiam_splash_v1');
                        var el = document.getElementById('nexiam-app-splash');
                        if (!el) return;
                        if (seen) {
                            el.remove();
                            return;
                        }
                        sessionStorage.setItem('nexiam_splash_v1', '1');
                        el.style.display = 'flex';

                        var dismissed = false;
                        function dismiss() {
                            if (dismissed) return;
                            dismissed = true;
                            el.classList.add('splash-dismissed');
                            setTimeout(function() {
                                if (el.parentNode) el.parentNode.removeChild(el);
                            }, 380);
                        }
                        window.__dismissNexiamSplash = dismiss;
                        setTimeout(dismiss, 1200);
                    } catch (e) {
                        var fallbackEl = document.getElementById('nexiam-app-splash');
                        if (fallbackEl) fallbackEl.remove();
                    }
                })();
            </script>
        </div>
        @inertia
    </body>
</html>
