<?php

namespace SebastianDittrich\Normalizer;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;

class AsJsonArray implements CastsAttributes
{
    private Normalizer $normalizer;

    public function __construct(private string $type)
    {
        $this->normalizer = new Normalizer;
    }

    /**
     * Cast the given value.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @return mixed
     */
    public function get($model, string $key, $value, array $attributes)
    {
        if ($value == 'null' || $value === null) {
            return [];
        }

        return $this->normalizer->deserialize($value, $this->type . '[]');
    }

    /**
     * Prepare the given value for storage.
     *
     * @param  \Illuminate\Database\Eloquent\Model  $model
     * @param  mixed  $value
     * @return mixed
     */
    public function set($model, string $key, $value, array $attributes)
    {
        return $this->normalizer->serialize($value);
    }

    public static function itemType(string $class)
    {
        return self::class . ':' . $class;
    }
}
