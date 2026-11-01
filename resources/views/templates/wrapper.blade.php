<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'N1X PANEL') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/favicons/apple-touch-icon.png">
            <link rel="icon" type="image/png" href="/favicons/favicon-32x32.png" sizes="32x32">
            <link rel="icon" type="image/png" href="/favicons/favicon-16x16.png" sizes="16x16">
            <link rel="manifest" href="/favicons/manifest.json">
            <link rel="mask-icon" href="/favicons/safari-pinned-tab.svg" color="#22c55e">
            <link rel="shortcut icon" href="/favicons/favicon.ico">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#22c55e">
        @show

        <style>
            :root {
                --n1x-bg: #0B0F0E;
                --n1x-sidebar: #101715;
                --n1x-card: #151E1A;
                --n1x-green: #22C55E;
                --n1x-green-sec: #16A34A;
                --n1x-green-accent: #86EFAC;
                --n1x-text: #F0FDF4;
                --n1x-text-sec: #94A3A0;
                --n1x-border: #26352E;
            }
            html, body {
                background-color: #0B0F0E !important;
                color: #F0FDF4 !important;
                margin: 0;
                padding: 0;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            /* Dark background overrides for React components */
            .bg-neutral-900, .bg-neutral-800, .bg-gray-900, .bg-gray-800 {
                background-color: #0B0F0E !important;
            }
            .bg-neutral-700, .bg-gray-700 {
                background-color: #101715 !important;
            }
            .bg-neutral-600, .bg-gray-600 {
                background-color: #151E1A !important;
            }
            /* Green brand highlights */
            .text-primary-400, .text-primary-500, .text-cyan-500, .text-blue-500 {
                color: #22C55E !important;
            }
            .bg-primary-500, .bg-primary-600, .bg-cyan-500, .bg-blue-600 {
                background-color: #22C55E !important;
                color: #0B0F0E !important;
            }
            .border-primary-500 {
                border-color: #22C55E !important;
            }
            /* Initial Loader - Prevents white screen */
            #n1x-loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: #0B0F0E;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                transition: opacity 0.3s ease;
            }
            .n1x-spinner {
                width: 48px;
                height: 48px;
                border: 3px solid rgba(34, 197, 94, 0.2);
                border-top-color: #22C55E;
                border-radius: 50%;
                animation: n1x-spin 0.8s linear infinite;
            }
            @keyframes n1x-spin {
                to { transform: rotate(360deg); }
            }
        </style>

        @section('user-data')
            @if(!is_null(Auth::user()))
                <script>
                    window.PterodactylUser = {!! json_encode(Auth::user()->toVueObject()) !!};
                </script>
            @endif
            @if(!empty($siteConfiguration))
                <script>
                    window.SiteConfiguration = {!! json_encode($siteConfiguration) !!};
                </script>
            @endif
        @show

        @yield('assets')

        @include('layouts.scripts')
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-900' }}">
        <div id="n1x-loader">
            <div class="n1x-spinner"></div>
            <p style="color: #22C55E; margin-top: 16px; font-weight: 700; letter-spacing: 0.15em; font-size: 14px;">N1X PANEL</p>
        </div>

        @section('content')
            @yield('above-container')
            @yield('container')
            @yield('below-container')
        @show
        @section('scripts')
            {!! $asset->js('main.js') !!}
        @show

        <script>
            document.addEventListener('DOMContentLoaded', function() {
                var app = document.getElementById('app');
                function removeLoader() {
                    var loader = document.getElementById('n1x-loader');
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(function() { if (loader.parentNode) loader.remove(); }, 350);
                    }
                }
                if (app) {
                    if (app.children.length > 0) {
                        removeLoader();
                    } else {
                        var observer = new MutationObserver(function() {
                            if (app.children.length > 0) {
                                removeLoader();
                                observer.disconnect();
                            }
                        });
                        observer.observe(app, { childList: true, subtree: true });
                        // Fallback removal after 3 seconds if app already rendered
                        setTimeout(removeLoader, 3000);
                    }
                } else {
                    removeLoader();
                }
            });
        </script>
    </body>
</html>
