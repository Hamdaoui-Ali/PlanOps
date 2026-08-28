<?php

namespace App\Domain\Labels\Rules;

final class NormalizedLabelName
{
    public function displayName(string $name): string
    {
        return (string) str($name)->squish();
    }

    public function normalize(string $name): string
    {
        return (string) str($this->displayName($name))->lower();
    }
}
