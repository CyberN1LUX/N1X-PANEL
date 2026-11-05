@extends('layouts.admin')

@section('title')
    Cloudflare Tunnel
@endsection

@section('content-header')
    <h1>Cloudflare Tunnel<small>Fast SSL tunnel &amp; port forwarding.</small></h1>
    <ol class="breadcrumb">
        <li><a href="{{ route('admin.index') }}">Admin</a></li>
        <li class="active">Tunnel</li>
    </ol>
@endsection

@section('content')
<style>
    .tunnel-stat-card {
        background: rgba(26, 26, 26, 0.95);
        border: 1px solid #333333;
        border-radius: 8px;
        padding: 16px 20px;
        margin-bottom: 20px;
        display: flex;
        align-items: center;
        gap: 16px;
    }
    .tunnel-icon-box {
        width: 50px;
        height: 50px;
        border-radius: 10px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 24px;
    }
    .status-pulse {
        width: 10px;
        height: 10px;
        border-radius: 50%;
        display: inline-block;
        margin-right: 6px;
    }
    .pulse-green {
        background-color: #10B981;
        box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7);
        animation: pulse-green 1.6s infinite;
    }
    @keyframes pulse-green {
        0% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0.7); }
        70% { transform: scale(1); box-shadow: 0 0 0 8px rgba(16, 185, 129, 0); }
        100% { transform: scale(0.95); box-shadow: 0 0 0 0 rgba(16, 185, 129, 0); }
    }
    .pulse-red {
        background-color: #EF4444;
    }
    .os-badge {
        font-size: 11px;
        font-weight: 700;
        letter-spacing: 0.05em;
        text-transform: uppercase;
        padding: 4px 8px;
        border-radius: 4px;
        background: #3b82f6;
        color: #ffffff;
    }
    .code-preview {
        background: #151515;
        border: 1px solid #2d2d2d;
        color: #38bdf8;
        padding: 12px 16px;
        border-radius: 6px;
        font-family: Consolas, "Courier New", monospace;
        font-size: 13px;
        white-space: pre-wrap;
        word-break: break-all;
        margin: 8px 0;
    }
</style>

