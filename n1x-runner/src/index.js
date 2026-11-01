const http = require('http');
const { parse: parseUrl } = require('url');
const path = require('path');
const fs = require('fs');
const os = require('os');
const { WebSocketServer } = require('ws');

const FileManager = require('./file-manager');
const ProcessManager = require('./process-manager');
const NginxManager = require('./nginx-manager');

// Load configuration
let config = {
    host: '0.0.0.0',
    port: 8080,
    token: 'n1x-secure-runner-token',
    applications_root: path.resolve(__dirname, '../../storage/applications'),
    nginx_enabled: false,
};

const configPath = path.resolve(__dirname, '../config.json');
if (fs.existsSync(configPath)) {
    try {
        config = { ...config, ...JSON.parse(fs.readFileSync(configPath, 'utf8')) };
    } catch (e) {
        console.warn('Could not read config.json, using defaults.');
    }
}

// In Windows development, use storage/applications as default root if Linux /var path does not exist
if (process.platform === 'win32' && config.applications_root.startsWith('/var/lib')) {
    config.applications_root = path.resolve(__dirname, '../../storage/applications');
}

const fileManager = new FileManager(config.applications_root);
const processManager = new ProcessManager(config);
const nginxManager = new NginxManager(config);

// Active WebSocket connections: uuid -> Set<WebSocket>
const wsConnections = new Map();

function sendJson(res, statusCode, data) {
    res.writeHead(statusCode, {
        'Content-Type': 'application/json',
        'Access-Control-Allow-Origin': '*',
        'Access-Control-Allow-Headers': '*',
        'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
    });
    res.end(JSON.stringify(data));
}

function parseJsonBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => {
            if (!body) return resolve({});
            try {
                resolve(JSON.parse(body));
            } catch (e) {
                resolve({});
            }
        });
        req.on('error', reject);
    });
}

function parseRawBody(req) {
    return new Promise((resolve, reject) => {
        let body = '';
        req.on('data', chunk => body += chunk);
        req.on('end', () => resolve(body));
        req.on('error', reject);
    });
}

function getBearerToken() {
    return config.token_id ? `${config.token_id}.${config.token}` : config.token;
}

function notifyPanelInstallComplete(uuid) {
    return new Promise((resolve) => {
        const panelUrl = config.panel_url || 'http://127.0.0.1:5000';
        try {
            const parsedUrl = new URL(panelUrl);
            const postData = JSON.stringify({ successful: true });
            const req = http.request({
                hostname: parsedUrl.hostname,
                port: parsedUrl.port || 80,
                path: `/api/remote/servers/${uuid}/install`,
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Content-Length': Buffer.byteLength(postData),
                    'Authorization': `Bearer ${getBearerToken()}`,
                    'Accept': 'application/json',
                },
            }, (res) => {
                resolve(res.statusCode >= 200 && res.statusCode < 300);
            });
            req.on('error', () => resolve(false));
            req.write(postData);
            req.end();
        } catch (e) {
            resolve(false);
        }
    });
}

function fetchServerDetailsFromPanel(uuid) {
    return new Promise((resolve) => {
        const panelUrl = config.panel_url || 'http://127.0.0.1:5000';
        try {
            const parsedUrl = new URL(panelUrl);
            const req = http.request({
                hostname: parsedUrl.hostname,
                port: parsedUrl.port || 80,
                path: `/api/remote/servers/${uuid}`,
                method: 'GET',
                headers: {
                    'Authorization': `Bearer ${getBearerToken()}`,
                    'Accept': 'application/json',
                },
            }, (res) => {
                let data = '';
                res.on('data', chunk => data += chunk);
                res.on('end', () => {
                    try {
                        const parsed = JSON.parse(data);
                        resolve(parsed && parsed.settings ? parsed.settings : null);
                    } catch (e) {
                        resolve(null);
                    }
                });
            });
            req.on('error', () => resolve(null));
            req.end();
        } catch (e) {
            resolve(null);
        }
    });
}

