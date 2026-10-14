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
class ScheduleNormalizer implements DenormalizerInterface, NormalizerInterface, DenormalizerAwareInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;
    use CheckArray;
    use ValidatorTrait;
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return $type === \App\Generated\Model\Schedule::class;
    }
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data) && get_class($data) === \App\Generated\Model\Schedule::class;
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $object = new \App\Generated\Model\Schedule();
        if (null === $data || false === \is_array($data)) {
            return $object;
        }
        if (isset($data['$ref']) && !isset($data['type']) && !isset($data['properties']) && !isset($data['allOf'])) {
            return new Reference($data['$ref'], $context['document-origin']);
        }
        if (isset($data['$recursiveRef'])) {
            return new Reference($data['$recursiveRef'], $context['document-origin']);
        }
        if (\array_key_exists('workingDays', $data)) {
            $values = [];
            foreach ($data['workingDays'] as $value) {
                $values[] = $value;
            }
            $object->workingDays = $values;
            unset($data['workingDays']);
        }
        if (\array_key_exists('dayStart', $data)) {
            $object->dayStart = $data['dayStart'];
            unset($data['dayStart']);
        }
        if (\array_key_exists('dayEnd', $data)) {
            $object->dayEnd = $data['dayEnd'];
            unset($data['dayEnd']);
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
        $values = [];
        foreach ($data->workingDays as $value) {
            $values[] = $value;
        }
        $dataArray['workingDays'] = $values;
        $dataArray['dayStart'] = $data->dayStart;
        $dataArray['dayEnd'] = $data->dayEnd;
        foreach ($data->additionalPropertyEntries() as $key => $value_1) {
            if (preg_match('/.*/', (string) $key)) {
                $dataArray[$key] = $value_1;
            }
        }
        return $dataArray;
    }
    public function getSupportedTypes(?string $format = null): array
    {
        return [\App\Generated\Model\Schedule::class => false];
    }
}