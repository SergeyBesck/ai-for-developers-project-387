<?php

namespace App\Generated;

class Client extends \App\Generated\Runtime\Client\Client
{
    /**
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\BookingsResponse
     */
    public function listBookings()
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\ListBookings());
    }
    /**
     * Создание записи Гостем на свободный слот. 404 — тип события не найден.
     * @param \App\Generated\Model\BookingCreate $requestBody
     * @param array $accept Accept content header application/json|application/problem+json
     * @throws \App\Generated\Exception\CreateBookingBadRequestException
     * @throws \App\Generated\Exception\CreateBookingNotFoundException
     * @throws \App\Generated\Exception\CreateBookingConflictException
     * @throws \App\Generated\Exception\CreateBookingUnprocessableEntityException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\Booking
     */
    public function createBooking(\App\Generated\Model\BookingCreate $requestBody, array $accept = [])
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\CreateBooking($requestBody, $accept));
    }
    /**
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\EventType[]
     */
    public function listEventTypes()
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\ListEventTypes());
    }
    /**
     * Создание типа события Хостом.
     * @param \App\Generated\Model\EventTypeCreate $requestBody
     * @param array $accept Accept content header application/json|application/problem+json
     * @throws \App\Generated\Exception\CreateEventTypeBadRequestException
     * @throws \App\Generated\Exception\CreateEventTypeConflictException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\EventType
     */
    public function createEventType(\App\Generated\Model\EventTypeCreate $requestBody, array $accept = [])
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\CreateEventType($requestBody, $accept));
    }
    /**
     * Детали типа события.
     * @param string $id
     * @param array $accept Accept content header application/json|application/problem+json
     * @throws \App\Generated\Exception\GetEventTypeNotFoundException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\EventType
     */
    public function getEventType(string $id, array $accept = [])
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\GetEventType($id, $accept));
    }
    /**
     * Слоты на окно записи (от текущего момента, округлённого вверх до 15 минут, до конца 14-го календарного дня в поясе приложения).
     * @param string $id
     * @param array $accept Accept content header application/json|application/problem+json
     * @throws \App\Generated\Exception\ListSlotsNotFoundException
     * @throws \App\Generated\Exception\BadResponseException
     *
     * @return \App\Generated\Model\SlotsResponse
     */
    public function listSlots(string $id, array $accept = [])
    {
        return $this->executeEndpoint(new \App\Generated\Endpoint\ListSlots($id, $accept));
    }
    /**
     * @param list<callable(\Symfony\Contracts\HttpClient\HttpClientInterface): \Symfony\Contracts\HttpClient\HttpClientInterface> $additionalPlugins HttpClientInterface decorator factories, applied left-to-right after the server URL decorator
     * @param list<\Symfony\Component\Serializer\Normalizer\NormalizerInterface|\Symfony\Component\Serializer\Normalizer\DenormalizerInterface> $additionalNormalizers
     */
    public static function create(?\Symfony\Contracts\HttpClient\HttpClientInterface $httpClient = null, array $additionalPlugins = [], array $additionalNormalizers = [], bool $applyServerPlugins = true)
    {
        $plugins = [];
        if (null === $httpClient) {
            $httpClient = \Symfony\Component\HttpClient\HttpClient::create();
        }
        if ($applyServerPlugins) {
            $plugins[] = new \Jane\Component\OpenApiRuntime\Client\Plugin\ServerUrlHttpClient('/api');
        }
        if (count($additionalPlugins) > 0) {
            $plugins = array_merge($plugins, $additionalPlugins);
        }
        foreach ($plugins as $plugin) {
            $httpClient = $plugin($httpClient);
        }
        $normalizers = [new \Symfony\Component\Serializer\Normalizer\ArrayDenormalizer(), new \App\Generated\Normalizer\JaneObjectNormalizer()];
        if (count($additionalNormalizers) > 0) {
            $normalizers = array_merge($normalizers, $additionalNormalizers);
        }
        $serializer = new \Symfony\Component\Serializer\Serializer($normalizers, [new \Symfony\Component\Serializer\Encoder\JsonEncoder(new \Symfony\Component\Serializer\Encoder\JsonEncode(), new \Symfony\Component\Serializer\Encoder\JsonDecode(['json_decode_associative' => true])), new \App\Generated\Runtime\Client\FormEncoder()]);
        return new static($httpClient, $serializer);
    }
}