// HTTP Server
const server = http.createServer(async (req, res) => {
    const url = parseUrl(req.url, true);
    const pathname = url.pathname;
    const method = req.method.toUpperCase();

    // CORS preflight
    if (method === 'OPTIONS') {
        res.writeHead(204, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Headers': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PUT, DELETE, OPTIONS',
        });
        return res.end();
    }

    try {
        // Welcome / Health check route for browser or monitoring
        if (pathname === '/' || pathname === '/health') {
            const isHtml = (req.headers.accept || '').includes('text/html');
            if (isHtml) {
                res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
                return res.end(`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>N1X PANEL - Native Application Runner</title>
<style>
body { background: #0B0F0E; color: #F0FDF4; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
.card { background: #151E1A; border: 1px solid #26352E; border-top: 4px solid #22C55E; padding: 36px 44px; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.6); max-width: 500px; text-align: center; }
h1 { color: #22C55E; margin: 0 0 8px 0; font-size: 28px; letter-spacing: 0.05em; }
p { color: #94A3A0; font-size: 14px; margin: 8px 0; }
.badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.15); color: #22C55E; border: 1px solid #22C55E; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin: 14px 0; }
.dot { width: 8px; height: 8px; background: #22C55E; border-radius: 50%; box-shadow: 0 0 6px #22C55E; }
.info { background: #101715; border: 1px solid #26352E; border-radius: 8px; padding: 14px; margin-top: 18px; text-align: left; font-size: 13px; font-family: Consolas, monospace; color: #BBF7D0; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
<h1>N1X PANEL</h1>
<div class="badge"><span class="dot"></span> Native Runner Online (Docker-Free)</div>
<p>The native application daemon is actively running and connected.</p>
<div class="info">
&bull; Service: N1X Native Application Runner<br>
&bull; Status: Online (Docker-Free)<br>
&bull; Port: ${port}<br>
&bull; Runtimes: Node.js, Python, PHP, Static
</div>
</div>
</body>
</html>`);
            }
            return sendJson(res, 200, {
                name: 'N1X PANEL Native Application Runner',
                status: 'online',
                version: '1.0.0',
                docker_free: true,
                runtimes: ['node', 'python', 'php', 'static'],
            });
        }

        // Pterodactyl Wings / Node Compatibility: GET /api/system
        if (pathname === '/api/system' && method === 'GET') {
            return sendJson(res, 200, {
                architecture: os.arch(),
                cpu_count: os.cpus().length,
                kernel_version: os.release(),
                os: os.platform(),
                version: '1.0.0',
            });
        }

        // Pterodactyl Wings / Node Compatibility: POST /api/update
        if (pathname === '/api/update' && method === 'POST') {
            const body = await parseJsonBody(req);
            if (body && body.token) {
                config.token = body.token;
                config.uuid = body.uuid || config.uuid;
                try {
                    fs.writeFileSync(configPath, JSON.stringify(config, null, 2));
                } catch (e) {
                    console.warn('Could not persist updated config to config.json', e.message);
                }
            }
            return sendJson(res, 200, { success: true });
        }

        // 1. POST /api/servers (Create/Register Server)
        if (pathname === '/api/servers' && method === 'POST') {
            const body = await parseJsonBody(req);
            const uuid = body.uuid;
            if (!uuid) return sendJson(res, 400, { error: 'Server uuid required' });

            await fileManager.ensureAppDirectory(uuid);

            // Fetch comprehensive server details from panel
            const panelSettings = await fetchServerDetailsFromPanel(uuid);

            let port = body.port || panelSettings?.allocations?.default?.port || 5002;
            if (!port || port <= 5000) {
                port = 5002;
            }

            let name = body.name || panelSettings?.meta?.name || uuid;

            // Detect runtime accurately
            let runtime = (body.runtime || panelSettings?.meta?.runtime || '').toLowerCase();
            const image = (panelSettings?.container?.image || '').toLowerCase();
            const startup = (panelSettings?.invocation || '').toLowerCase();
            const startupFile = (panelSettings?.environment?.STARTUP_FILE || '').toLowerCase();

            if (runtime.includes('python') || image.includes('python') || startup.includes('python') || startupFile.endsWith('.py')) {
                runtime = 'python';
            } else if (runtime.includes('php') || image.includes('php') || startup.includes('php') || startupFile.endsWith('.php')) {
                runtime = 'php';
            } else if (runtime.includes('static') || image.includes('static') || startup.includes('serve') || startupFile.endsWith('.html')) {
                runtime = 'static';
            } else {
                runtime = 'node';
            }

            const chosenStartupFile = panelSettings?.environment?.STARTUP_FILE || 
                (runtime === 'python' ? 'app.py' : (runtime === 'php' ? 'index.php' : (runtime === 'static' ? 'index.html' : 'index.js')));

            const app = processManager.registerApp(uuid, {
                name,
                runtime,
                port,
                startupFile: chosenStartupFile,
                env: Object.assign({}, panelSettings?.environment || {}, body.env || {}),
            });

            // Ensure starter template files so process never crashes
            processManager.ensureStarterFiles(app);

            // Notify panel that installation is completed
            await notifyPanelInstallComplete(uuid);

            if (body.start_on_completion) {
                processManager.start(uuid).catch(e => console.error(e));
            }

            return sendJson(res, 201, { success: true, uuid });
        }

        // Match /api/servers/:uuid
        const serverMatch = pathname.match(/^\/api\/servers\/([a-zA-Z0-9_-]+)(.*)$/);
        if (serverMatch) {
            const uuid = serverMatch[1];
            const subPath = serverMatch[2];

            // GET /api/servers/:uuid
            if (!subPath && method === 'GET') {
                const app = processManager.getApp(uuid);
                return sendJson(res, 200, {
                    uuid,
                    status: app.status,
                    stats: processManager.getStats(uuid),
                });
            }

            // DELETE /api/servers/:uuid
            if (!subPath && method === 'DELETE') {
                await processManager.kill(uuid);
                await nginxManager.removeConfig(uuid);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/sync
            if (subPath === '/sync' && method === 'POST') {
                const body = await parseJsonBody(req);
                processManager.registerApp(uuid, body);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/power
            if (subPath === '/power' && method === 'POST') {
                const body = await parseJsonBody(req);
                const action = (body.action || '').toLowerCase();

                const existingApp = processManager.getApp(uuid);
                if (!existingApp.port || existingApp.port <= 5000 || !existingApp.startupFile) {
                    const panelSettings = await fetchServerDetailsFromPanel(uuid);
                    if (panelSettings) {
                        let p = panelSettings.allocations?.default?.port || 5002;
                        if (!p || p <= 5000) p = 5002;
                        existingApp.port = p;
                        if (panelSettings.meta?.name) existingApp.name = panelSettings.meta.name;

                        let r = (panelSettings.meta?.runtime || '').toLowerCase();
                        const img = (panelSettings.container?.image || '').toLowerCase();
                        const startCmd = (panelSettings.invocation || '').toLowerCase();
                        const sf = (panelSettings.environment?.STARTUP_FILE || '').toLowerCase();

                        if (r.includes('python') || img.includes('python') || startCmd.includes('python') || sf.endsWith('.py')) {
                            existingApp.runtime = 'python';
                        } else if (r.includes('php') || img.includes('php') || startCmd.includes('php') || sf.endsWith('.php')) {
                            existingApp.runtime = 'php';
                        } else if (r.includes('static') || img.includes('static') || startCmd.includes('serve') || sf.endsWith('.html')) {
                            existingApp.runtime = 'static';
                        } else {
                            existingApp.runtime = 'node';
                        }

                        existingApp.startupFile = panelSettings.environment?.STARTUP_FILE ||
                            (existingApp.runtime === 'python' ? 'app.py' : (existingApp.runtime === 'php' ? 'index.php' : (existingApp.runtime === 'static' ? 'index.html' : 'index.js')));

                        if (panelSettings.environment) {
                            existingApp.env = Object.assign({}, panelSettings.environment, existingApp.env || {});
                        }
                    }
                }

                if (action === 'start') await processManager.start(uuid);
                else if (action === 'stop') await processManager.stop(uuid);
                else if (action === 'restart') await processManager.restart(uuid);
                else if (action === 'kill') await processManager.kill(uuid);
                else return sendJson(res, 400, { error: 'Unknown power action' });

                return sendJson(res, 204, {});
            }

            // POST /api/servers/:uuid/commands
            if (subPath === '/commands' && method === 'POST') {
                const body = await parseJsonBody(req);
                processManager.sendCommand(uuid, body.command || '');
                return sendJson(res, 204, {});
            }

            // FILE MANAGER ENDPOINTS
            // GET /api/servers/:uuid/files/list-directory
            if (subPath === '/files/list-directory' && method === 'GET') {
                const dir = url.query.directory || '/';
                const files = await fileManager.listDirectory(uuid, dir);
                return sendJson(res, 200, files);
            }

            // GET /api/servers/:uuid/files/contents
            if (subPath === '/files/contents' && method === 'GET') {
                const file = url.query.file;
                if (!file) return sendJson(res, 400, { error: 'File parameter is required' });
                const content = await fileManager.readFile(uuid, file);
                res.writeHead(200, { 'Content-Type': 'text/plain; charset=utf-8' });
                return res.end(content);
            }

            // POST /api/servers/:uuid/files/write
            if (subPath === '/files/write' && method === 'POST') {
                const file = url.query.file;
                if (!file) return sendJson(res, 400, { error: 'File parameter is required' });
                const content = await parseRawBody(req);
                await fileManager.writeFile(uuid, file, content);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/files/create-directory
            if (subPath === '/files/create-directory' && method === 'POST') {
                const body = await parseJsonBody(req);
                await fileManager.createDirectory(uuid, body.path, body.name);
                return sendJson(res, 200, { success: true });
            }

            // PUT /api/servers/:uuid/files/rename
            if (subPath === '/files/rename' && method === 'PUT') {
                const body = await parseJsonBody(req);
                await fileManager.renameFiles(uuid, body.root, body.files);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/files/delete
            if (subPath === '/files/delete' && method === 'POST') {
                const body = await parseJsonBody(req);
                await fileManager.deleteFiles(uuid, body.root, body.files);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/files/chmod
            if (subPath === '/files/chmod' && method === 'POST') {
                const body = await parseJsonBody(req);
                await fileManager.chmodFiles(uuid, body.root, body.files);
                return sendJson(res, 200, { success: true });
            }

            // POST /api/servers/:uuid/files/compress
            if (subPath === '/files/compress' && method === 'POST') {
                const body = await parseJsonBody(req);
                const result = await fileManager.compressFiles(uuid, body.root, body.files);
                return sendJson(res, 200, result);
            }

            // POST /api/servers/:uuid/files/decompress
            if (subPath === '/files/decompress' && method === 'POST') {
                const body = await parseJsonBody(req);
                await fileManager.decompressFile(uuid, body.root, body.file);
                return sendJson(res, 200, { success: true });
            }
        }

        // Default 404
        return sendJson(res, 404, { error: 'Endpoint not found' });
    } catch (err) {
        console.error('API Error:', err);
        return sendJson(res, err.statusCode || 500, { error: err.message });
    }
});

// WebSocket Server for Console & Live Telemetry
const wss = new WebSocketServer({ noServer: true });

server.on('upgrade', (request, socket, head) => {
    const { pathname } = parseUrl(request.url);
    const wsMatch = pathname.match(/^\/api\/servers\/([a-zA-Z0-9_-]+)\/ws$/);

    if (wsMatch) {
        const uuid = wsMatch[1];
        wss.handleUpgrade(request, socket, head, (ws) => {
            wss.emit('connection', ws, request, uuid);
        });
    } else {
        socket.destroy();
    }
});

wss.on('connection', (ws, request, uuid) => {
    if (!wsConnections.has(uuid)) {
        wsConnections.set(uuid, new Set());
    }
    wsConnections.get(uuid).add(ws);

    let authenticated = false;
    const app = processManager.getApp(uuid);

    function sendEvent(event, args = []) {
        if (ws.readyState === ws.OPEN) {
            ws.send(JSON.stringify({ event, args }));
        }
    }

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);
            const { event, args } = data;

            if (event === 'auth') {
                // Token accepted
                authenticated = true;
                sendEvent('auth success');
                sendEvent('status', [app.status]);

                // Send recent buffered console logs
                for (const line of app.logs) {
                    sendEvent('console output', [line]);
                }

                // Send current stats
                sendEvent('stats', [JSON.stringify(processManager.getStats(uuid))]);
            }

            if (!authenticated) return;

            if (event === 'set state') {
                const action = (args && args[0]) || '';
                if (action === 'start') processManager.start(uuid);
                else if (action === 'stop') processManager.stop(uuid);
                else if (action === 'restart') processManager.restart(uuid);
                else if (action === 'kill') processManager.kill(uuid);
            }

            if (event === 'send command') {
                const cmd = (args && args[0]) || '';
                processManager.sendCommand(uuid, cmd);
            }

            if (event === 'send stats') {
                sendEvent('stats', [JSON.stringify(processManager.getStats(uuid))]);
            }

            if (event === 'send logs') {
                for (const line of app.logs) {
                    sendEvent('console output', [line]);
                }
            }
        } catch (e) {
            console.error('WS Error:', e.message);
        }
    });

    ws.on('close', () => {
        if (wsConnections.has(uuid)) {
            wsConnections.get(uuid).delete(ws);
        }
    });
});

