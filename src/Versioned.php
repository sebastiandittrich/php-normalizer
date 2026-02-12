<?php

namespace SebastianDittrich\Normalizer;

interface Versioned
{
    public static function applyVersion(int $version, array $data): array;
}
