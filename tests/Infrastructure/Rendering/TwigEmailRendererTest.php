<?php

declare(strict_types=1);

namespace App\Tests\Infrastructure\Rendering;

use App\Domain\PermanentErrorException;
use App\Infrastructure\Rendering\TwigEmailRenderer;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;

final class TwigEmailRendererTest extends TestCase
{
    private TwigEmailRenderer $renderer;

    protected function setUp(): void
    {
        $loader = new FilesystemLoader(__DIR__ . '/../../../templates');
        $twig = new Environment($loader);
        $this->renderer = new TwigEmailRenderer($twig);
    }

    public function testRenderProducesHtmlWithJournalistNameAndLink(): void
    {
        $html = $this->renderer->render(
            'Carlos Garcia',
            'Match report: Real Madrid 3-1 Barcelona',
            'https://example.com/match-report',
        );

        $this->assertStringContainsString('Carlos Garcia', $html);
        $this->assertStringContainsString('Match report: Real Madrid 3-1 Barcelona', $html);
        $this->assertStringContainsString('https://example.com/match-report', $html);
        $this->assertStringContainsString('<a href=', $html);
    }

    public function testRenderThrowsPermanentErrorOnBadTemplate(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willThrowException(new \RuntimeException('syntax error'));
        $renderer = new TwigEmailRenderer($twig);

        $this->expectException(PermanentErrorException::class);
        $this->expectExceptionMessage('Failed to render email template');

        $renderer->render('Test', 'Title', 'https://example.com');
    }
}
