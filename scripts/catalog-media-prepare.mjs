#!/usr/bin/env node

import { createHash } from 'node:crypto';
import { mkdir, readFile, writeFile, rename, stat } from 'node:fs/promises';
import { dirname, resolve, relative } from 'node:path';
import { fileURLToPath } from 'node:url';
import sharp from 'sharp';

const ROOT = resolve(dirname(fileURLToPath(import.meta.url)), '..');
const DIRECTORY = resolve(ROOT, 'catalog-service/fixtures/media');
const VARIANTS = { thumb: 320, card: 640, main: 1600 };
const BACKGROUND = '#F4F4F6';
const MAX_SOURCE_BYTES = 40 * 1024 * 1024;

async function main() {
  const args = process.argv.slice(2);
  if (args.some((arg) => arg !== '--download')) throw new Error('Usage: npm run catalog:media:prepare -- [--download]');
  const selection = JSON.parse(await readFile(resolve(DIRECTORY, 'sources.json'), 'utf8'));
  const assets = [];
  const hashes = new Set();
  for (const photo of selection.photos) {
    validatePhoto(photo);
    const sourcePath = safePath(photo.path);
    const source = await readSource(photo, sourcePath, args.includes('--download'));
    if (hashes.has(photo.sha256)) throw new Error(`Duplicate source content: ${photo.assetId}. Reuse its existing asset instead.`);
    hashes.add(photo.sha256);
    assets.push(await prepareAsset(photo, source));
    process.stdout.write(`Prepared ${photo.assetId}\n`);
  }
  const retiredAssets = await retainPreviousVersions(assets);
  const manifest = { schemaVersion: 1, libraryVersion: 'demo-v1', processing: { variants: VARIANTS, background: BACKGROUND, quality: 84, fit: 'contain', autoOrient: true, metadata: 'stripped' }, assets, retiredAssets };
  await atomicWrite(resolve(DIRECTORY, 'manifest.json'), JSON.stringify(manifest, null, 2) + '\n');
  await writePreview(assets);
  process.stdout.write(`Ready: ${assets.length} photographs, ${assets.length * 3} WebP variants.\n`);
}

function validatePhoto(photo) {
  if (!/^[a-z0-9-]+$/.test(photo.assetId) || !/^[a-f0-9]{64}$/.test(photo.sha256)
      || !photo.categorySlugs?.length || !photo.productType || !photo.creator || !photo.attribution
      || !/^https:\/\//.test(photo.originalUrl) || !/^https:\/\//.test(photo.sourceUrl)
      || !/^https:\/\//.test(photo.licenseUrl) || !photo.license) {
    throw new Error(`Incomplete photograph provenance: ${photo.assetId}`);
  }
}

function safePath(path) {
  const absolute = resolve(DIRECTORY, path);
  if (!relative(DIRECTORY, absolute) || relative(DIRECTORY, absolute).startsWith('..') || path.includes('\\')) {
    throw new Error('Photo path must stay inside the media library.');
  }
  return absolute;
}

async function readSource(photo, path, download) {
  let bytes;
  try {
    const info = await stat(path);
    if (info.size > MAX_SOURCE_BYTES) throw new Error(`Oversized source: ${photo.assetId}`);
    bytes = await readFile(path);
  } catch (error) {
    if (error.code !== 'ENOENT' || !download) throw error;
    const response = await fetch(photo.originalUrl, { signal: AbortSignal.timeout(60000), headers: { 'User-Agent': 'Symfony2026CatalogFixtures/1.0' } });
    if (!response.ok) throw new Error(`Download failed for ${photo.assetId}: HTTP ${response.status}`);
    const chunks = [];
    let length = 0;
    for await (const chunk of response.body) {
      length += chunk.length;
      if (length > MAX_SOURCE_BYTES) throw new Error(`Oversized source: ${photo.assetId}`);
      chunks.push(chunk);
    }
    bytes = Buffer.concat(chunks);
    verifySource(photo, bytes);
    await atomicWrite(path, bytes);
  }
  verifySource(photo, bytes);
  return bytes;
}

function verifySource(photo, bytes) {
  if (sha256(bytes) !== photo.sha256) throw new Error(`Source changed: ${photo.assetId}. Review provenance before updating its checksum.`);
}

async function prepareAsset(photo, bytes) {
  const metadata = await sharp(bytes).metadata();
  if (!['jpeg', 'png'].includes(metadata.format) || Math.max(metadata.width, metadata.height) < 1600) {
    throw new Error(`Expected a JPEG/PNG photograph at least 1600 px: ${photo.assetId}`);
  }
  const images = {};
  for (const [name, size] of Object.entries(VARIANTS)) {
    images[name] = await sharp(bytes).rotate().flatten({ background: BACKGROUND })
      .resize(size, size, { fit: 'contain', background: BACKGROUND })
      .webp({ quality: 84, effort: 5 }).toBuffer();
  }
  // The identity covers source AND encoded variants, including encoder/configuration changes.
  const version = sha256(Buffer.concat([bytes, ...Object.values(images)])).slice(0, 16);
  const prefix = `catalog/demo/v1/${photo.assetId}/${version}`;
  const variants = {};
  for (const [name, buffer] of Object.entries(images)) {
    const path = `variants/${photo.assetId}/${version}/${name}.webp`;
    await atomicWrite(safePath(path), buffer);
    variants[name] = { path, key: `${prefix}/${name}.webp`, sha256: sha256(buffer), contentType: 'image/webp', width: VARIANTS[name], height: VARIANTS[name], bytes: buffer.length };
  }
  return {
    assetId: photo.assetId, categorySlugs: photo.categorySlugs, productType: photo.productType,
    alt: photo.alt, creator: photo.creator, sourceUrl: photo.sourceUrl, originalUrl: photo.originalUrl,
    license: photo.license, licenseUrl: photo.licenseUrl, attribution: photo.attribution,
    retrievedAt: photo.retrievedAt, modifications: 'Auto-oriented; fitted to a neutral square canvas; resized and encoded as WebP; metadata removed.',
    // Re-encoding derivatives must not duplicate an unchanged original in S3.
    source: { path: photo.path, key: `catalog/demo/v1/${photo.assetId}/${photo.sha256.slice(0, 16)}/source.${metadata.format === 'jpeg' ? 'jpg' : 'png'}`, sha256: photo.sha256, contentType: `image/${metadata.format}`, width: metadata.width, height: metadata.height, bytes: bytes.length },
    variants,
  };
}

