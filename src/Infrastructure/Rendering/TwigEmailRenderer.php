<?php

declare(strict_types=1);

namespace App\Infrastructure\Rendering;

use App\Domain\PermanentErrorException;
use Twig\Environment;

final class TwigEmailRenderer
{
    public function __construct(
        private readonly Environment $twig,
    ) {
    }

    public function render(string $journalistName, string $articleTitle, string $articleUrl): string
    {
        try {
            return $this->twig->render('email/editorial_notification.html.twig', [
                'journalist_name' => $journalistName,
                'article_title' => $articleTitle,
                'article_url' => $articleUrl,
            ]);
        } catch (\Throwable $e) {
            throw new PermanentErrorException(
                'Failed to render email template: ' . $e->getMessage(),
                'template_error',
                0,
                $e,
            );
        }
    }
}
