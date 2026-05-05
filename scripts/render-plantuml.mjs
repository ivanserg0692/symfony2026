#!/usr/bin/env node

import { deflateRawSync } from 'node:zlib';
import { request as httpRequest } from 'node:http';
import { request as httpsRequest } from 'node:https';
import {
  existsSync,
  mkdirSync,
  readFileSync,
  readdirSync,
  statSync,
  writeFileSync,
} from 'node:fs';
import { dirname, extname, relative, resolve, sep } from 'node:path';

const MARKDOWN_EXTENSIONS = new Set(['.md', '.markdown']);
const IGNORED_DIRS = new Set(['vendor', 'var', 'node_modules', '.git']);
const TARGET_PATH = process.argv[2] ?? 'symfony/docs';
const PLANTUML_SERVER = (process.env.PLANTUML_SERVER ?? 'https://www.plantuml.com/plantuml').replace(/\/$/, '');

async function main() {
  const targetPath = resolve(process.cwd(), TARGET_PATH);
  const markdownFiles = collectMarkdownFiles(targetPath);

  if (markdownFiles.length === 0) {
    throw new Error(`No Markdown files found in ${targetPath}`);
  }

  let renderedCount = 0;

  for (const markdownPath of markdownFiles) {
    const original = readFileSync(markdownPath, 'utf8');
    const { content, rendered } = await replacePlantumlBlocks(original, markdownPath);

    if (content !== original) {
      writeFileSync(markdownPath, content);
    }

    renderedCount += rendered;
  }

  console.log(`PlantUML diagrams processed: ${renderedCount}`);
}

function collectMarkdownFiles(path) {
  if (!existsSync(path)) {
    throw new Error(`Path does not exist: ${path}`);
  }

  const stats = statSync(path);

  if (stats.isFile()) {
    return MARKDOWN_EXTENSIONS.has(extname(path)) ? [path] : [];
  }

  return readdirSync(path)
    .filter((name) => !IGNORED_DIRS.has(name))
    .flatMap((name) => collectMarkdownFiles(resolve(path, name)))
    .sort((left, right) => left.localeCompare(right));
}

async function replacePlantumlBlocks(markdown, markdownPath) {
  const blockPattern = /<!--\s*plantuml\s+([^>]*)-->([\s\S]*?)<!--\s*\/plantuml\s*-->/g;
  let rendered = 0;
  let content = '';
  let offset = 0;

  for (const match of markdown.matchAll(blockPattern)) {
    const [block, rawAttributes] = match;
    const blockStart = match.index ?? 0;
    const attributes = parseAttributes(rawAttributes);
    const sourcePath = resolveAttributePath(markdownPath, attributes.src ?? attributes.path);

    if (!sourcePath) {
      throw new Error(`Missing plantuml src attribute in ${markdownPath}`);
    }

    const outputPath = resolveAttributePath(markdownPath, attributes.out) ?? defaultOutputPath(sourcePath);
    const alt = attributes.alt ?? readableAlt(sourcePath);
    const plantuml = readFileSync(sourcePath, 'utf8');

    await renderPng(plantuml, sourcePath, outputPath);
    rendered++;

    const imagePath = toMarkdownPath(relative(dirname(markdownPath), outputPath));

    content += markdown.slice(offset, blockStart);
    content += `<!-- plantuml src="${toMarkdownPath(relative(dirname(markdownPath), sourcePath))}" alt="${escapeAttribute(alt)}" out="${imagePath}" -->\n![${escapeMarkdownText(alt)}](${imagePath})\n<!-- /plantuml -->`;
    offset = blockStart + block.length;
  }

  content += markdown.slice(offset);

  return { content, rendered };
}

function parseAttributes(rawAttributes) {
  const attributes = {};
  const pattern = /([a-zA-Z][a-zA-Z0-9_-]*)="([^"]*)"/g;
  let match;

  while ((match = pattern.exec(rawAttributes)) !== null) {
    attributes[match[1]] = match[2];
  }

  return attributes;
}

function resolveAttributePath(markdownPath, value) {
  if (!value) {
    return null;
  }

  return resolve(dirname(markdownPath), value);
}