<div class="row">
    <!-- OS Detection & Live Status -->
    <div class="col-md-4 col-sm-6 col-xs-12">
        <div class="tunnel-stat-card">
            <div class="tunnel-icon-box" style="background: rgba(59, 130, 246, 0.15); color: #3b82f6;">
                @if($osInfo['family'] === 'Windows')
                    <i class="fa fa-windows"></i>
                @elseif($osInfo['family'] === 'Linux')
                    <i class="fa fa-linux"></i>
                @else
                    <i class="fa fa-apple"></i>
                @endif
            </div>
            <div>
                <div style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 600;">Detected Operating System</div>
                <div style="font-size: 17px; font-weight: 700; color: #FFFFFF; display: flex; align-items: center; gap: 8px; margin-top: 2px;">
                    {{ $osInfo['family'] }} ({{ $osInfo['arch'] }})
                    <span class="os-badge">Auto-Detected</span>
                </div>
                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">{{ $osInfo['name'] }}</div>
            </div>
        </div>
    </div>

    <!-- Binary Status -->
    <div class="col-md-4 col-sm-6 col-xs-12">
        <div class="tunnel-stat-card">
            <div class="tunnel-icon-box" style="background: rgba(16, 185, 129, 0.15); color: #10B981;">
                <i class="fa fa-cube"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 600;">Cloudflared Binary</div>
                <div style="font-size: 17px; font-weight: 700; color: #FFFFFF; margin-top: 2px;">
                    @if($binaryInfo['exists'])
                        <span style="color: #10B981;"><i class="fa fa-check-circle"></i> Ready & Installed</span>
                    @else
                        <span style="color: #EF4444;"><i class="fa fa-times-circle"></i> Not Found</span>
                    @endif
                </div>
                <div style="font-size: 12px; color: #6B7280; margin-top: 2px; text-overflow: ellipsis; overflow: hidden; white-space: nowrap; max-width: 240px;">
                    {{ $binaryInfo['version'] }}
                </div>
            </div>
        </div>
    </div>

    <!-- Tunnel Daemon Process Status -->
    <div class="col-md-4 col-sm-12 col-xs-12">
        <div class="tunnel-stat-card">
            <div class="tunnel-icon-box" style="background: {{ $tunnelStatus['running'] ? 'rgba(16, 185, 129, 0.15)' : 'rgba(239, 68, 68, 0.15)' }}; color: {{ $tunnelStatus['running'] ? '#10B981' : '#EF4444' }};">
                <i class="fa fa-cloud"></i>
            </div>
            <div>
                <div style="font-size: 12px; color: #9CA3AF; text-transform: uppercase; font-weight: 600;">Tunnel Service Status</div>
                <div style="font-size: 17px; font-weight: 700; color: #FFFFFF; margin-top: 2px;">
                    @if($tunnelStatus['running'])
                        <span class="status-pulse pulse-green"></span> <span style="color: #10B981;">Active & Connected</span>
                    @else
                        <span class="status-pulse pulse-red"></span> <span style="color: #EF4444;">Offline / Stopped</span>
                    @endif
                </div>
                <div style="font-size: 12px; color: #6B7280; margin-top: 2px;">
                    @if($tunnelStatus['running'])
                        PID: <strong>{{ $tunnelStatus['pid'] }}</strong> | Protocol: {{ $tunnelStatus['protocol'] }}
                    @else
                        Enter token below to install & start service
                    @endif
                </div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- Tunnel Token & Auto Install Form -->
    <div class="col-md-7 col-xs-12">
        <div class="box box-primary">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-key text-primary"></i> Cloudflare Zero Trust Tunnel Token</h3>
            </div>
            <form action="{{ route('admin.tunnel.install') }}" method="POST">
                {{ csrf_field() }}
                <div class="box-body">
                    <p style="color: #9CA3AF; font-size: 13px;">
                        Enter your Cloudflare Zero Trust tunnel token below to auto-install and run on <strong>{{ $osInfo['family'] }}</strong>.
                    </p>

                    <div class="form-group">
                        <label for="tunnelToken">Cloudflare Tunnel Token</label>
                        <textarea name="token" id="tunnelToken" class="form-control" rows="3" placeholder="eyJhIjoi... (Paste Token Here)" required style="font-family: monospace; font-size: 12.5px;">{{ old('token', $savedToken) }}</textarea>
                        <p class="help-block" style="color: #6B7280;">Token saved in environment configuration and monitored by N1LUX PETRO runner.</p>
                    </div>

                    @if(!empty($savedToken))
                        <div class="alert alert-info" style="background: rgba(59, 130, 246, 0.12) !important; border: 1px solid rgba(59, 130, 246, 0.3) !important; color: #93c5fd !important; margin-bottom: 0;">
                            <i class="fa fa-info-circle"></i> Token is currently saved and active. (Length: {{ strlen($savedToken) }} characters)
                        </div>
                    @endif
                </div>
                <div class="box-footer" style="display: flex; gap: 10px; align-items: center; justify-content: space-between;">
                    <div>
                        <button type="submit" class="btn btn-primary btn-sm" style="font-weight: 600;">
                            <i class="fa fa-bolt"></i> Auto-Install &amp; Start ({{ $osInfo['family'] }})
                        </button>
                        <button type="submit" formaction="{{ route('admin.tunnel.save') }}" class="btn btn-default btn-sm">
                            <i class="fa fa-save"></i> Save Token Only
                        </button>
                    </div>
                    <div>
                        @if($tunnelStatus['running'])
                            <button type="submit" formaction="{{ route('admin.tunnel.stop') }}" class="btn btn-danger btn-sm">
                                <i class="fa fa-stop"></i> Stop Tunnel
                            </button>
                            <button type="submit" formaction="{{ route('admin.tunnel.start') }}" class="btn btn-warning btn-sm">
                                <i class="fa fa-refresh"></i> Restart
                            </button>
                        @else
                            <button type="submit" formaction="{{ route('admin.tunnel.start') }}" class="btn btn-success btn-sm">
                                <i class="fa fa-play"></i> Start Tunnel
                            </button>
                        @endif
                    </div>
                </div>
            </form>
        </div>

        <!-- Mapped Port Guide -->
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-exchange text-info"></i> Active Application Ports to Map</h3>
            </div>
            <div class="box-body no-padding">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Internal Service</th>
                            <th>Target URL</th>
                            <th>Example Public Domain</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td><strong>N1X Web Panel</strong></td>
                            <td><code>http://127.0.0.1:5000</code></td>
                            <td><code>panel.n1lux.bond</code></td>
                            <td><span class="label label-success">Online</span></td>
                        </tr>
                        <tr>
                            <td><strong>MAIN AI SERVER (Python)</strong></td>
                            <td><code>http://127.0.0.1:5002</code></td>
                            <td><code>python.n1lux.bond</code></td>
                            <td><span class="label label-success">Online</span></td>
                        </tr>
                        <tr>
                            <td><strong>N1X Process Runner Daemon</strong></td>
                            <td><code>http://127.0.0.1:8080</code></td>
                            <td>Internal Host API</td>
                            <td><span class="label label-primary">Online</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- OS Installation Commands & Terminal Instructions -->
    <div class="col-md-5 col-xs-12">
        <div class="box">
            <div class="box-header with-border">
                <h3 class="box-title"><i class="fa fa-terminal text-success"></i> OS Installation Reference</h3>
            </div>
            <div class="box-body">
                <p style="color: #9CA3AF; font-size: 13px;">
                    Quick host terminal commands for manual execution:
                </p>

                <!-- Windows -->
                <div style="margin-bottom: 16px; border-bottom: 1px solid #333333; padding-bottom: 14px;">
                    <div style="font-weight: 700; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa fa-windows text-primary"></i> Windows (PowerShell / CMD)</span>
                        @if($osInfo['family'] === 'Windows')
                            <span class="badge" style="background: #3b82f6;">Current Host</span>
                        @endif
                    </div>
                    <div class="code-preview">.\cloudflared.exe service install {{ !empty($savedToken) ? substr($savedToken, 0, 24) . '...' : '<YOUR_TOKEN>' }}</div>
                    <div style="font-size: 11.5px; color: #9CA3AF;">Or run directly without service:</div>
                    <div class="code-preview">.\cloudflared.exe tunnel --protocol http2 run --token {{ !empty($savedToken) ? substr($savedToken, 0, 24) . '...' : '<YOUR_TOKEN>' }}</div>
                </div>

                <!-- Linux Debian / Ubuntu -->
                <div style="margin-bottom: 16px; border-bottom: 1px solid #333333; padding-bottom: 14px;">
                    <div style="font-weight: 700; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa fa-linux text-warning"></i> Linux (Debian / Ubuntu / x64)</span>
                        @if($osInfo['family'] === 'Linux')
                            <span class="badge" style="background: #3b82f6;">Current Host</span>
                        @endif
                    </div>
                    <div class="code-preview">curl -L --output cloudflared.deb https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-linux-amd64.deb
sudo dpkg -i cloudflared.deb
sudo cloudflared service install {{ !empty($savedToken) ? substr($savedToken, 0, 24) . '...' : '<YOUR_TOKEN>' }}</div>
                </div>

                <!-- macOS -->
                <div>
                    <div style="font-weight: 700; color: #FFFFFF; display: flex; align-items: center; justify-content: space-between;">
                        <span><i class="fa fa-apple text-default"></i> macOS (Homebrew)</span>
                        @if($osInfo['family'] === 'Darwin')
                            <span class="badge" style="background: #3b82f6;">Current Host</span>
                        @endif
                    </div>
                    <div class="code-preview">brew install cloudflared
sudo cloudflared service install {{ !empty($savedToken) ? substr($savedToken, 0, 24) . '...' : '<YOUR_TOKEN>' }}</div>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