function sha256(bytes) {
  return createHash('sha256').update(bytes).digest('hex');
}

async function retainPreviousVersions(assets) {
  try {
    const previous = JSON.parse(await readFile(resolve(DIRECTORY, 'manifest.json'), 'utf8'));
    const activeKeys = new Set(assets.map((asset) => asset.variants.main.key));
    const retired = new Map();
    for (const asset of [...previous.assets, ...(previous.retiredAssets ?? [])]) {
      if (!activeKeys.has(asset.variants.main.key)) retired.set(asset.variants.main.key, asset);
    }
    return [...retired.values()];
  } catch (error) {
    if (error.code !== 'ENOENT') throw error;
    return [];
  }
}

async function writePreview(assets) {
  const escape = (value) => String(value).replaceAll('&', '&amp;').replaceAll('<', '&lt;').replaceAll('>', '&gt;').replaceAll('"', '&quot;');
  const cards = assets.map((asset) => `<article data-search="${escape(`${asset.productType} ${asset.categorySlugs.join(' ')}`.toLowerCase())}">
    <img src="../fixtures/media/${escape(asset.variants.thumb.path)}" alt="${escape(asset.alt)}" width="320" height="320" loading="lazy">
    <h2>${escape(asset.productType)}</h2><p class="category">${escape(asset.categorySlugs.join(', '))}</p>
    <p><a href="${escape(asset.sourceUrl)}">Photo: ${escape(asset.creator)}</a><br><a href="${escape(asset.licenseUrl)}">${escape(asset.license)}</a></p>
    <details><summary>Provenance</summary><p>${escape(asset.attribution)}</p><p>${escape(asset.modifications)}</p><code>${escape(asset.assetId)}</code></details>
  </article>`).join('\n');
  const page = `<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Catalog fixture photographs</title>
<style>
:root{color-scheme:light;--bg:#f7f7fa;--surface:#fff;--ink:#18181b;--muted:#52525b;--line:#e4e4e7}html.dark{color-scheme:dark;--bg:#101116;--surface:#191b23;--ink:#f4f4f5;--muted:#a1a1aa;--line:#30323d}*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--ink);font:15px/1.5 Inter,system-ui,sans-serif}header,main{max-width:1440px;margin:auto;padding:24px}h1{margin:0;font-size:28px}header p{color:var(--muted);max-width:900px}nav{display:flex;gap:12px;flex-wrap:wrap}input,button{border:1px solid var(--line);border-radius:10px;padding:12px 16px;font:inherit;background:var(--surface);color:var(--ink)}input{min-width:260px;flex:1}button{background:#7c3aed;color:white;cursor:pointer}input:focus-visible,button:focus-visible,a:focus-visible,summary:focus-visible{outline:3px solid #7c3aed;outline-offset:3px}main{display:grid;grid-template-columns:repeat(auto-fill,minmax(230px,1fr));gap:20px;padding-top:0}article{min-width:0;border:1px solid var(--line);border-radius:16px;padding:12px;background:var(--surface)}article[hidden]{display:none}img{display:block;width:100%;height:auto;aspect-ratio:1;object-fit:contain;border-radius:10px;background:#f4f4f6}h2{font-size:17px;margin:12px 0 4px}.category{font-size:11px;overflow-wrap:anywhere;color:var(--muted)}a{color:inherit;text-underline-offset:3px}article p,details{font-size:12px}details{overflow-wrap:anywhere}summary{cursor:pointer}code{font-size:11px}
</style></head><body><header><h1>Catalog fixture photographs</h1><p>${assets.length} downloaded photographs. The same photograph is reused by matching fixture products. This is a library review page, not the storefront. Sources, licenses and modifications appear on each card.</p><nav><input id="filter" aria-label="Filter photographs by product type or category" placeholder="Filter by product type or category"><button id="theme" type="button" aria-pressed="false">Dark theme</button></nav></header><main>${cards}</main>
<script>
document.getElementById('filter').addEventListener('input',event=>{const terms=event.target.value.toLowerCase().trim().split(/\\s+/);for(const card of document.querySelectorAll('article'))card.hidden=!terms.every(term=>card.dataset.search.includes(term));});
document.getElementById('theme').addEventListener('click',event=>{const dark=document.documentElement.classList.toggle('dark');event.target.setAttribute('aria-pressed',String(dark));event.target.textContent=dark?'Light theme':'Dark theme';});
</script></body></html>\n`;
  await atomicWrite(resolve(ROOT, 'catalog-service/docs/fixture-media-preview.html'), page);
}

async function atomicWrite(path, contents) {
  await mkdir(dirname(path), { recursive: true });
  const temporary = `${path}.tmp`;
  await writeFile(temporary, contents);
  await rename(temporary, path);
}

main().catch((error) => { process.stderr.write(`${error.message}\n`); process.exitCode = 1; });
