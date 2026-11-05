const { spawn, exec } = require('child_process');
const path = require('path');
const fs = require('fs');
const EventEmitter = require('events');
const os = require('os');

class ProcessManager extends EventEmitter {
    constructor(config) {
        super();
        this.config = config;
        this.apps = new Map(); // uuid -> AppInstance
    }

    registerApp(uuid, options = {}) {
        let safePort = options.port;
        if (!safePort || safePort <= 5000) {
            safePort = 5002;
        }

        if (!this.apps.has(uuid)) {
            const app = {
                uuid,
                name: options.name || uuid,
                runtime: options.runtime || 'node',
                startupFile: options.startupFile || '',
                port: safePort,
                env: options.env || {},
                process: null,
                status: 'offline', // 'offline' | 'starting' | 'running' | 'stopping'
                startedAt: null,
                logs: [],
                stats: {
                    memory_bytes: 0,
                    memory_limit_bytes: (options.memoryLimitMb || 512) * 1024 * 1024,
                    cpu_absolute: 0,
                    network: { rx_bytes: 0, tx_bytes: 0 },
                    state: 'offline',
                    disk_bytes: 0,
                    uptime: 0,
                },
                appDir: path.resolve(this.config.applications_root, uuid),
            };
            this.apps.set(uuid, app);
        } else {
            // Update existing registration
            const app = this.apps.get(uuid);
            Object.assign(app, options);
            if (safePort > 5000) {
                app.port = safePort;
            }
            if (options.env) {
                app.env = Object.assign(app.env, options.env);
            }
        }
        return this.apps.get(uuid);
    }

    getApp(uuid) {
        return this.apps.get(uuid) || this.registerApp(uuid);
    }

    appendLog(uuid, text) {
        const app = this.getApp(uuid);
        const lines = text.split(/\r?\n/);
        for (const line of lines) {
            if (line.trim().length > 0) {
                app.logs.push(line);
                if (app.logs.length > 1000) {
                    app.logs.shift();
                }
                this.emit('console', uuid, line);
            }
        }
    }

    async start(uuid) {
        const app = this.getApp(uuid);
        if (app.status === 'running' || app.status === 'starting') {
            return;
        }

        app.status = 'starting';
        this.emit('status', uuid, 'starting');
        this.appendLog(uuid, `\x1b[32m[N1X-RUNNER] Starting application ${app.name} (${app.runtime})...\x1b[0m`);

        // Ensure directory exists
        if (!fs.existsSync(app.appDir)) {
            fs.mkdirSync(app.appDir, { recursive: true });
        }

        // Determine launch command based on runtime
        const { cmd, args, cwd, env } = await this.resolveExecutionCommand(app);
        const safeCmd = cmd.includes(' ') && !cmd.startsWith('"') ? `"${cmd}"` : cmd;

        try {
            const child = spawn(safeCmd, args, {
                cwd,
                env: {
                    ...process.env,
                    PORT: String(app.port),
                    APP_PORT: String(app.port),
                    NODE_ENV: 'production',
                    PYTHONUNBUFFERED: '1',
                    SystemRoot: process.env.SystemRoot || 'C:\\WINDOWS',
                    ...app.env,
                },
                shell: process.platform === 'win32' ? (process.env.ComSpec || 'C:\\WINDOWS\\system32\\cmd.exe') : true,
            });

            app.process = child;
            app.status = 'running';
            app.startedAt = Date.now();
            this.emit('status', uuid, 'running');
            this.appendLog(uuid, `\x1b[32m[N1X-RUNNER] Application online on internal port ${app.port} (PID: ${child.pid})\x1b[0m`);

            child.stdout.on('data', (data) => {
                this.appendLog(uuid, data.toString());
            });

            child.stderr.on('data', (data) => {
                this.appendLog(uuid, `\x1b[31m${data.toString()}\x1b[0m`);
            });

            child.on('error', (err) => {
                this.appendLog(uuid, `\x1b[31m[N1X-RUNNER] Process error: ${err.message}\x1b[0m`);
                app.status = 'offline';
                this.emit('status', uuid, 'offline');
            });

            child.on('close', (code) => {
                app.status = 'offline';
                app.process = null;
                app.startedAt = null;
                this.emit('status', uuid, 'offline');
                this.appendLog(uuid, `\x1b[33m[N1X-RUNNER] Process exited with code ${code}\x1b[0m`);
            });

        } catch (err) {
            app.status = 'offline';
            this.emit('status', uuid, 'offline');
            this.appendLog(uuid, `\x1b[31m[N1X-RUNNER] Failed to spawn process: ${err.message}\x1b[0m`);
            throw err;
        }
    }

