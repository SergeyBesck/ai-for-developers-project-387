<?php

namespace App\Generated\Normalizer;

use App\Generated\Runtime\Normalizer\CheckArray;
use App\Generated\Runtime\Normalizer\ValidatorTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\DenormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
class JaneObjectNormalizer implements DenormalizerInterface, NormalizerInterface, DenormalizerAwareInterface, NormalizerAwareInterface
{
    use DenormalizerAwareTrait;
    use NormalizerAwareTrait;
    use CheckArray;
    use ValidatorTrait;
    protected $normalizers = [
        
        \App\Generated\Model\Booking::class => \App\Generated\Normalizer\BookingNormalizer::class,
        
        \App\Generated\Model\BookingCreate::class => \App\Generated\Normalizer\BookingCreateNormalizer::class,
        
        \App\Generated\Model\BookingsResponse::class => \App\Generated\Normalizer\BookingsResponseNormalizer::class,
        
        \App\Generated\Model\EventType::class => \App\Generated\Normalizer\EventTypeNormalizer::class,
        
        \App\Generated\Model\EventTypeCreate::class => \App\Generated\Normalizer\EventTypeCreateNormalizer::class,
        
        \App\Generated\Model\Problem::class => \App\Generated\Normalizer\ProblemNormalizer::class,
        
        \App\Generated\Model\Slot::class => \App\Generated\Normalizer\SlotNormalizer::class,
        
        \App\Generated\Model\SlotsResponse::class => \App\Generated\Normalizer\SlotsResponseNormalizer::class,
        
        \Jane\Component\JsonSchemaRuntime\Reference::class => \App\Generated\Runtime\Normalizer\ReferenceNormalizer::class,
    ], $normalizersCache = [];
    public function supportsDenormalization(mixed $data, string $type, ?string $format = null, array $context = []): bool
    {
        return array_key_exists($type, $this->normalizers);
    }
    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return is_object($data) && array_key_exists(get_class($data), $this->normalizers);
    }
    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $normalizerClass = $this->normalizers[get_class($data)];
        $normalizer = $this->getNormalizer($normalizerClass);
        return $normalizer->normalize($data, $format, $context);
    }
    public function denormalize(mixed $data, string $type, ?string $format = null, array $context = []): mixed
    {
        $denormalizerClass = $this->normalizers[$type];
        $denormalizer = $this->getNormalizer($denormalizerClass);
        return $denormalizer->denormalize($data, $type, $format, $context);
    }
    private function getNormalizer(string $normalizerClass)
    {
        return $this->normalizersCache[$normalizerClass] ?? $this->initNormalizer($normalizerClass);
    }
    private function initNormalizer(string $normalizerClass)
    {
        $normalizer = match ($normalizerClass) {
            \App\Generated\Normalizer\BookingNormalizer::class => new \App\Generated\Normalizer\BookingNormalizer(),
            \App\Generated\Normalizer\BookingCreateNormalizer::class => new \App\Generated\Normalizer\BookingCreateNormalizer(),
            \App\Generated\Normalizer\BookingsResponseNormalizer::class => new \App\Generated\Normalizer\BookingsResponseNormalizer(),
            \App\Generated\Normalizer\EventTypeNormalizer::class => new \App\Generated\Normalizer\EventTypeNormalizer(),
            \App\Generated\Normalizer\EventTypeCreateNormalizer::class => new \App\Generated\Normalizer\EventTypeCreateNormalizer(),
            \App\Generated\Normalizer\ProblemNormalizer::class => new \App\Generated\Normalizer\ProblemNormalizer(),
            \App\Generated\Normalizer\SlotNormalizer::class => new \App\Generated\Normalizer\SlotNormalizer(),
            \App\Generated\Normalizer\SlotsResponseNormalizer::class => new \App\Generated\Normalizer\SlotsResponseNormalizer(),
            \App\Generated\Runtime\Normalizer\ReferenceNormalizer::class => new \App\Generated\Runtime\Normalizer\ReferenceNormalizer(),
            default => throw new \InvalidArgumentException('Unknown normalizer class: ' . $normalizerClass),
        };
        if ($normalizer instanceof \Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface) {
            $normalizer->setNormalizer($this->normalizer);
        }
        if ($normalizer instanceof \Symfony\Component\Serializer\Normalizer\DenormalizerAwareInterface) {
            $normalizer->setDenormalizer($this->denormalizer);
        }
        $this->normalizersCache[$normalizerClass] = $normalizer;
        return $normalizer;
    }
    public function getSupportedTypes(?string $format = null): array
    {
        return array_combine(array_keys($this->normalizers), array_fill(0, count($this->normalizers), false));
    }
}