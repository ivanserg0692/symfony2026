#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import {
  existsSync,
  mkdirSync,
  readdirSync,
  statSync,
  readFileSync,
  writeFileSync,
} from 'node:fs';
import { dirname, extname, relative, resolve, sep } from 'node:path';

const MARKDOWN_EXTENSIONS = new Set(['.md', '.markdown']);
const IGNORED_DIRS = new Set(['vendor', 'var', 'node_modules', '.git']);
const TARGET_PATHS = process.argv.slice(2);
const PLANTUML_SERVICE = process.env.PLANTUML_SERVICE ?? 'plantuml';

async function main() {
  const targetPaths = TARGET_PATHS.length > 0 ? TARGET_PATHS : ['symfony/docs'];
  const markdownFiles = targetPaths
    .flatMap((targetPath) => collectMarkdownFiles(resolve(process.cwd(), targetPath)))
    .sort((left, right) => left.localeCompare(right));

  if (markdownFiles.length === 0) {
    throw new Error(`No Markdown files found in ${targetPaths.join(', ')}`);
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
    renderPng(sourcePath, outputPath);
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

function renderPng(sourcePath, outputPath) {
  if (existsSync(outputPath) && statSync(outputPath).mtimeMs >= statSync(sourcePath).mtimeMs) {
    return;
  }

  mkdirSync(dirname(outputPath), { recursive: true });

  try {
    execFileSync(
      'docker',
      [
        'compose',
        'run',
        '--rm',
        PLANTUML_SERVICE,
        '-tpng',
        '-o',
        toContainerPath(relative(dirname(sourcePath), dirname(outputPath))),
        toContainerPath(relative(process.cwd(), sourcePath)),
      ],
      {
        cwd: process.cwd(),
        stdio: 'inherit',
      },
    );
  } catch (error) {
    throw new Error(
      `Failed to render PlantUML locally with Docker service "${PLANTUML_SERVICE}". `
      + 'Make sure the image is available by running: docker compose run --rm plantuml -version',
      { cause: error },
    );
  }
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

function toContainerPath(path) {
  return path.split(sep).join('/');
}

function escapeAttribute(value) {
  return value.replaceAll('&', '&amp;').replaceAll('"', '&quot;');
}

function escapeMarkdownText(value) {
  return value.replaceAll('[', '\\[').replaceAll(']', '\\]');
}

await main();
