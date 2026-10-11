<?php

namespace App\Generated\Model;

use App\Generated\Runtime\AdditionalAndPatternProperties;
use App\Generated\Runtime\AdditionalPropertiesInterface;
class Problem implements AdditionalPropertiesInterface
{
    use AdditionalAndPatternProperties;
    /**
     * URI-идентификатор типа проблемы.
     *
     * @var string
     */
    public string $type;
    /**
     * @var string
     */
    public string $title;
    /**
     * @var int
     */
    public int $status;
    /**
     * @var string
     */
    public string $detail;
    public function definedProperties(): array
    {
        return ['type' => 'type', 'title' => 'title', 'status' => 'status', 'detail' => 'detail'];
    }
}