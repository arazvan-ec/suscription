<?php

declare(strict_types=1);

namespace App\Domain;

interface EmailRendererInterface
{
    public function render(string $journalistName, string $articleTitle, string $articleUrl): string;
}
