<?php

namespace App\Generated\Endpoint;

class GetEventType extends \App\Generated\Runtime\Client\BaseEndpoint implements \App\Generated\Runtime\Client\Endpoint
{
    protected $id;
    protected $accept;
    /**
     * Детали типа события.
     * @param string $id
     * @param array $accept Accept content header application/json|application/problem+json
     */
    public function __construct(string $id, array $accept = [])
    {
        $this->id = $id;
        $this->accept = $accept;
    }
    use \App\Generated\Runtime\Client\EndpointTrait;
    public function getMethod(): string
    {
        return 'GET';
    }
    public function getUri(): string
    {
        return str_replace(['{id}'], [rawurlencode($this->id)], '/event-types/{id}');
    }
    public function getBody(\Symfony\Component\Serializer\SerializerInterface $serializer): array
    {
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
     * @throws \App\Generated\Exception\GetEventTypeNotFoundException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\EventType
     */
    protected function transformResponseBody(\Symfony\Contracts\HttpClient\ResponseInterface $response, \Symfony\Component\Serializer\SerializerInterface $serializer, ?string $contentType = null)
    {
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($contentType !== null && (200 === $status && stripos(strtolower($contentType), 'application/json') !== false)) {
            return $serializer->deserialize($body, 'App\Generated\Model\EventType', 'json');
        }
        if ($contentType !== null && (404 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\GetEventTypeNotFoundException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        throw new \App\Generated\Exception\BadResponseException($status, $body, $response);
    }
    public function getAuthenticationScopes(): array
    {
        return [];
    }
    public function getFetchMode(): string
    {
        return \Jane\Component\OpenApiRuntime\Client\FetchMode::Lazy->value;
    }
    public function getTargetClass(): ?string
    {
        return \App\Generated\Model\EventType::class;
    }
}