function defaultOutputPath(sourcePath) {
  const docsRoot = findDocsRoot(sourcePath);
  const sourceRelativePath = relative(resolve(docsRoot, 'plantuml'), sourcePath);

  return resolve(docsRoot, 'images', 'plantuml', sourceRelativePath.replace(/\.puml$/i, '.png'));
}

function findDocsRoot(path) {
  let current = dirname(path);

  while (current !== dirname(current)) {
    if (current.endsWith(`${sep}docs`) || current.endsWith('/docs')) {
      return current;
    }

    current = dirname(current);
  }

  throw new Error(`Cannot detect docs root for ${path}`);
}

async function renderPng(plantuml, sourcePath, outputPath) {
  if (existsSync(outputPath) && statSync(outputPath).mtimeMs >= statSync(sourcePath).mtimeMs) {
    return;
  }

  mkdirSync(dirname(outputPath), { recursive: true });

  const encoded = encodePlantuml(plantuml);
  const png = await fetchBuffer(`${PLANTUML_SERVER}/png/${encoded}`);

  writeFileSync(outputPath, png);
}

async function fetchBuffer(url) {
  const result = await new Promise((resolvePromise, rejectPromise) => {
    const req = requestForUrl(url)(url, (res) => {
      const chunks = [];

      res.on('data', (chunk) => chunks.push(chunk));
      res.on('end', () => resolvePromise({
        statusCode: res.statusCode ?? 0,
        body: Buffer.concat(chunks),
      }));
    });

    req.setTimeout(30000, () => req.destroy(new Error(`PlantUML request timed out for ${url}`)));
    req.on('error', rejectPromise);
    req.end();
  });

  if (result.statusCode < 200 || result.statusCode >= 300) {
    throw new Error(`PlantUML server returned HTTP ${result.statusCode} for ${url}`);
  }

  return result.body;
}

function requestForUrl(url) {
  if (url.startsWith('https://')) {
    return httpsRequest;
  }

  if (url.startsWith('http://')) {
    return httpRequest;
  }

  throw new Error(`Unsupported PlantUML server URL: ${url}`);
}

function encodePlantuml(plantuml) {
  return encode64(deflateRawSync(Buffer.from(plantuml, 'utf8')));
}

function encode64(buffer) {
  let result = '';

  for (let i = 0; i < buffer.length; i += 3) {
    const b1 = buffer[i];
    const b2 = i + 1 < buffer.length ? buffer[i + 1] : 0;
    const b3 = i + 2 < buffer.length ? buffer[i + 2] : 0;

    result += append3bytes(b1, b2, b3);
  }

  return result;
}

function append3bytes(b1, b2, b3) {
  const c1 = b1 >> 2;
  const c2 = ((b1 & 0x3) << 4) | (b2 >> 4);
  const c3 = ((b2 & 0xF) << 2) | (b3 >> 6);
  const c4 = b3 & 0x3F;

  return encode6bit(c1 & 0x3F)
    + encode6bit(c2 & 0x3F)
    + encode6bit(c3 & 0x3F)
    + encode6bit(c4 & 0x3F);
}

function encode6bit(value) {
  if (value < 10) {
    return String.fromCharCode(48 + value);
  }

  value -= 10;

  if (value < 26) {
    return String.fromCharCode(65 + value);
  }

  value -= 26;

  if (value < 26) {
    return String.fromCharCode(97 + value);
  }

  value -= 26;

  if (value === 0) {
    return '-';
  }

  if (value === 1) {
    return '_';
  }

  throw new Error(`Invalid 6-bit value: ${value}`);
}

function readableAlt(path) {
  return path
    .replace(/\.puml$/i, '')
    .split(/[\\/]/)
    .at(-1)
    .replaceAll('-', ' ');
}

function toMarkdownPath(path) {
  return path.split(sep).join('/');
}

function escapeAttribute(value) {
  return value.replaceAll('&', '&amp;').replaceAll('"', '&quot;');
}

function escapeMarkdownText(value) {
  return value.replaceAll('[', '\\[').replaceAll(']', '\\]');
}

await main();
