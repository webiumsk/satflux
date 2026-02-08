import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const rootDir = path.resolve(__dirname, '..');

const viteManifest = path.join(rootDir, 'public/build/.vite/manifest.json');
const targetManifest = path.join(rootDir, 'public/build/manifest.json');

if (fs.existsSync(viteManifest)) {
    fs.copyFileSync(viteManifest, targetManifest);
    console.log('✓ Manifest copied from .vite/ to build/');
} else if (fs.existsSync(targetManifest)) {
    console.log('✓ Manifest already exists in build/');
} else {
    console.warn('⚠ Manifest not found in .vite/ or build/');
    process.exit(1);
}

