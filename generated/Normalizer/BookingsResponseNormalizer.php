<?php

namespace App\Generated\Normalizer;

use Jane\Component\JsonSchemaRuntime\Reference;
use App\Generated\Runtime\Normalizer\CheckArray;
use App\Generated\Runtime\Normalizer\ValidatorTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
class BookingsResponseNormalizer implements DenormalizerInterface, NormalizerInterface, DenormalizerAwareInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;
    use CheckArray;
    use ValidatorTrait;
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === \App\Generated\Model\BookingsResponse::class;
    }
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data) && get_class($data) === \App\Generated\Model\BookingsResponse::class;
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $object = new \App\Generated\Model\BookingsResponse();
        if (null === $data || false === \is_array($data)) {
            return $object;
        }
        if (isset($data['$ref']) && !isset($data['type']) && !isset($data['properties']) && !isset($data['allOf'])) {
            return new Reference($data['$ref'], $context['document-origin']);
        }
        if (isset($data['$recursiveRef'])) {
            return new Reference($data['$recursiveRef'], $context['document-origin']);
        }
        if (\array_key_exists('timezone', $data)) {
            $object->timezone = $data['timezone'];
            unset($data['timezone']);
        }
        if (\array_key_exists('bookings', $data)) {
            $values = [];
            foreach ($data['bookings'] as $value) {
                $values[] = $this->denormalizer->denormalize($value, \App\Generated\Model\Booking::class, 'json', $context);
            }
            $object->bookings = $values;
            unset($data['bookings']);
        }
        foreach ($data as $key => $value_1) {
            if (preg_match('/.*/', (string) $key)) {
                $object[$key] = $value_1;
            }
        }
        return $object;
    }
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $dataArray = [];
        $dataArray['timezone'] = $data->timezone;
        $values = [];
        foreach ($data->bookings as $value) {
            $normalized = $value === null ? null : $this->normalizer->normalize($value, 'json', $context);
            $values[] = \is_iterable($normalized) ? new \App\Generated\Runtime\JsonObject($normalized) : $normalized;
        }
        $dataArray['bookings'] = $values;
        foreach ($data->additionalPropertyEntries() as $key => $value_1) {
            if (preg_match('/.*/', (string) $key)) {
                $dataArray[$key] = $value_1;
            }
        }
        return $dataArray;
    }
    public function getSupportedTypes(?string $format = null): array
    {
        return [\App\Generated\Model\BookingsResponse::class => false];
    }
}