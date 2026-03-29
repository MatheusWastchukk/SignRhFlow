import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const root = path.join(__dirname, '..');
const envPath = path.join(root, '.env');

const DEFAULT_URL = 'http://localhost:8000/api';

function parseEnv(content) {
  const out = {};
  for (const line of content.split('\n')) {
    const t = line.trim();
    if (!t || t.startsWith('#')) continue;
    const i = t.indexOf('=');
    if (i === -1) continue;
    const k = t.slice(0, i).trim();
    let v = t.slice(i + 1).trim();
    if (
      (v.startsWith('"') && v.endsWith('"')) ||
      (v.startsWith("'") && v.endsWith("'"))
    ) {
      v = v.slice(1, -1);
    }
    out[k] = v;
  }
  return out;
}

let env = {};
if (fs.existsSync(envPath)) {
  env = parseEnv(fs.readFileSync(envPath, 'utf8'));
}

const prodFlag = process.argv.includes('--production');
const devUrl = env.NG_API_URL || DEFAULT_URL;
const prodUrl =
  env.NG_API_URL_PRODUCTION || env.NG_API_URL || DEFAULT_URL;

const devBody = `// Gerado por scripts/sync-web-env.mjs (NG_API_URL em .env)
export const environment = {
  production: false,
  apiBaseUrl: ${JSON.stringify(devUrl)},
};
`;

const prodBody = `// Gerado por scripts/sync-web-env.mjs --production (NG_API_URL_PRODUCTION ou NG_API_URL)
export const environment = {
  production: true,
  apiBaseUrl: ${JSON.stringify(prodUrl)},
};
`;

if (prodFlag) {
  fs.writeFileSync(path.join(root, 'src/environments/environment.ts'), prodBody);
} else {
  fs.writeFileSync(
    path.join(root, 'src/environments/environment.development.ts'),
    devBody
  );
}
