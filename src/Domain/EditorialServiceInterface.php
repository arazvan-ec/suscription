<?php

declare(strict_types=1);

namespace App\Domain;

interface EditorialServiceInterface
{
    public function getEditorial(string $editorialId): EditorialData;
}
