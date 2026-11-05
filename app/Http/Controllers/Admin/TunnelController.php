<?php

namespace Pterodactyl\Http\Controllers\Admin;

use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;
use Prologue\Alerts\AlertsMessageBag;
use Pterodactyl\Http\Controllers\Controller;

class TunnelController extends Controller
{
    public function __construct(
        protected AlertsMessageBag $alert
    ) {
    }

    /**
     * Display the Tunnel configuration and status page.
     */
    public function index(): View
    {
        $osInfo = $this->getOperatingSystemInfo();
        $binaryInfo = $this->getBinaryInfo();
        $tunnelStatus = $this->getTunnelProcessStatus();
        $savedToken = $this->getSavedToken();

        return view('admin.tunnel.index', [
            'osInfo' => $osInfo,
            'binaryInfo' => $binaryInfo,
            'tunnelStatus' => $tunnelStatus,
            'savedToken' => $savedToken,
        ]);
    }

    /**
     * Save the tunnel token into .env.
     */
    public function saveToken(Request $request): RedirectResponse
    {
        $token = trim((string) $request->input('token'));

        if (empty($token)) {
            $this->alert->danger('Tunnel token cannot be empty.')->flash();
            return redirect()->route('admin.tunnel');
        }

        $this->updateEnvToken($token);
        $this->alert->success('Cloudflare Zero Trust Tunnel token saved successfully!')->flash();

        return redirect()->route('admin.tunnel');
    }

    /**
     * Auto-install and start the tunnel according to the host OS.
     */
    public function install(Request $request): RedirectResponse
    {
        $token = trim((string) $request->input('token')) ?: $this->getSavedToken();

        if (empty($token)) {
            $this->alert->danger('Please provide a valid Cloudflare Tunnel Token to install the service.')->flash();
            return redirect()->route('admin.tunnel');
        }

        $this->updateEnvToken($token);

        $osFamily = PHP_OS_FAMILY;
        $bin = $this->getBinaryPath();

        if ($osFamily === 'Windows') {
            if ($bin && file_exists($bin)) {
                // Try service install
                $escapedBin = escapeshellarg($bin);
                $escapedToken = escapeshellarg($token);

                // Run service install and background process
                @exec("{$escapedBin} service install {$escapedToken} 2>&1", $out, $ret);

                // Also launch tunnel supervisor process
                $this->launchWindowsTunnel($bin, $token);

                $this->alert->success('Cloudflare Tunnel service installed & started successfully on Windows!')->flash();
            } else {
                $this->alert->warning('cloudflared.exe binary was not found in the root directory. Please verify file placement.')->flash();
            }
        } elseif ($osFamily === 'Linux') {
            @exec("sudo cloudflared service install " . escapeshellarg($token) . " 2>&1", $out, $ret);
            $this->alert->success('Cloudflare Tunnel service install command executed for Linux!')->flash();
        } else {
            $this->alert->info('Automatic installation command ready for macOS/Unix.')->flash();
        }

        return redirect()->route('admin.tunnel');
    }

    /**
     * Start the tunnel process.
     */
    public function start(): RedirectResponse
    {
        $token = $this->getSavedToken();
        if (empty($token)) {
            $this->alert->danger('No tunnel token found. Please save a token first.')->flash();
            return redirect()->route('admin.tunnel');
        }

        $bin = $this->getBinaryPath();
        if (!$bin || !file_exists($bin)) {
            $this->alert->danger('Cloudflared binary not found on this system.')->flash();
            return redirect()->route('admin.tunnel');
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->launchWindowsTunnel($bin, $token);
        } else {
            $cmd = "nohup " . escapeshellarg($bin) . " tunnel --protocol http2 --no-autoupdate run --token " . escapeshellarg($token) . " > /dev/null 2>&1 &";
            @exec($cmd);
        }

        $this->alert->success('Cloudflare Tunnel process started with HTTP/2 persistent protocol!')->flash();
        return redirect()->route('admin.tunnel');
    }

    /**
     * Stop the running tunnel process.
     */
    public function stop(): RedirectResponse
    {
        if (PHP_OS_FAMILY === 'Windows') {
            @exec('taskkill /F /IM cloudflared.exe 2>&1');
        } else {
            @exec('pkill -9 cloudflared 2>&1');
        }

        $this->alert->info('Cloudflare Tunnel process stopped successfully.')->flash();
        return redirect()->route('admin.tunnel');
    }

    /**
     * Helper to launch tunnel in background on Windows.
     */
    protected function launchWindowsTunnel(string $bin, string $token): void
    {
        // Stop any running instances first
        @exec('taskkill /F /IM cloudflared.exe 2>&1');
        sleep(1);

        $psCommand = "Start-Process -FilePath '{$bin}' -ArgumentList 'tunnel --protocol http2 --no-autoupdate run --token {$token}' -WindowStyle Hidden";
        $cmd = 'powershell.exe -NoProfile -ExecutionPolicy Bypass -Command "' . $psCommand . '"';
        @pclose(@popen("start /B {$cmd}", "r"));
    }

