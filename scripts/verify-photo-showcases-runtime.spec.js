const { spawnSync } = require('node:child_process');
const path = require('node:path');

const root = path.resolve(__dirname, '..');
const result = spawnSync('docker', ['compose', 'config', '--format', 'json'], {
  cwd: root,
  encoding: 'utf8',
  shell: process.platform === 'win32',
});

if (result.status !== 0) {
  process.stderr.write(result.stderr || result.stdout || 'docker compose config failed\n');
  process.exit(result.status || 1);
}

const config = JSON.parse(result.stdout);
const volumes = config.services?.wordpress?.volumes ?? [];
const targets = new Set(volumes.map((volume) => volume.target));
const requiredTargets = [
  '/var/www/html/wp-content/plugins/theobroma-photo-showcases',
  '/var/www/html/wp-content/mu-plugins',
];
const missing = requiredTargets.filter((target) => !targets.has(target));

if (missing.length > 0) {
  process.stderr.write(`Missing photo showcases runtime mounts: ${missing.join(', ')}\n`);
  process.exit(1);
}

process.stdout.write('Photo showcases plugin and its auto-loader are mounted into WordPress.\n');
