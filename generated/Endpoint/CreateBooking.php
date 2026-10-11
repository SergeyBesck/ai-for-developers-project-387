<?php

namespace App\Generated\Endpoint;

class CreateBooking extends \App\Generated\Runtime\Client\BaseEndpoint implements \App\Generated\Runtime\Client\Endpoint
{
    protected $accept;
    /**
     * Создание записи Гостем на свободный слот. 404 — тип события не найден.
     * @param \App\Generated\Model\BookingCreate $requestBody
     * @param array $accept Accept content header application/json|application/problem+json
     */
    public function __construct(\App\Generated\Model\BookingCreate $requestBody, array $accept = [])
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
        return '/bookings';
    }
    public function getBody(\Symfony\Component\Serializer\SerializerInterface $serializer): array
    {
        if ($this->body instanceof \App\Generated\Model\BookingCreate) {
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
     * @throws \App\Generated\Exception\CreateBookingBadRequestException
     * @throws \App\Generated\Exception\CreateBookingNotFoundException
     * @throws \App\Generated\Exception\CreateBookingConflictException
     * @throws \App\Generated\Exception\CreateBookingUnprocessableEntityException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\Booking
     */
    protected function transformResponseBody(\Symfony\Contracts\HttpClient\ResponseInterface $response, \Symfony\Component\Serializer\SerializerInterface $serializer, ?string $contentType = null)
    {
        $status = $response->getStatusCode();
        $body = $response->getContent(false);
        if ($contentType !== null && (201 === $status && stripos(strtolower($contentType), 'application/json') !== false)) {
            return $serializer->deserialize($body, 'App\Generated\Model\Booking', 'json');
        }
        if ($contentType !== null && (400 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateBookingBadRequestException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        if ($contentType !== null && (404 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateBookingNotFoundException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        if ($contentType !== null && (409 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateBookingConflictException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
        }
        if ($contentType !== null && (422 === $status && stripos(strtolower($contentType), 'application/problem+json') !== false)) {
            throw new \App\Generated\Exception\CreateBookingUnprocessableEntityException($serializer->deserialize($body, 'App\Generated\Model\Problem', 'json'), $response);
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