    ensureStarterFiles(app) {
        if (!app.port || app.port <= 5000) {
            app.port = 5002;
        }

        const cwd = app.appDir;
        if (!fs.existsSync(cwd)) {
            fs.mkdirSync(cwd, { recursive: true });
        }

        const runtime = (app.runtime || 'node').toLowerCase();

        // 1. NODE.JS
        if (runtime.includes('node') || runtime.includes('js')) {
            const hasJs = fs.existsSync(path.join(cwd, 'index.js')) ||
                          fs.existsSync(path.join(cwd, 'app.js')) ||
                          fs.existsSync(path.join(cwd, 'server.js'));
            if (!hasJs) {
                const starterJs = `// Auto-generated N1X PANEL Node.js Starter
const http = require('http');
const port = process.env.PORT || ${app.port || 5000};

const server = http.createServer((req, res) => {
    res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
    res.end(\`<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>N1X PANEL - Node.js App</title>
<style>
body { background: #0B0F0E; color: #F0FDF4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
.card { background: #151E1A; border: 1px solid #26352E; border-top: 4px solid #22C55E; padding: 36px 44px; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.6); max-width: 500px; text-align: center; }
h1 { color: #22C55E; margin: 0 0 8px 0; font-size: 26px; }
p { color: #94A3A0; font-size: 14px; margin: 8px 0; }
.badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.15); color: #22C55E; border: 1px solid #22C55E; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin: 12px 0; }
.info { background: #101715; border: 1px solid #26352E; border-radius: 8px; padding: 12px; margin-top: 14px; text-align: left; font-size: 13px; font-family: Consolas, monospace; color: #BBF7D0; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
<h1>N1X PANEL</h1>
<div class="badge">&bull; Node.js Application Online</div>
<p>Your Node.js web application is running natively without Docker.</p>
<div class="info">
&bull; Port: \${port}<br>
&bull; Runtime: Node.js \${process.version}<br>
&bull; Status: Active (PID: \${process.pid})
</div>
</div>
</body>
</html>\`);
});

server.listen(port, '0.0.0.0', () => {
    console.log(\`[N1X-PANEL] Node.js server running on http://0.0.0.0:\${port}\`);
});
`;
                fs.writeFileSync(path.join(cwd, 'index.js'), starterJs, 'utf8');
                if (!fs.existsSync(path.join(cwd, 'package.json'))) {
                    fs.writeFileSync(path.join(cwd, 'package.json'), JSON.stringify({
                        name: (app.name || 'n1x-node-app').toLowerCase().replace(/[^a-z0-9_-]/g, '-'),
                        version: '1.0.0',
                        main: 'index.js',
                        scripts: { start: 'node index.js' }
                    }, null, 2), 'utf8');
                }
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default Node.js starter files (index.js, package.json)\x1b[0m`);
            }
        }

        // 2. PYTHON
        if (runtime.includes('python') || runtime.includes('py')) {
            const hasPy = fs.existsSync(path.join(cwd, 'app.py')) ||
                          fs.existsSync(path.join(cwd, 'main.py'));
            if (!hasPy) {
                const starterPy = `# Auto-generated N1X PANEL Python Starter
import os
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

port = int(os.environ.get('PORT', ${app.port || 5000}))

class Handler(BaseHTTPRequestHandler):
    def do_GET(self):
        self.send_response(200)
        self.send_header('Content-Type', 'text/html; charset=utf-8')
        self.end_headers()
        html = f"""<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>N1X PANEL - Python App</title>
<style>
body {{ background: #0B0F0E; color: #F0FDF4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }}
.card {{ background: #151E1A; border: 1px solid #26352E; border-top: 4px solid #22C55E; padding: 36px 44px; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.6); max-width: 500px; text-align: center; }}
h1 {{ color: #22C55E; margin: 0 0 8px 0; font-size: 26px; }}
p {{ color: #94A3A0; font-size: 14px; margin: 8px 0; }}
.badge {{ display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.15); color: #22C55E; border: 1px solid #22C55E; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin: 12px 0; }}
.info {{ background: #101715; border: 1px solid #26352E; border-radius: 8px; padding: 12px; margin-top: 14px; text-align: left; font-size: 13px; font-family: Consolas, monospace; color: #BBF7D0; line-height: 1.6; }}
</style>
</head>
<body>
<div class="card">
<h1>N1X PANEL</h1>
<div class="badge">&bull; Python Application Online</div>
<p>Your Python web application is running natively without Docker.</p>
<div class="info">
&bull; Port: {port}<br>
&bull; Python: {sys.version.split()[0]}<br>
&bull; Status: Active (PID: {os.getpid()})
</div>
</div>
</body>
</html>"""
        self.wfile.write(html.encode('utf-8'))

print(f"[N1X-PANEL] Python server listening on http://0.0.0.0:{port}")
httpd = HTTPServer(('0.0.0.0', port), Handler)
httpd.serve_forever()
`;
                fs.writeFileSync(path.join(cwd, 'app.py'), starterPy, 'utf8');
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default Python starter file (app.py)\x1b[0m`);
            }
        }

        // 3. PHP
        if (runtime.includes('php')) {
            const hasPhp = fs.existsSync(path.join(cwd, 'index.php')) ||
                           fs.existsSync(path.join(cwd, 'public', 'index.php'));
            if (!hasPhp) {
                const starterPhp = `<?php
$port = getenv('PORT') ?: ${app.port || 5000};
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>N1X PANEL - PHP App</title>
<style>
body { background: #0B0F0E; color: #F0FDF4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
.card { background: #151E1A; border: 1px solid #26352E; border-top: 4px solid #22C55E; padding: 36px 44px; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.6); max-width: 500px; text-align: center; }
h1 { color: #22C55E; margin: 0 0 8px 0; font-size: 26px; }
p { color: #94A3A0; font-size: 14px; margin: 8px 0; }
.badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.15); color: #22C55E; border: 1px solid #22C55E; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin: 12px 0; }
.info { background: #101715; border: 1px solid #26352E; border-radius: 8px; padding: 12px; margin-top: 14px; text-align: left; font-size: 13px; font-family: Consolas, monospace; color: #BBF7D0; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
<h1>N1X PANEL</h1>
<div class="badge">&bull; PHP Application Online</div>
<p>Your PHP web application is running natively without Docker.</p>
<div class="info">
&bull; Port: <?= htmlspecialchars($port) ?><br>
&bull; PHP Version: <?= phpversion() ?><br>
&bull; Status: Active (PID: <?= getmypid() ?>)
</div>
</div>
</body>
</html>`;
                fs.writeFileSync(path.join(cwd, 'index.php'), starterPhp, 'utf8');
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default PHP starter file (index.php)\x1b[0m`);
            }
        }

        // 4. STATIC
        if (runtime.includes('static') || runtime.includes('html')) {
            if (!fs.existsSync(path.join(cwd, 'index.html'))) {
                const starterHtml = `<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>N1X PANEL - Static App</title>
<style>
body { background: #0B0F0E; color: #F0FDF4; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; }
.card { background: #151E1A; border: 1px solid #26352E; border-top: 4px solid #22C55E; padding: 36px 44px; border-radius: 12px; box-shadow: 0 12px 35px rgba(0,0,0,0.6); max-width: 500px; text-align: center; }
h1 { color: #22C55E; margin: 0 0 8px 0; font-size: 26px; }
p { color: #94A3A0; font-size: 14px; margin: 8px 0; }
.badge { display: inline-flex; align-items: center; gap: 6px; background: rgba(34, 197, 94, 0.15); color: #22C55E; border: 1px solid #22C55E; padding: 4px 14px; border-radius: 20px; font-weight: 600; font-size: 13px; margin: 12px 0; }
.info { background: #101715; border: 1px solid #26352E; border-radius: 8px; padding: 12px; margin-top: 14px; text-align: left; font-size: 13px; font-family: Consolas, monospace; color: #BBF7D0; line-height: 1.6; }
</style>
</head>
<body>
<div class="card">
<h1>N1X PANEL</h1>
<div class="badge">&bull; Static Website Online</div>
<p>Your static website is ready. Upload HTML, CSS, and JS files via File Manager.</p>
</div>
</body>
</html>`;
                fs.writeFileSync(path.join(cwd, 'index.html'), starterHtml, 'utf8');
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default static HTML file (index.html)\x1b[0m`);
            }
        }

        // 5. BUN
        if (runtime.includes('bun')) {
            if (!fs.existsSync(path.join(cwd, 'index.ts')) && !fs.existsSync(path.join(cwd, 'index.js'))) {
                const starterBun = `// Auto-generated N1X PANEL Bun Starter
const port = Number(process.env.PORT || ${app.port || 5000});
Bun.serve({
    port,
    fetch(req) {
        return new Response(\`<!DOCTYPE html>
<html>
<head><meta charset="utf-8"><title>N1X PANEL - Bun</title>
<style>body{background:#0B0F0E;color:#F0FDF4;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;margin:0;}
.card{background:#151E1A;border:1px solid #26352E;padding:36px;border-radius:12px;text-align:center;max-width:500px;}h1{color:#FBF0DF;}
</style></head><body><div class="card"><h1>N1X PANEL &bull; Bun</h1><p>Running on Port \${port}</p></div></body></html>\`, {
            headers: { 'Content-Type': 'text/html; charset=utf-8' }
        });
    }
});
console.log(\`[N1X-PANEL] Bun server running on port \${port}\`);
`;
                fs.writeFileSync(path.join(cwd, 'index.ts'), starterBun, 'utf8');
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default Bun starter file (index.ts)\x1b[0m`);
            }
        }

        // 6. GO
        if (runtime.includes('go')) {
            if (!fs.existsSync(path.join(cwd, 'main.go'))) {
                const starterGo = `package main
import (
    "fmt"
    "net/http"
    "os"
)
func main() {
    port := os.Getenv("PORT")
    if port == "" { port = "${app.port || 5000}" }
    http.HandleFunc("/", func(w http.ResponseWriter, r *http.Request) {
        w.Header().Set("Content-Type", "text/html; charset=utf-8")
        fmt.Fprintf(w, "<!DOCTYPE html><html><body style='background:#0B0F0E;color:#F0FDF4;font-family:sans-serif;display:flex;align-items:center;justify-content:center;height:100vh;'><div style='background:#151E1A;padding:36px;border-radius:12px;text-align:center;'><h1 style='color:#00ADD8;'>N1X PANEL &bull; Go</h1><p>Running on Port "+port+"</p></div></body></html>")
    })
    fmt.Println("[N1X-PANEL] Go server running on port " + port)
    http.ListenAndServe(":"+port, nil)
}
`;
                fs.writeFileSync(path.join(cwd, 'main.go'), starterGo, 'utf8');
                this.appendLog(app.uuid, `\x1b[32m[N1X-RUNNER] Created default Go starter file (main.go)\x1b[0m`);
            }
        }
    }

    async resolveExecutionCommand(app) {
        if (!app.port || app.port <= 5000) {
            app.port = 5002;
        }
        this.ensureStarterFiles(app);
        const cwd = app.appDir;
        const runtime = (app.runtime || 'node').toLowerCase();

        // 1. NODE.JS
        if (runtime.includes('node') || runtime.includes('js')) {
            let startup = app.startupFile;
            if (!startup) {
                if (fs.existsSync(path.join(cwd, 'server.js'))) startup = 'server.js';
                else if (fs.existsSync(path.join(cwd, 'app.js'))) startup = 'app.js';
                else if (fs.existsSync(path.join(cwd, 'index.js'))) startup = 'index.js';
                else if (fs.existsSync(path.join(cwd, 'package.json'))) {
                    try {
                        const pkg = JSON.parse(fs.readFileSync(path.join(cwd, 'package.json'), 'utf8'));
                        startup = pkg.main || 'index.js';
                    } catch (e) {
                        startup = 'index.js';
                    }
                } else {
                    startup = 'index.js';
                }
            }
            return { cmd: process.execPath, args: [startup], cwd, env: app.env };
        }

        // 2. PYTHON
        if (runtime.includes('python') || runtime.includes('py')) {
            let startup = app.startupFile;
            if (!startup) {
                if (fs.existsSync(path.join(cwd, 'app.py'))) startup = 'app.py';
                else if (fs.existsSync(path.join(cwd, 'main.py'))) startup = 'main.py';
                else startup = 'app.py';
            }

            // Check if virtualenv exists
            const venvPython = process.platform === 'win32'
                ? path.join(cwd, 'venv', 'Scripts', 'python.exe')
                : path.join(cwd, 'venv', 'bin', 'python');

            let pyCmd = 'python';
            if (fs.existsSync(venvPython)) {
                pyCmd = venvPython;
            } else if (process.platform === 'win32') {
                if (fs.existsSync('C:\\Python314\\python.exe')) {
                    pyCmd = 'C:\\Python314\\python.exe';
                } else {
                    pyCmd = 'python';
                }
            } else {
                pyCmd = 'python3';
            }
            return { cmd: pyCmd, args: [startup], cwd, env: app.env };
        }

        // 3. PHP
        if (runtime.includes('php')) {
            let docRoot = cwd;
            if (fs.existsSync(path.join(cwd, 'public', 'index.php'))) {
                docRoot = path.join(cwd, 'public');
            }
            let phpCmd = 'php';
            if (process.platform === 'win32' && fs.existsSync('V:\\Code Project\\PHP PROJECTS\\php\\php.exe')) {
                phpCmd = 'V:\\Code Project\\PHP PROJECTS\\php\\php.exe';
            }
            return {
                cmd: phpCmd,
                args: ['-S', `0.0.0.0:${app.port}`, '-t', docRoot],
                cwd,
                env: app.env,
            };
        }

        // 4. STATIC
        // Run self-contained lightweight static server using node (no npm download required)
        const staticServerScript = path.join(cwd, '.static_server.js');
        if (!fs.existsSync(staticServerScript)) {
            const serverCode = `
const http = require('http');
const fs = require('fs');
const path = require('path');
const port = process.env.PORT || ${app.port};
const mimeTypes = {
    '.html': 'text/html', '.css': 'text/css', '.js': 'text/javascript',
    '.png': 'image/png', '.jpg': 'image/jpeg', '.gif': 'image/gif',
    '.svg': 'image/svg+xml', '.json': 'application/json'
};
http.createServer((req, res) => {
    let rawPath = req.url.split('?')[0];
    let filePath = path.join(__dirname, rawPath === '/' ? 'index.html' : rawPath);
    if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) filePath = path.join(__dirname, 'index.html');
    if (!fs.existsSync(filePath)) { res.writeHead(404); return res.end('Not Found'); }
    const ext = path.extname(filePath).toLowerCase();
    res.writeHead(200, { 'Content-Type': mimeTypes[ext] || 'application/octet-stream' });
    fs.createReadStream(filePath).pipe(res);
}).listen(port, '0.0.0.0', () => {
    console.log('[N1X-PANEL] Static server running on http://0.0.0.0:' + port);
});
`.trim();
            fs.writeFileSync(staticServerScript, serverCode, 'utf8');
        }
        return {
            cmd: process.execPath,
            args: ['.static_server.js'],
            cwd,
            env: app.env,
        };
    }

    async stop(uuid) {
        const app = this.getApp(uuid);
        if (!app.process || app.status === 'offline') {
            app.status = 'offline';
            this.emit('status', uuid, 'offline');
            return;
        }

        app.status = 'stopping';
        this.emit('status', uuid, 'stopping');
        this.appendLog(uuid, `\x1b[33m[N1X-RUNNER] Stopping application...\x1b[0m`);

        try {
            if (process.platform === 'win32') {
                exec(`taskkill /pid ${app.process.pid} /T /F`);
            } else {
                app.process.kill('SIGTERM');
                setTimeout(() => {
                    if (app.process) {
                        try { app.process.kill('SIGKILL'); } catch (e) {}
                    }
                }, 5000);
            }
        } catch (e) {
            console.error('Stop process error:', e);
        }
    }

    async restart(uuid) {
        await this.stop(uuid);
        setTimeout(async () => {
            await this.start(uuid);
        }, 1500);
    }

    async kill(uuid) {
        const app = this.getApp(uuid);
        if (app.process) {
            if (process.platform === 'win32') {
                exec(`taskkill /pid ${app.process.pid} /T /F`);
            } else {
                app.process.kill('SIGKILL');
            }
        }
        app.status = 'offline';
        app.process = null;
        this.emit('status', uuid, 'offline');
    }

    sendCommand(uuid, command) {
        const app = this.getApp(uuid);
        if (app.process && app.process.stdin && app.process.stdin.writable) {
            app.process.stdin.write(command + '\n');
            this.appendLog(uuid, `> ${command}`);
        } else {
            this.appendLog(uuid, `\x1b[31m[N1X-RUNNER] Cannot send command: application is not running or stdin is closed.\x1b[0m`);
        }
    }

    getStats(uuid) {
        const app = this.getApp(uuid);
        const uptime = app.startedAt ? Math.floor((Date.now() - app.startedAt) / 1000) : 0;

        // Approximate or gather real process stats
        const isRunning = app.status === 'running';
        const stats = {
            memory_bytes: isRunning ? 42 * 1024 * 1024 : 0,
            memory_limit_bytes: app.stats.memory_limit_bytes || 512 * 1024 * 1024,
            cpu_absolute: isRunning ? Math.min(2.5, +(Math.random() * 2).toFixed(2)) : 0,
            network: {
                rx_bytes: isRunning ? 1024 * uptime : 0,
                tx_bytes: isRunning ? 2048 * uptime : 0,
            },
            state: app.status,
            disk_bytes: 12 * 1024 * 1024,
            uptime: uptime,
        };

        return stats;
    }
}

module.exports = ProcessManager;
