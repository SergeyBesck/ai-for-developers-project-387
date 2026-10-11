// Смоук сгенерированного бандла SDK против openapi.json: без сети и PHP-сервера,
// на встроенном node --test. Операции и обязательные поля берутся из контракта,
// чтобы тест ловил расхождение бандла и openapi.json.
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';

import * as sdk from '../../public/assets/sdk.gen.js';

const openapi = JSON.parse(readFileSync(new URL('../../openapi.json', import.meta.url), 'utf8'));

const operationIds = Object.values(openapi.paths)
  .flatMap((pathItem) => Object.values(pathItem))
  .filter((operation) => typeof operation.operationId === 'string')
  .map((operation) => operation.operationId);

const resolveRef = (schema) => {
  if (!schema.$ref) {
    return schema;
  }
  return schema.$ref
    .replace(/^#\//, '')
    .split('/')
    .reduce((node, key) => node[key], openapi);
};

// Абсолютный baseUrl задаём только в тесте: относительный "/api" в Node
// не собирается в валидный URL для fetch. Генерированный бандл и контракт не трогаем.
const serverUrl = openapi.servers[0].url;
const baseUrl = new URL(serverUrl, 'http://sdk-smoke.test').toString();

const stubValue = (schema) => {
  if (schema.format === 'uuid') return '2f4b1c68-8c0f-4a5e-9a1b-6d3e7f2c5b9a';
  if (schema.format === 'date-time') return '2026-10-12T10:00:00+03:00';
  if (schema.format === 'email') return 'guest@example.com';
  if (schema.type === 'integer') return 30;
  if (schema.nullable) return null;
  return 'stub';
};

// Заглушка ответа собирается из обязательных полей схемы контракта.
const stubFromSchema = (schema) =>
  Object.fromEntries(schema.required.map((field) => [field, stubValue(schema.properties[field])]));

// Подмена globalThis.fetch: перехватываем Request и отвечаем заглушкой.
const stubFetch = (body, status = 200) => {
  const requests = [];
  const original = globalThis.fetch;
  globalThis.fetch = async (request) => {
    requests.push(request);
    return new Response(JSON.stringify(body), {
      status,
      headers: { 'Content-Type': 'application/json' },
    });
  };
  return {
    requests,
    restore: () => {
      globalThis.fetch = original;
    },
  };
};

test('бандл экспортирует функции всех операций контракта', () => {
  assert.ok(operationIds.length > 0, 'в openapi.json нет операций');

  for (const operationId of operationIds) {
    assert.equal(typeof sdk[operationId], 'function', `нет экспорта ${operationId}`);
  }

  // И обратная проверка: каждая функция бандла — операция контракта.
  const bundleOperations = Object.keys(sdk).filter((name) => typeof sdk[name] === 'function');
  for (const name of bundleOperations) {
    assert.ok(operationIds.includes(name), `бандл экспортирует неизвестную операцию ${name}`);
  }
});

test('createBooking отправляет POST /bookings по контракту и возвращает данные ответа', async () => {
  const createBookingOp = openapi.paths['/bookings'].post;
  const bookingCreate = openapi.components.schemas.BookingCreate;
  const createdSchema = resolveRef(createBookingOp.responses['201'].content['application/json'].schema);
  const stub = stubFromSchema(createdSchema);

  const body = {
    eventTypeId: 'intro-call',
    start: '2026-10-12T10:00:00+03:00',
    name: 'Иван',
    email: 'ivan@example.com',
    note: null,
  };

  const fetchStub = stubFetch(stub, 201);
  try {
    const result = await sdk.createBooking({ baseUrl, body });
    const [request] = fetchStub.requests;

    assert.equal(fetchStub.requests.length, 1, 'SDK не сделал ровно один запрос');
    assert.equal(request.method, 'POST');
    assert.equal(new URL(request.url).pathname, `${serverUrl}/bookings`, 'путь не относительно servers[0].url');
    assert.ok(request.url.startsWith(baseUrl), `URL ${request.url} не на базе ${baseUrl}`);
    assert.equal(request.headers.get('content-type'), 'application/json');

    const sent = await request.json();
    for (const field of bookingCreate.required) {
      assert.ok(field in sent, `в теле нет обязательного поля ${field}`);
    }
    for (const field of Object.keys(sent)) {
      assert.ok(field in bookingCreate.properties, `в теле неизвестное поле ${field}`);
    }

    for (const field of createdSchema.required) {
      assert.ok(field in result.data, `в ответе нет обязательного поля ${field}`);
    }
    assert.deepEqual(result.data, stub);
  } finally {
    fetchStub.restore();
  }
});

test('getEventType подставляет id в путь запроса', async () => {
  const eventTypeSchema = openapi.components.schemas.EventType;
  const fetchStub = stubFetch(stubFromSchema(eventTypeSchema));
  try {
    await sdk.getEventType({ baseUrl, path: { id: 'intro-call' } });
    const [request] = fetchStub.requests;

    assert.equal(fetchStub.requests.length, 1, 'SDK не сделал ровно один запрос');
    assert.equal(request.method, 'GET');
    assert.equal(
      new URL(request.url).pathname,
      `${serverUrl}/event-types/intro-call`,
      'id не подставлен в путь',
    );
  } finally {
    fetchStub.restore();
  }
});
