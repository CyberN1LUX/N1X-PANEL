<!DOCTYPE html>
<html>
    <head>
        <title>{{ config('app.name', 'N1LUX PETRO') }}</title>

        @section('meta')
            <meta charset="utf-8">
            <meta http-equiv="X-UA-Compatible" content="IE=edge">
            <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
            <meta name="csrf-token" content="{{ csrf_token() }}">
            <meta name="robots" content="noindex">
            <link rel="apple-touch-icon" sizes="180x180" href="/assets/n1lux-logo.jpg">
            <link rel="icon" type="image/jpeg" href="/assets/n1lux-logo.jpg">
            <link rel="shortcut icon" href="/assets/n1lux-logo.jpg">
            <meta name="msapplication-config" content="/favicons/browserconfig.xml">
            <meta name="theme-color" content="#1A1A1A">
        @show

        <style>
            :root {
                --one: #1A1A1A;
                --two: #262626;
                --three: #333333;
                --bg: url("/themes/recolor/dark.jpg");
            }
            html, body {
                background-color: #1A1A1A !important;
                background-image: var(--bg);
                background-attachment: fixed;
                background-repeat: no-repeat;
                background-position: center center;
                background-size: cover;
                color: #E0E0E0 !important;
                margin: 0;
                padding: 0;
                min-height: 100vh;
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            }
            #app, div[id="app"] {
                background-color: #1A1A1A !important;
                min-height: 100vh;
            }
            /* Dark background overrides for React components - Prevent white flashing */
            .bg-neutral-900, .bg-neutral-800, .bg-gray-900, .bg-gray-800 {
                background-color: rgba(26, 26, 26, 0.95) !important;
            }
            .bg-neutral-700, .bg-gray-700 {
                background-color: rgba(38, 38, 38, 0.95) !important;
            }
            .bg-neutral-600, .bg-gray-600 {
                background-color: rgba(51, 51, 51, 0.95) !important;
            }
            .bg-white {
                background-color: #242424 !important;
                color: #E0E0E0 !important;
            }
            /* Recolor dark style for login form card */
            .LoginFormContainer__Container-sc-cyh04c-0 > form > div,
            .LoginFormContainer___StyledDiv-sc-cyh04c-3 {
                background-color: #222222 !important;
                border: 1px solid #333333 !important;
                color: #E0E0E0 !important;
            }
            /* Initial Loader - Recolor Dark Theme */
            #n1x-loader {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: #1A1A1A;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 99999;
                transition: opacity 0.35s ease;
            }
            .n1x-spinner {
                width: 44px;
                height: 44px;
                border: 3px solid rgba(255, 255, 255, 0.12);
                border-top-color: #3b82f6;
                border-radius: 50%;
                animation: n1x-spin 0.75s linear infinite;
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

        <link rel="stylesheet" href="/themes/recolor/recolor.css">
    </head>
    <body class="{{ $css['body'] ?? 'bg-neutral-900' }}">
        <div id="n1x-loader">
            <img src="/assets/n1lux-logo.jpg" alt="N1LUX PETRO" style="width: 54px; height: 54px; border-radius: 12px; box-shadow: 0 0 20px rgba(132, 204, 22, 0.4); margin-bottom: 14px; object-fit: cover;">
            <div class="n1x-spinner"></div>
            <p style="color: #94a3b8; margin-top: 14px; font-weight: 700; letter-spacing: 0.12em; font-size: 13px;">N1LUX PETRO</p>
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
                var loader = document.getElementById('n1x-loader');

                function removeLoader() {
                    if (loader) {
                        loader.style.opacity = '0';
                        setTimeout(function() { 
                            if (loader && loader.parentNode) loader.parentNode.removeChild(loader); 
                        }, 350);
                    }
                }

                if (app && app.children.length > 0) {
                    removeLoader();
                } else if (app) {
                    var observer = new MutationObserver(function() {
                        if (app.children.length > 0) {
                            removeLoader();
                            observer.disconnect();
                        }
                    });
                    observer.observe(app, { childList: true, subtree: true });
                    // Maximum wait before revealing DOM
                    setTimeout(removeLoader, 4000);
                } else {
                    removeLoader();
                }
            });
        </script>
    </body>
</html>