    /**
     * Get detected OS details.
     */
    protected function getOperatingSystemInfo(): array
    {
        $family = PHP_OS_FAMILY;
        $name = php_uname('s') . ' ' . php_uname('r');
        $arch = php_uname('m') ?: (PHP_INT_SIZE === 8 ? 'x64' : 'x86');
        $hostname = gethostname() ?: 'localhost';

        // Public IP detection
        $publicIp = '127.0.0.1';
        try {
            $ctx = stream_context_create(['http' => ['timeout' => 2]]);
            $ipRes = @file_get_contents('https://api.ipify.org', false, $ctx);
            if ($ipRes && filter_var(trim($ipRes), FILTER_VALIDATE_IP)) {
                $publicIp = trim($ipRes);
            }
        } catch (\Throwable) {
            $publicIp = '47.31.105.0';
        }

        return [
            'family' => $family,
            'name' => $name,
            'arch' => $arch,
            'hostname' => $hostname,
            'publicIp' => $publicIp,
        ];
    }

    /**
     * Get binary status and version.
     */
    protected function getBinaryInfo(): array
    {
        $path = $this->getBinaryPath();
        $exists = !empty($path) && file_exists($path);
        $version = 'Not Installed';

        if ($exists) {
            $output = [];
            @exec(escapeshellarg($path) . ' --version 2>&1', $output);
            if (!empty($output[0])) {
                $version = trim($output[0]);
            }
        }

        return [
            'path' => $path,
            'exists' => $exists,
            'version' => $version,
        ];
    }

    /**
     * Get the absolute path to cloudflared binary.
     */
    protected function getBinaryPath(): ?string
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $local = base_path('cloudflared.exe');
            if (file_exists($local)) return $local;

            $output = [];
            @exec('where cloudflared.exe 2>&1', $output);
            if (!empty($output[0]) && file_exists(trim($output[0]))) {
                return trim($output[0]);
            }
            return $local;
        }

        $local = base_path('cloudflared');
        if (file_exists($local)) return $local;

        $output = [];
        @exec('which cloudflared 2>&1', $output);
        if (!empty($output[0]) && file_exists(trim($output[0]))) {
            return trim($output[0]);
        }

        return '/usr/local/bin/cloudflared';
    }

    /**
     * Check if tunnel process is actively running.
     */
    protected function getTunnelProcessStatus(): array
    {
        $running = false;
        $pid = null;

        if (PHP_OS_FAMILY === 'Windows') {
            $output = [];
            @exec('tasklist /FI "IMAGENAME eq cloudflared.exe" /FO CSV /NH 2>&1', $output);
            foreach ($output as $line) {
                if (stripos($line, 'cloudflared.exe') !== false) {
                    $parts = str_getcsv($line);
                    if (!empty($parts[1])) {
                        $running = true;
                        $pid = (int) $parts[1];
                        break;
                    }
                }
            }
        } else {
            $output = [];
            @exec('pgrep -x cloudflared 2>&1', $output);
            if (!empty($output[0]) && is_numeric(trim($output[0]))) {
                $running = true;
                $pid = (int) trim($output[0]);
            }
        }

        return [
            'running' => $running,
            'pid' => $pid,
            'protocol' => 'HTTP/2 (Persistent)',
        ];
    }

    /**
     * Get saved token from .env or config.
     */
    protected function getSavedToken(): string
    {
        $token = env('CLOUDFLARE_TUNNEL_TOKEN', '');
        if (!empty($token)) return $token;

        $envPath = base_path('.env');
        if (file_exists($envPath)) {
            $content = file_get_contents($envPath);
            if (preg_match('/^CLOUDFLARE_TUNNEL_TOKEN=([^\r\n]+)/m', $content, $m)) {
                return trim($m[1]);
            }
        }

        return '';
    }

    /**
     * Update .env file with token.
     */
    protected function updateEnvToken(string $token): void
    {
        $envPath = base_path('.env');
        if (!file_exists($envPath)) return;

        $content = file_get_contents($envPath);
        if (preg_match('/^CLOUDFLARE_TUNNEL_TOKEN=.*$/m', $content)) {
            $content = preg_replace('/^CLOUDFLARE_TUNNEL_TOKEN=.*$/m', 'CLOUDFLARE_TUNNEL_TOKEN=' . $token, $content);
        } else {
            $content .= "\nCLOUDFLARE_TUNNEL_TOKEN=" . $token . "\n";
        }

        file_put_contents($envPath, $content);
    }
}

