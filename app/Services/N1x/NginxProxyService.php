<?php

namespace Pterodactyl\Services\N1x;

use Pterodactyl\Models\Server;
use Illuminate\Support\Facades\File;

class NginxProxyService
{
    protected bool $enabled;
    protected string $configPath;

    public function __construct()
    {
        $this->enabled = (bool) config('n1x.nginx.enabled', true);
        $this->configPath = config('n1x.nginx.config_path', '/etc/nginx/conf.d');
    }

    /**
     * Generate an Nginx virtual host configuration string for an application.
     */
    public function generateVirtualHost(Server $server, string $domain, ?string $sslCert = null, ?string $sslKey = null): string
    {
        $port = $server->allocation ? $server->allocation->port : 5000;
        $uuid = $server->uuid;
        $hasSsl = !empty($sslCert) && !empty($sslKey);

        $sslBlock = $hasSsl ? "
    listen 443 ssl http2;
    ssl_certificate {$sslCert};
    ssl_certificate_key {$sslKey};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
" : "
    listen 80;
";

        return <<<NGINX
# N1X PANEL Reverse Proxy Configuration for {$server->name} ({$uuid})
server {
    server_name {$domain};
{$sslBlock}

    access_log /var/log/nginx/n1x-{$uuid}.access.log;
    error_log /var/log/nginx/n1x-{$uuid}.error.log;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:{$port};
        proxy_http_version 1.1;

        proxy_set_header Upgrade \$http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host \$host;
        proxy_set_header X-Real-IP \$remote_addr;
        proxy_set_header X-Forwarded-For \$proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto \$scheme;

        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
NGINX;
    }

    /**
     * Write Nginx config file to disk if running locally or sync with daemon.
     */
    public function deployVirtualHost(Server $server, string $domain, ?string $sslCert = null, ?string $sslKey = null): bool
    {
        if (!$this->enabled || empty($domain)) {
            return false;
        }

        $config = $this->generateVirtualHost($server, $domain, $sslCert, $sslKey);
        $fileName = "n1x-{$server->uuid}.conf";

        if (File::isDirectory($this->configPath)) {
            $dest = rtrim($this->configPath, '/\\') . DIRECTORY_SEPARATOR . $fileName;
            File::put($dest, $config);
            return true;
        }

        return false;
    }

    /**
     * Remove the Nginx configuration file for a deleted application.
     */
    public function removeVirtualHost(Server $server): bool
    {
        $fileName = "n1x-{$server->uuid}.conf";
        $dest = rtrim($this->configPath, '/\\') . DIRECTORY_SEPARATOR . $fileName;

        if (File::exists($dest)) {
            return File::delete($dest);
        }

        return false;
    }
}

