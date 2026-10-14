<?php

namespace App\Generated\Endpoint;

class UpdateSchedule extends \App\Generated\Runtime\Client\BaseEndpoint implements \App\Generated\Runtime\Client\Endpoint
{
    protected $accept;
    /**
     * Обновление расписания Хостом: рабочие дни недели плюс единые часы для всех рабочих дней. 422 — конец рабочего дня не позже начала. Уже созданные записи не изменяются.
     * @param \App\Generated\Model\Schedule $requestBody
     * @param array $accept Accept content header application/json|application/problem+json
     */
    public function __construct(\App\Generated\Model\Schedule $requestBody, array $accept = [])
    {
        $this->body = $requestBody;
        $this->accept = $accept;
    }
    use \App\Generated\Runtime\Client\EndpointTrait;
    public function getMethod(): string
    {
        return 'PUT';
    }
    public function getUri(): string
    {
        return '/schedule';
    }
    public function getBody(\Symfony\Component\Serializer\SerializerInterface $serializer): array
    {
        if ($this->body instanceof \App\Generated\Model\Schedule) {
            return [['Content-Type' => ['application/json']], \App\Generated\Runtime\Client\JsonPayload::encode($serializer, $this->body)];
        }
        return [[], null];
    }
    public function getExtraHeaders(): array
    {
        if (empty($this->accept)) {
            return ['Accept' => ['application/json', 'application/problem+json']];
        }
        return $this->accept;
    }
    /**
     * {@inheritdoc}
     *
     * @throws \App\Generated\Exception\UpdateScheduleBadRequestException
     * @throws \App\Generated\Exception\UpdateScheduleUnprocessableEntityException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\Schedule
     */
    protected function transformResponseBody(\Symfony\Contracts\HttpClient\ResponseInterface $response, \Symfony\Component\Serializer\SerializerInterface $serializer, ?string $contentType = null)
    {
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($contentType !== null && (200 === $status && stripos(strtolower($contentType), 'application/json') !== false)) {
            return $serializer->deserialize($body, 'App\Generated\Model\Schedule', 'json');
        }
        if ($contentType !== null && (400 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\UpdateScheduleBadRequestException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        if ($contentType !== null && (422 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\UpdateScheduleUnprocessableEntityException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        throw new \App\Generated\Exception\BadResponseException($status, $body, $response);
    }
    public function getAuthenticationScopes(): array
    {
        return [];
    }
    public function getFetchMode(): string
    {
        return \Jane\Component\OpenApiRuntime\Client\FetchMode::Eager->value;
    }
    public function getTargetClass(): ?string
    {
        return null;
    }
}