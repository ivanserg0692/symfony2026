#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const METHOD_ORDER = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
const README_PATH = process.argv[2] ?? 'README.md';
const BLOCK_PATTERN = /<!-- START api-endpoints(?<startParams>[^>]*) -->[\s\S]*?<!-- END api-endpoints(?<endParams>[^>]*) -->/g;

const HEADERS_BY_LOCALE = {
  en: ['Method', 'Path', 'Summary'],
  ru: ['Method', 'Path', 'Описание из OpenAPI'],
};

function main() {
  const readmePath = resolve(process.cwd(), README_PATH);
  const openApiCache = new Map();
  let readme = readFileSync(readmePath, 'utf8');

  if (!BLOCK_PATTERN.test(readme)) {
    throw new Error('Missing generated api-endpoints blocks');
  }

  readme = readme.replace(BLOCK_PATTERN, (match, startParamsRaw, endParamsRaw) => {
    const params = parseBlockParams(startParamsRaw);
    const endParams = parseBlockParams(endParamsRaw);

    validateBlockParams(params, endParams);

    const openApi = readOpenApiForBlock(params, openApiCache);
    const rows = buildRows(openApi);

    return renderGeneratedBlock(params, renderTable(HEADERS_BY_LOCALE[params.locale], rows));
  });

  writeFileSync(readmePath, readme);
}

function parseBlockParams(rawParams) {
  return rawParams
    .trim()
    .split(/\s+/)
    .filter(Boolean)
    .reduce((params, token) => {
      const match = token.match(/^([A-Za-z][\w-]*)=(.+)$/);

      if (!match) {
        throw new Error(`Invalid api-endpoints marker parameter: ${token}`);
      }

      params[match[1]] = match[2];

      return params;
    }, {});
}

function validateBlockParams(params, endParams) {
  for (const name of ['service', 'locale', 'sourceType', 'source']) {
    if (!params[name]) {
      throw new Error(`Missing api-endpoints marker parameter: ${name}`);
    }
  }

  if (!HEADERS_BY_LOCALE[params.locale]) {
    throw new Error(`Unsupported api-endpoints locale: ${params.locale}`);
  }

  if (!['docker', 'file'].includes(params.sourceType)) {
    throw new Error(`Unsupported api-endpoints sourceType: ${params.sourceType}`);
  }

  for (const name of ['service', 'locale']) {
    if (endParams[name] && endParams[name] !== params[name]) {
      throw new Error(`Mismatched api-endpoints ${name}: start=${params[name]} end=${endParams[name]}`);
    }
  }
}

function readOpenApiForBlock(params, openApiCache) {
  const cacheKey = `${params.sourceType}:${params.source}`;

  if (!openApiCache.has(cacheKey)) {
    openApiCache.set(cacheKey, params.sourceType === 'docker'
      ? dumpOpenApiFromService(params.source)
      : readOpenApiFile(params.source));
  }

  return openApiCache.get(cacheKey);
}

function dumpOpenApiFromService(composeService) {
  const output = execFileSync(
    'docker',
    ['compose', 'exec', '-T', composeService, 'php', 'bin/console', 'nelmio:apidoc:dump', '--format=json'],
    {
      cwd: process.cwd(),
      encoding: 'utf8',
      stdio: ['ignore', 'pipe', 'inherit'],
    },
  );

  return JSON.parse(output);
}

function readOpenApiFile(path) {
  return JSON.parse(readFileSync(resolve(process.cwd(), path), 'utf8'));
}

function buildRows(openApi) {
  const paths = openApi.paths ?? {};

  return Object.entries(paths)
    .flatMap(([path, operations]) => METHOD_ORDER
      .filter((method) => operations[method])
      .map((method) => ({
        method: method.toUpperCase(),
        path,
        summary: getOperationSummary(operations[method]),
      })))
    .sort((left, right) => compareRows(left, right));
}

function compareRows(left, right) {
  const pathCompare = left.path.localeCompare(right.path);

  if (pathCompare !== 0) {
    return pathCompare;
  }

  return METHOD_ORDER.indexOf(left.method.toLowerCase()) - METHOD_ORDER.indexOf(right.method.toLowerCase());
}

function getOperationSummary(operation) {
  return firstNonEmpty([
    operation.summary,
    firstLine(operation.description),
    getSuccessResponseDescription(operation.responses),
    operation.operationId,
    '-',
  ]);
}

function getSuccessResponseDescription(responses = {}) {
  for (const status of ['200', '201', '202', '204', 'default']) {
    const description = firstLine(responses[status]?.description);

    if (description) {
      return description;
    }
  }

  return null;
}

function firstLine(value) {
  if (typeof value !== 'string') {
    return null;
  }

  return value.trim().split(/\r?\n/)[0]?.trim() || null;
}

function firstNonEmpty(values) {
  return values.find((value) => typeof value === 'string' && value.trim() !== '')?.trim() ?? '-';
}

function renderTable(header, rows) {
  return [
    `| ${header.join(' | ')} |`,
    `| ${header.map(() => '---').join(' | ')} |`,
    ...rows.map((row) => `| \`${row.method}\` | \`${row.path}\` | ${escapeMarkdown(row.summary)} |`),
  ].join('\n');
}

function escapeMarkdown(value) {
  return value
    .replaceAll('\\', '\\\\')
    .replaceAll('|', '\\|')
    .replace(/\s+/g, ' ');
}

function renderGeneratedBlock(params, content) {
  const start = renderStartMarker(params);
  const end = `<!-- END api-endpoints service=${params.service} locale=${params.locale} -->`;

  return `${start}
${content}
${end}`;
}

function renderStartMarker(params) {
  const markerParams = [
    `service=${params.service}`,
    `locale=${params.locale}`,
    `sourceType=${params.sourceType}`,
    `source=${params.source}`,
  ];

  return `<!-- START api-endpoints ${markerParams.join(' ')} -->`;
}

main();
