// Генерирует generated/routes.php из openapi.json:
// method + path-шаблон + operationId -> App\Handler\<PascalCase(operationId)>Handler.
import { readFileSync, writeFileSync } from 'node:fs';
import { dirname, join } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const spec = JSON.parse(readFileSync(join(root, 'openapi.json'), 'utf8'));

const HTTP_METHODS = ['get', 'post', 'put', 'patch', 'delete'];

const pascal = (name) => name
  .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
  .split(/[\s-]+/)
  .map((word) => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
  .join('');

const routes = [];
for (const [path, operations] of Object.entries(spec.paths ?? {})) {
  for (const method of HTTP_METHODS) {
    const operation = operations[method];
    if (!operation) {
      continue;
    }
    const { operationId } = operation;
    if (!operationId) {
      throw new Error(`У операции ${method.toUpperCase()} ${path} нет operationId`);
    }
    routes.push({
      method: method.toUpperCase(),
      path,
      operationId,
      handler: `App\\Handler\\${pascal(operationId)}Handler`,
    });
  }
}

routes.sort((a, b) => a.path.localeCompare(b.path) || a.method.localeCompare(b.method));

const single = (value) => `'${value.replaceAll("'", "\\'")}'`;
const lines = routes.map(
  (route) => `    ['method' => ${single(route.method)}, 'path' => ${single(route.path)}, 'operationId' => ${single(route.operationId)}, 'handler' => ${single(route.handler)}],`,
);

const header = '<?php\n\ndeclare(strict_types=1);\n\n// @generated из openapi.json скриптом scripts/generate-routes.mjs. Руками не править.\n\nreturn ';
writeFileSync(join(root, 'generated', 'routes.php'), `${header}[\n${lines.join('\n')}\n];\n`);

console.log(`routes.php: ${routes.length} маршрутов`);
