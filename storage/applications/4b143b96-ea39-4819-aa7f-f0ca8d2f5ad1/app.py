# Auto-generated N1X PANEL Python Starter
import os
import sys
from http.server import HTTPServer, BaseHTTPRequestHandler

port = int(os.environ.get('PORT', 5002))

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
&bull; Server: MAIN AI SERVER<br>
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

