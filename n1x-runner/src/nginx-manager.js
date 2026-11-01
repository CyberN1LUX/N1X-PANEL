const fs = require('fs');
const fsp = require('fs/promises');
const path = require('path');
const { exec } = require('child_process');
const util = require('util');
const execPromise = util.promisify(exec);

class NginxManager {
    constructor(config) {
        this.enabled = config.nginx_enabled !== false;
        this.confDir = config.nginx_conf_dir || '/etc/nginx/conf.d';
    }

    async generateConfig(uuid, domain, port, options = {}) {
        if (!this.enabled || !domain) return null;

        const confFileName = `n1x-${uuid}.conf`;
        const confFilePath = path.join(this.confDir, confFileName);

        const sslConfig = options.ssl ? `
    listen 443 ssl http2;
    ssl_certificate ${options.sslCert || `/etc/letsencrypt/live/${domain}/fullchain.pem`};
    ssl_certificate_key ${options.sslKey || `/etc/letsencrypt/live/${domain}/privkey.pem`};
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
` : `
    listen 80;
`;

        const template = `# N1X PANEL Reverse Proxy Configuration for ${uuid}
server {
    server_name ${domain};
${sslConfig}

    access_log /var/log/nginx/n1x-${uuid}.access.log;
    error_log /var/log/nginx/n1x-${uuid}.error.log;

    client_max_body_size 100M;

    location / {
        proxy_pass http://127.0.0.1:${port};
        proxy_http_version 1.1;

        proxy_set_header Upgrade $http_upgrade;
        proxy_set_header Connection "upgrade";
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;

        proxy_connect_timeout 60s;
        proxy_send_timeout 60s;
        proxy_read_timeout 60s;
    }
}
`;

        try {
            await fsp.mkdir(this.confDir, { recursive: true });
            await fsp.writeFile(confFilePath, template, 'utf8');
            await this.reloadNginx();
            return confFilePath;
        } catch (e) {
            console.error(`Failed to write Nginx config for ${uuid}:`, e.message);
            return null;
        }
    }

    async removeConfig(uuid) {
        if (!this.enabled) return;
        const confFilePath = path.join(this.confDir, `n1x-${uuid}.conf`);
        if (fs.existsSync(confFilePath)) {
            await fsp.unlink(confFilePath);
            await this.reloadNginx();
        }
    }

    async reloadNginx() {
        try {
            if (process.platform !== 'win32') {
                await execPromise('nginx -t && systemctl reload nginx');
            }
        } catch (err) {
            console.warn('Nginx reload skipped or failed:', err.message);
        }
    }
}

module.exports = NginxManager;

