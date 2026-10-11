<?php

namespace App\Generated\Endpoint;

class CreateEventType extends \App\Generated\Runtime\Client\BaseEndpoint implements \App\Generated\Runtime\Client\Endpoint
{
    protected $accept;
    /**
     * Создание типа события Хостом.
     * @param \App\Generated\Model\EventTypeCreate $requestBody
     * @param array $accept Accept content header application/json|application/problem+json
     */
    public function __construct(\App\Generated\Model\EventTypeCreate $requestBody, array $accept = [])
    {
        $this->body = $requestBody;
        $this->accept = $accept;
    }
    use \App\Generated\Runtime\Client\EndpointTrait;
    public function getMethod(): string
    {
        return 'POST';
    }
    public function getUri(): string
    {
        return '/event-types';
    }
    public function getBody(\Symfony\Component\Serializer\SerializerInterface $serializer): array
    {
        if ($this->body instanceof \App\Generated\Model\EventTypeCreate) {
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
     * @throws \App\Generated\Exception\CreateEventTypeBadRequestException
     * @throws \App\Generated\Exception\CreateEventTypeConflictException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\EventType
     */
    protected function transformResponseBody(\Symfony\Contracts\HttpClient\ResponseInterface $response, \Symfony\Component\Serializer\SerializerInterface $serializer, ?string $contentType = null)
    {
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($contentType !== null && (201 === $status && stripos(strtolower($contentType), 'application/json') !== false)) {
            return $serializer->deserialize($body, 'App\Generated\Model\EventType', 'json');
        }
        if ($contentType !== null && (400 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateEventTypeBadRequestException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        if ($contentType !== null && (409 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateEventTypeConflictException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
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