// Конфиг @hey-api/openapi-ts. Вызывать только с явным -f (автопоиск конфига падает багом в 0.99.0).
export default {
  input: './openapi.json',
  output: { path: './generated/sdk' },
};
