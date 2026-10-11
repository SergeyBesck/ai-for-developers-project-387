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
class BookingNormalizer implements DenormalizerInterface, NormalizerInterface, DenormalizerAwareInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;
    use CheckArray;
    use ValidatorTrait;
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === \App\Generated\Model\Booking::class;
    }
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data) && get_class($data) === \App\Generated\Model\Booking::class;
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $object = new \App\Generated\Model\Booking();
        if (null === $data || false === \is_array($data)) {
            return $object;
        }
        if (isset($data['$ref']) && !isset($data['type']) && !isset($data['properties']) && !isset($data['allOf'])) {
            return new Reference($data['$ref'], $context['document-origin']);
        }
        if (isset($data['$recursiveRef'])) {
            return new Reference($data['$recursiveRef'], $context['document-origin']);
        }
        if (\array_key_exists('id', $data)) {
            $object->id = $data['id'];
            unset($data['id']);
        }
        if (\array_key_exists('eventTypeId', $data)) {
            $object->eventTypeId = $data['eventTypeId'];
            unset($data['eventTypeId']);
        }
        if (\array_key_exists('eventTypeName', $data)) {
            $object->eventTypeName = $data['eventTypeName'];
            unset($data['eventTypeName']);
        }
        if (\array_key_exists('start', $data)) {
            $date = \DateTime::createFromFormat('Y-m-d\TH:i:sP', $data['start']);
            if (false === $date) {
                if (is_string($data['start']) and 1 === preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d+)?(?:Z|[+-]\d{2}:\d{2})$/', $data['start'])) {
                    try {
                        $date = new \DateTime($data['start']);
                    } catch (\Exception) {
                        throw new \App\Generated\Runtime\Normalizer\InvalidDateException($data['start'], 'Y-m-d\TH:i:sP');
                    }
                } else {
                    throw new \App\Generated\Runtime\Normalizer\InvalidDateException($data['start'], 'Y-m-d\TH:i:sP');
                }
            }
            $object->start = $date;
            unset($data['start']);
        }
        if (\array_key_exists('durationMinutes', $data)) {
            $object->durationMinutes = $data['durationMinutes'];
            unset($data['durationMinutes']);
        }
        if (\array_key_exists('name', $data)) {
            $object->name = $data['name'];
            unset($data['name']);
        }
        if (\array_key_exists('email', $data)) {
            $object->email = $data['email'];
            unset($data['email']);
        }
        if (\array_key_exists('note', $data) && $data['note'] !== null) {
            $object->note = $data['note'];
            unset($data['note']);
        }
        elseif (\array_key_exists('note', $data)) {
            $object->note = null;
            unset($data['note']);
        }
        foreach ($data as $key => $value) {
            if (preg_match('/.*/', (string) $key)) {
                $object[$key] = $value;
            }
        }
        return $object;
    }
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $dataArray = [];
        $dataArray['id'] = $data->id;
        $dataArray['eventTypeId'] = $data->eventTypeId;
        $dataArray['eventTypeName'] = $data->eventTypeName;
        $dataArray['start'] = $data->start->format('Y-m-d\TH:i:sP');
        $dataArray['durationMinutes'] = $data->durationMinutes;
        $dataArray['name'] = $data->name;
        $dataArray['email'] = $data->email;
        $dataArray['note'] = $data->note;
        foreach ($data->additionalPropertyEntries() as $key => $value) {
            if (preg_match('/.*/', (string) $key)) {
                $dataArray[$key] = $value;
            }
        }
        return $dataArray;
    }
    public function getSupportedTypes(?string $format = null): array
    {
        return [\App\Generated\Model\Booking::class => false];
    }
}