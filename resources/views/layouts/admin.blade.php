<!DOCTYPE html>
<html>
    <head>
        <meta charset="utf-8">
        <meta http-equiv="X-UA-Compatible" content="IE=edge">
        <title>{{ config('app.name', 'N1LUX PETRO') }} - @yield('title')</title>
        <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
        <meta name="_token" content="{{ csrf_token() }}">

        <link rel="apple-touch-icon" sizes="180x180" href="/assets/n1lux-logo.jpg">
        <link rel="icon" type="image/jpeg" href="/assets/n1lux-logo.jpg">
        <link rel="shortcut icon" href="/assets/n1lux-logo.jpg">
        <meta name="msapplication-config" content="/favicons/browserconfig.xml">
        <meta name="theme-color" content="#1A1A1A">

        @include('layouts.scripts')

        @section('scripts')
            {!! Theme::css('vendor/select2/select2.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/bootstrap/bootstrap.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/admin.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/adminlte/colors/skin-blue.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/sweetalert/sweetalert.min.css?t={cache-version}') !!}
            {!! Theme::css('vendor/animate/animate.min.css?t={cache-version}') !!}
            {!! Theme::css('css/pterodactyl.css?t={cache-version}') !!}
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/ionicons/2.0.1/css/ionicons.min.css">
            <link rel="stylesheet" href="/themes/recolor/recolor.css">

            <style>
                /* Recolor Blueprint Dark Theme for AdminLTE */
                body.skin-blue, .wrapper, .content-wrapper, .main-footer, .modal-content {
                    background-image: url("/themes/recolor/dark.jpg") !important;
                    background-attachment: fixed !important;
                    background-repeat: no-repeat !important;
                    background-position: center center !important;
                    background-size: cover !important;
                    background-color: #1A1A1A !important;
                    color: #E0E0E0 !important;
                }
                .content-wrapper {
                    background: transparent !important;
                }
                .main-header .navbar, .main-header .logo {
                    background-color: rgba(26, 26, 26, 0.95) !important;
                    backdrop-filter: blur(10px) !important;
                    border-bottom: 1px solid #333333 !important;
                    color: #FFFFFF !important;
                }
                .main-header .logo span {
                    color: #FFFFFF !important;
                    font-weight: 700;
                }
                .main-sidebar, .sidebar {
                    background-color: rgba(26, 26, 26, 0.95) !important;
                    backdrop-filter: blur(10px) !important;
                    border-right: 1px solid #333333 !important;
                }
                .sidebar-menu > li.header {
                    background: rgba(18, 18, 18, 0.9) !important;
                    color: #888888 !important;
                    border-bottom: 1px solid #333333;
                }
                .sidebar-menu > li > a {
                    color: #B0B0B0 !important;
                    border-left: 3px solid transparent;
                }
                .sidebar-menu > li:hover > a, .sidebar-menu > li.active > a {
                    background-color: #333333 !important;
                    color: #FFFFFF !important;
                    border-left-color: #585858 !important;
                }
                .box {
                    background: rgba(26, 26, 26, 0.9) !important;
                    backdrop-filter: blur(12px) !important;
                    -webkit-backdrop-filter: blur(12px) !important;
                    border: 1px solid #333333 !important;
                    border-top: 3px solid #585858 !important;
                    color: #E0E0E0 !important;
                    border-radius: 8px !important;
                    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.5) !important;
                }
                .box-header {
                    border-bottom: 1px solid #333333 !important;
                    color: #FFFFFF !important;
                }
                .box-header .box-title, .box-header h3 {
                    color: #FFFFFF !important;
                }
                .box-footer {
                    background-color: rgba(26, 26, 26, 0.9) !important;
                    border-top: 1px solid #333333 !important;
                }
                .form-control, .select2-container--default .select2-selection--single {
                    background-color: #262626 !important;
                    border: 1px solid #444444 !important;
                    color: #FFFFFF !important;
                    border-radius: 6px !important;
                }
                .form-control:focus {
                    border-color: #777777 !important;
                    box-shadow: 0 0 5px rgba(255, 255, 255, 0.2);
                }
                .table {
                    color: #E0E0E0 !important;
                }
                .table > tbody > tr > td, .table > tbody > tr > th, .table > tfoot > tr > td, .table > tfoot > tr > th, .table > thead > tr > td, .table > thead > tr > th {
                    border-top: 1px solid #333333 !important;
                }
                .table-hover > tbody > tr:hover {
                    background-color: rgba(255, 255, 255, 0.05) !important;
                }
                .btn-primary {
                    background-color: #3b82f6 !important;
                    border-color: #2563eb !important;
                    color: #FFFFFF !important;
                }
                .btn-success {
                    background-color: #10b981 !important;
                    border-color: #059669 !important;
                    color: #FFFFFF !important;
                }
                .btn-default {
                    background-color: #2A2A2A !important;
                    border-color: #444444 !important;
                    color: #E0E0E0 !important;
                }
                .btn-default:hover {
                    background-color: #383838 !important;
                    color: #FFFFFF !important;
                }
                .nav-tabs-custom {
                    background: #1A1A1A !important;
                    border: 1px solid #333333 !important;
                }
                .nav-tabs-custom > .nav-tabs > li.active {
                    border-top-color: #585858 !important;
                }
                .nav-tabs-custom > .nav-tabs > li.active > a {
                    background-color: #1A1A1A !important;
                    color: #FFFFFF !important;
                }
                .content-header > h1 {
                    color: #FFFFFF !important;
                }
                .content-header > .breadcrumb > li > a {
                    color: #888888 !important;
                }
                .content-header > .breadcrumb > li.active {
                    color: #CCCCCC !important;
                }
            </style>

            <!--[if lt IE 9]>
            <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
            <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
            <![endif]-->
        @show
    </head>
    <body class="hold-transition skin-blue fixed sidebar-mini">
        <div class="wrapper">
            <header class="main-header">
                <a href="{{ route('index') }}" class="logo">
                    <img src="/assets/n1lux-logo.jpg" alt="N1LUX PETRO" style="height: 28px; width: 28px; border-radius: 6px; vertical-align: middle; margin-right: 8px; display: inline-block; object-fit: cover; box-shadow: 0 0 8px rgba(132, 204, 22, 0.4);">
                    <span>{{ config('app.name', 'N1LUX PETRO') }}</span>
                </a>
                <nav class="navbar navbar-static-top">
                    <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
                        <span class="sr-only">Toggle navigation</span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                        <span class="icon-bar"></span>
                    </a>
                    <div class="navbar-custom-menu">
                        <ul class="nav navbar-nav">
                            <li class="user-menu">
                                <a href="{{ route('account') }}" style="display: flex; align-items: center; gap: 8px;">
                                    <img src="/assets/n1lux-logo.jpg" class="user-image" alt="N1LUX Administrator" style="width: 26px; height: 26px; border-radius: 50%; object-fit: cover; border: 1.5px solid #84cc16; margin: 0; box-shadow: 0 0 6px rgba(132, 204, 22, 0.4);">
                                    <span class="hidden-xs" style="font-weight: 600;">{{ Auth::user()->name_first ?? 'N1LUX' }} {{ Auth::user()->name_last ?? 'Admin' }}</span>
                                </a>
                            </li>
                            <li>
                                <li><a href="{{ route('index') }}" data-toggle="tooltip" data-placement="bottom" title="Exit Admin Control"><i class="fa fa-server"></i></a></li>
                            </li>
                            <li>
                                <li><a href="{{ route('auth.logout') }}" id="logoutButton" data-toggle="tooltip" data-placement="bottom" title="Logout"><i class="fa fa-sign-out"></i></a></li>
                            </li>
                        </ul>
                    </div>
                </nav>
            </header>
            <aside class="main-sidebar">
                <section class="sidebar">
                    <ul class="sidebar-menu">
                        <li class="header">BASIC ADMINISTRATION</li>
                        <li class="{{ Route::currentRouteName() !== 'admin.index' ?: 'active' }}">
                            <a href="{{ route('admin.index') }}">
                                <i class="fa fa-home"></i> <span>Overview</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.settings') ?: 'active' }}">
                            <a href="{{ route('admin.settings')}}">
                                <i class="fa fa-wrench"></i> <span>Settings</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.api') ?: 'active' }}">
                            <a href="{{ route('admin.api.index')}}">
                                <i class="fa fa-gamepad"></i> <span>Application API</span>
                            </a>
                        </li>
                        <li class="header">MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.databases') ?: 'active' }}">
                            <a href="{{ route('admin.databases') }}">
                                <i class="fa fa-database"></i> <span>Databases</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.locations') ?: 'active' }}">
                            <a href="{{ route('admin.locations') }}">
                                <i class="fa fa-globe"></i> <span>Locations</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nodes') ?: 'active' }}">
                            <a href="{{ route('admin.nodes') }}">
                                <i class="fa fa-sitemap"></i> <span>Nodes</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.servers') ?: 'active' }}">
                            <a href="{{ route('admin.servers') }}">
                                <i class="fa fa-server"></i> <span>Servers</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.users') ?: 'active' }}">
                            <a href="{{ route('admin.users') }}">
                                <i class="fa fa-users"></i> <span>Users</span>
                            </a>
                        </li>
                        <li class="header">SERVICE MANAGEMENT</li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.mounts') ?: 'active' }}">
                            <a href="{{ route('admin.mounts') }}">
                                <i class="fa fa-magic"></i> <span>Mounts</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.nests') ?: 'active' }}">
                            <a href="{{ route('admin.nests') }}">
                                <i class="fa fa-th-large"></i> <span>Nests</span>
                            </a>
                        </li>
                        <li class="{{ ! starts_with(Route::currentRouteName(), 'admin.tunnel') ?: 'active' }}">
                            <a href="{{ route('admin.tunnel') }}">
                                <i class="fa fa-cloud"></i> <span>Cloudflare Tunnel</span>
                            </a>
                        </li>
                    </ul>
                </section>
            </aside>
            <div class="content-wrapper">
                <section class="content-header">
                    @yield('content-header')
                </section>
                <section class="content">
                    <div class="row">
                        <div class="col-xs-12">
                            @if (isset($errors) && count($errors) > 0)
                                <div class="alert alert-danger">
                                    There was an error validating the data provided.<br><br>
                                    <ul>
                                        @foreach ($errors->all() as $error)
                                            <li>{{ $error }}</li>
                                        @endforeach
                                    </ul>
                                </div>
                            @endif
                            @foreach (Alert::getMessages() as $type => $messages)
                                @foreach ($messages as $message)
                                    <div class="alert alert-{{ $type }} alert-dismissable" role="alert">
                                        {{ $message }}
                                    </div>
                                @endforeach
                            @endforeach
                        </div>
                    </div>
                    @yield('content')
                </section>
            </div>
            <footer class="main-footer">
                <div class="pull-right small text-gray" style="margin-right:10px;margin-top:-7px;">
                    <strong><i class="fa fa-fw {{ $appIsGit ? 'fa-git-square' : 'fa-code-fork' }}"></i></strong> {{ $appVersion }}<br />
                    <strong><i class="fa fa-fw fa-clock-o"></i></strong> {{ round(microtime(true) - LARAVEL_START, 3) }}s
                </div>
                Copyright &copy; 2026 <a href="/">N1LUX PETRO</a>. Multi-Language Hosting Control Panel.
            </footer>
        </div>
        @section('footer-scripts')
            <script src="/js/keyboard.polyfill.js" type="application/javascript"></script>
            <script>keyboardeventKeyPolyfill.polyfill();</script>

            {!! Theme::js('vendor/jquery/jquery.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/sweetalert/sweetalert.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap/bootstrap.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/slimscroll/jquery.slimscroll.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/adminlte/app.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/bootstrap-notify/bootstrap-notify.min.js?t={cache-version}') !!}
            {!! Theme::js('vendor/select2/select2.full.min.js?t={cache-version}') !!}
            {!! Theme::js('js/admin/functions.js?t={cache-version}') !!}
            <script src="/js/autocomplete.js" type="application/javascript"></script>

            @if(Auth::user()->root_admin)
                <script>
                    $('#logoutButton').on('click', function (event) {
                        event.preventDefault();

                        var that = this;
                        swal({
                            title: 'Do you want to log out?',
                            type: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#d9534f',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Log out'
                        }, function () {
                             $.ajax({
                                type: 'POST',
                                url: '{{ route('auth.logout') }}',
                                data: {
                                    _token: '{{ csrf_token() }}'
                                },complete: function () {
                                    window.location.href = '{{route('auth.login')}}';
                                }
                        });
                    });
                });
                </script>
            @endif

            <script>
                $(function () {
                    $('[data-toggle="tooltip"]').tooltip();
                })
            </script>
        @show
    </body>
</html>
