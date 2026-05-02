#!/usr/bin/env node

import { execFileSync } from 'node:child_process';
import { readFileSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const METHOD_ORDER = ['get', 'post', 'put', 'patch', 'delete', 'options', 'head', 'trace'];
const README_PATH = process.argv[2] ?? 'README.md';
const OPENAPI_SOURCE = process.argv[3] ?? null;

const BLOCKS = [
  {
    name: 'api-endpoints-en',
    header: ['Method', 'Path', 'Summary'],
  },
  {
    name: 'api-endpoints-ru',
    header: ['Method', 'Path', 'Описание из OpenAPI'],
  },
];

function main() {
  const readmePath = resolve(process.cwd(), README_PATH);
  const openApi = readOpenApi(OPENAPI_SOURCE);
  const rows = buildRows(openApi);
  let readme = readFileSync(readmePath, 'utf8');

  for (const block of BLOCKS) {
    readme = replaceGeneratedBlock(readme, block.name, renderTable(block.header, rows));
  }

  writeFileSync(readmePath, readme);
}

function readOpenApi(source) {
  if (!source) {
    const output = execFileSync(
      'docker',
      ['compose', 'exec', '-T', 'symfony-cli', 'php', 'bin/console', 'nelmio:apidoc:dump', '--format=json'],
      {
        cwd: process.cwd(),
        encoding: 'utf8',
        stdio: ['ignore', 'pipe', 'inherit'],
      },
    );

    return JSON.parse(output);
  }

  if (source.startsWith('http://') || source.startsWith('https://')) {
    throw new Error('HTTP OpenAPI sources are not supported by this script yet. Dump JSON to a file or omit the source argument.');
  }

  return JSON.parse(readFileSync(resolve(process.cwd(), source), 'utf8'));
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

function replaceGeneratedBlock(readme, name, content) {
  const start = `<!-- START ${name} generated from OpenAPI -->`;
  const end = `<!-- END ${name} generated from OpenAPI -->`;
  const pattern = new RegExp(`${escapeRegExp(start)}[\\s\\S]*?${escapeRegExp(end)}`);

  if (!pattern.test(readme)) {
    throw new Error(`Missing generated block markers for ${name}`);
  }

  return readme.replace(pattern, `${start}\n${content}\n${end}`);
}

function escapeRegExp(value) {
  return value.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

main();