// Broadcast live events from ProcessManager to WebSockets
processManager.on('console', (uuid, line) => {
    const clients = wsConnections.get(uuid);
    if (clients) {
        const payload = JSON.stringify({ event: 'console output', args: [line] });
        for (const ws of clients) {
            if (ws.readyState === ws.OPEN) ws.send(payload);
        }
    }
});

processManager.on('status', (uuid, status) => {
    const clients = wsConnections.get(uuid);
    if (clients) {
        const payload = JSON.stringify({ event: 'status', args: [status] });
        for (const ws of clients) {
            if (ws.readyState === ws.OPEN) ws.send(payload);
        }
    }
});

// Periodic Stats Broadcaster (every 2 seconds)
setInterval(() => {
    for (const [uuid, clients] of wsConnections.entries()) {
        if (clients.size > 0) {
            const stats = processManager.getStats(uuid);
            const payload = JSON.stringify({ event: 'stats', args: [JSON.stringify(stats)] });
            for (const ws of clients) {
                if (ws.readyState === ws.OPEN) ws.send(payload);
            }
        }
    }
}, 2000);

const port = process.env.RUNNER_PORT || config.port || 8080;
const host = process.env.RUNNER_HOST || config.host || '0.0.0.0';

server.listen(port, host, () => {
    console.log(`\x1b[32m===================================================\x1b[0m`);
    console.log(`\x1b[32m  N1X PANEL Native Application Runner (Docker-Free)\x1b[0m`);
    console.log(`\x1b[32m  Listening on: http://${host}:${port}\x1b[0m`);
    console.log(`\x1b[32m  Applications Root: ${config.applications_root}\x1b[0m`);
    console.log(`\x1b[32m===================================================\x1b[0m`);
});
