/**
 * ==============================================================================
 * Verifex Platform - Development HTTP Server (Port 3000)
 * Zero external dependencies. Uses Node.js standard library (http, fs, path).
 * Serves index.html, static assets, and renders platform views for web preview.
 * ==============================================================================
 */

import http from 'node:http';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const PORT = 3000;
const HOST = '0.0.0.0';

const MIME_TYPES = {
  '.html': 'text/html; charset=utf-8',
  '.css': 'text/css; charset=utf-8',
  '.js': 'application/javascript; charset=utf-8',
  '.json': 'application/json; charset=utf-8',
  '.svg': 'image/svg+xml',
  '.png': 'image/png',
  '.jpg': 'image/jpeg',
  '.sql': 'text/plain; charset=utf-8',
  '.php': 'text/html; charset=utf-8'
};

// Render PHP templates into styled HTML for preview
function renderPhpTemplate(filePath) {
  if (!fs.existsSync(filePath)) {
    return null;
  }

  let content = fs.readFileSync(filePath, 'utf8');

  // Helper to resolve includes
  function resolveIncludes(text, baseDir) {
    return text.replace(/<\?php\s+(?:require_once|include_once|require|include)\s+.*?(['"])(.*?)\1;\s*\?>/g, (match, q, incPath) => {
      let resolved = incPath;
      if (resolved.includes('dirname(__DIR__)')) {
        resolved = resolved.replace(/dirname\(__DIR__\)\s*\.\s*/, '');
        resolved = path.join(__dirname, resolved.replace(/^['"]\/|['"]/, ''));
      } else if (resolved.includes('__DIR__')) {
        resolved = resolved.replace(/__DIR__\s*\.\s*/, '');
        resolved = path.join(baseDir, resolved.replace(/^['"]\/|['"]/, ''));
      } else {
        resolved = path.resolve(baseDir, resolved);
      }

      if (fs.existsSync(resolved)) {
        return resolveIncludes(fs.readFileSync(resolved, 'utf8'), path.dirname(resolved));
      }
      return '';
    });
  }

  content = resolveIncludes(content, path.dirname(filePath));

  // If installer page, handle installer-specific template blocks
  if (filePath.includes('installer')) {
    const reqRows = `
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">PHP Version &gt;= 8.2.0</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: 8.2.0 &bull; Detected: 8.2.14</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">PDO Extension</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">PDO MySQL Driver</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">cURL Extension (Provider APIs)</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">OpenSSL Extension (Encryption)</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">Mbstring Extension</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">JSON Extension</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">Ctype Extension</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Enabled &bull; Detected: Enabled</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
      <div class="py-3.5 flex items-center justify-between text-xs">
          <div>
              <span class="font-bold text-gray-800">Config Directory Writable (/config)</span>
              <span class="text-gray-400 block text-[11px] mt-0.5">Required: Writable &bull; Detected: Writable</span>
          </div>
          <span class="badge-success text-xs font-bold inline-flex items-center gap-1">&check; Passed</span>
      </div>
    `;
    content = content.replace(/<\?php\s+if\s*\(\$isInstalled[\s\S]*?<\?php\s+else:\s*\?>/, '');
    content = content.replace(/<\?php\s+endif;\s*\?>\s*<\/main>/, '</main>');
    content = content.replace(/<\?php\s+foreach\s*\(\$systemCheck\['requirements'\][\s\S]*?<\?php\s+endforeach;\s*\?>/, reqRows);
  }

  // Handle echo tags <?= e(...) ?> and <?= ... ?>
  content = content.replace(/<\?=\s*e\((.*?)\)\s*\?>/g, (match, expr) => {
    expr = expr.trim();
    if (expr.includes('APP_NAME')) return 'Verifex SMS Hub';
    if (expr.includes('pageTitle')) return 'SMS Verification Platform';
    if (expr.includes('adminTitle')) return 'Admin Control Hub';
    if (expr.includes('wallet_balance')) return '$42.50';
    if (expr.includes('price')) return '$0.65';
    if (expr.includes('status')) return 'Active';
    if (expr.includes('uuid')) return 'vfx-89a1-432d-98f2';
    if (expr.includes('name')) return 'Developer Account';
    if (expr.includes('email')) return 'user@example.com';
    if (expr.includes('order_number')) return 'ORD-7294A1';
    if (expr.includes('phone') || expr.includes('number')) return '+1 (202) 555-0143';
    if (expr.includes('csrf_token') || expr.includes('csrf_field')) return '<input type="hidden" name="csrf_token" value="preview_token">';
    return '';
  });

  // Handle remaining <?= ... ?>
  content = content.replace(/<\?=\s*(.*?)\s*\?>/g, (match, expr) => {
    expr = expr.trim();
    if (expr.includes('csrf_field')) return '<input type="hidden" name="csrf_token" value="preview_token">';
    if (expr.includes('format_currency')) return '$10.00';
    if (expr.includes('date(')) return '2026-09-27';
    return '';
  });

  // Strip php blocks <?php ... ?>
  content = content.replace(/<\?php[\s\S]*?\?>/g, '');

  // If missing doctype, wrap with standard shell
  if (!content.includes('<!DOCTYPE') && !content.includes('<html')) {
    content = `<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Verifex SMS Hub</title>
  <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
  <link rel="stylesheet" href="/assets/css/app.css">
  <script defer src="/assets/js/app.js"></script>
</head>
<body class="bg-[#FAFAFC] text-[#171522] min-h-screen p-6">
  ${content}
</body>
</html>`;
  }

  return content;
}

const server = http.createServer((req, res) => {
  const urlObj = new URL(req.url, `http://${req.headers.host || 'localhost'}`);
  let pathname = decodeURIComponent(urlObj.pathname);

  // Default route
  if (pathname === '/' || pathname === '') {
    pathname = '/index.html';
  }

  // Handle API mock responses for preview
  if (pathname === '/api/poll-status.php') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({
      success: true,
      status: 'waiting_sms',
      sms_code: null,
      message: 'Active reservation awaiting carrier SMS'
    }));
    return;
  }

  // Handle installer API actions
  if (pathname === '/installer/api.php') {
    let body = '';
    req.on('data', chunk => { body += chunk; });
    req.on('end', () => {
      let data = {};
      try { data = JSON.parse(body); } catch (e) {}
      const action = urlObj.searchParams.get('action') || data.action || '';

      res.writeHead(200, { 'Content-Type': 'application/json; charset=utf-8' });
      if (action === 'test_db') {
        res.end(JSON.stringify({
          success: true,
          message: 'Database connection successful! MySQL 8.0+ server detected.'
        }));
      } else if (action === 'save_db') {
        try {
          const credFile = path.join(__dirname, 'config', 'db_credentials.php');
          const host = data.host || '127.0.0.1';
          const port = parseInt(data.port || '3306', 10);
          const db = data.db || 'sms_platform';
          const user = data.user || 'root';
          const pass = data.pass || '';
          const content = `<?php\ndeclare(strict_types=1);\n\ndefine('DB_HOST', ${JSON.stringify(host)});\ndefine('DB_PORT', ${port});\ndefine('DB_NAME', ${JSON.stringify(db)});\ndefine('DB_USER', ${JSON.stringify(user)});\ndefine('DB_PASS', ${JSON.stringify(pass)});\ndefine('DB_CHARSET', 'utf8mb4');\n`;
          fs.writeFileSync(credFile, content);
        } catch (e) {}
        res.end(JSON.stringify({
          success: true,
          message: 'Database configuration saved.'
        }));
      } else if (action === 'migrate') {
        res.end(JSON.stringify({
          success: true,
          message: 'Database schema migrated and 29 tables verified successfully.'
        }));
      } else if (action === 'create_admin') {
        res.end(JSON.stringify({
          success: true,
          message: 'Administrator account created successfully.'
        }));
      } else if (action === 'save_settings') {
        try {
          const lockFile = path.join(__dirname, 'config', 'installed.lock');
          fs.writeFileSync(lockFile, JSON.stringify({ installed_at: new Date().toISOString() }));
        } catch (e) {}
        res.end(JSON.stringify({
          success: true,
          message: 'Installation finalized and locked.'
        }));
      } else {
        res.end(JSON.stringify({ success: true, message: 'Action processed.' }));
      }
    });
    return;
  }

  let filePath = path.join(__dirname, pathname);

  // If path doesn't have extension and directory exists, look for index.html or index.php
  if (!path.extname(filePath)) {
    if (fs.existsSync(filePath) && fs.statSync(filePath).isDirectory()) {
      const tryHtml = path.join(filePath, 'index.html');
      const tryPhp = path.join(filePath, 'index.php');
      if (fs.existsSync(tryHtml)) filePath = tryHtml;
      else if (fs.existsSync(tryPhp)) filePath = tryPhp;
    } else {
      const tryPhp = filePath + '.php';
      const tryHtml = filePath + '.html';
      if (fs.existsSync(tryPhp)) filePath = tryPhp;
      else if (fs.existsSync(tryHtml)) filePath = tryHtml;
    }
  }

  // Check file exists
  if (!fs.existsSync(filePath) || fs.statSync(filePath).isDirectory()) {
    // If route not found, redirect to index.html
    const fallbackPath = path.join(__dirname, 'index.html');
    if (fs.existsSync(fallbackPath)) {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(fs.readFileSync(fallbackPath, 'utf8'));
      return;
    }
    res.writeHead(404, { 'Content-Type': 'text/plain' });
    res.end('404 Not Found');
    return;
  }

  const ext = path.extname(filePath).toLowerCase();

  // If PHP file, render HTML view
  if (ext === '.php') {
    const rendered = renderPhpTemplate(filePath);
    if (rendered !== null) {
      res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
      res.end(rendered);
      return;
    }
  }

  // Static file serving
  const contentType = MIME_TYPES[ext] || 'application/octet-stream';
  res.writeHead(200, { 'Content-Type': contentType });
  fs.createReadStream(filePath).pipe(res);
});

server.listen(PORT, HOST, () => {
  console.log(`[Verifex Platform] Server running at http://${HOST}:${PORT}/`);
});
