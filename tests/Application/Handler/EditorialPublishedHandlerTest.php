<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Handler\EditorialPublishedHandler;
use App\Application\Message\EditorialPublished;
use App\Application\Message\SendCampaign;
use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\EditorialData;
use App\Domain\EditorialServiceInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DelayStamp;

final class EditorialPublishedHandlerTest extends TestCase
{
    public function testCreatesCampaignAndDispatchesSendCampaign(): void
    {
        $repository = $this->createMock(CampaignRepositoryInterface::class);
        $repository->method('findScheduledByEditorialId')->willReturn(null);

        $savedCampaign = null;
        $repository->method('save')->willReturnCallback(function (Campaign $c) use (&$savedCampaign) {
            $savedCampaign = $c;
        });

        $editorialClient = $this->createMock(EditorialServiceInterface::class);
        $editorialClient->method('getEditorial')->willReturn(new EditorialData(
            title: 'Test article',
            url: 'https://example.com/test',
            scheduledAt: new \DateTimeImmutable('now'),
            journalistId: 'j-1',
            isPublished: true,
        ));

        $dispatchedMessage = null;
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($message, $stamps = []) use (&$dispatchedMessage) {
            $dispatchedMessage = $message;
            return new Envelope($message, $stamps);
        });

        $handler = new EditorialPublishedHandler($repository, $editorialClient, $bus, new NullLogger());
        $handler(new EditorialPublished('ed-123'));

        $this->assertNotNull($savedCampaign);
        $this->assertSame('ed-123', $savedCampaign->getEditorialId());
        $this->assertInstanceOf(SendCampaign::class, $dispatchedMessage);
    }

    public function testIgnoresDuplicateEvent(): void
    {
        $existingCampaign = new Campaign('ed-123', new \DateTimeImmutable());

        $repository = $this->createMock(CampaignRepositoryInterface::class);
        $repository->method('findScheduledByEditorialId')->willReturn($existingCampaign);
        $repository->expects($this->never())->method('save');

        $editorialClient = $this->createMock(EditorialServiceInterface::class);
        $editorialClient->expects($this->never())->method('getEditorial');

        $bus = $this->createMock(MessageBusInterface::class);
        $bus->expects($this->never())->method('dispatch');

        $handler = new EditorialPublishedHandler($repository, $editorialClient, $bus, new NullLogger());
        $handler(new EditorialPublished('ed-123'));
    }

    public function testDispatchesWithDelayForFuturePublication(): void
    {
        $repository = $this->createMock(CampaignRepositoryInterface::class);
        $repository->method('findScheduledByEditorialId')->willReturn(null);
        $repository->method('save');

        $futureDate = new \DateTimeImmutable('+1 hour');
        $editorialClient = $this->createMock(EditorialServiceInterface::class);
        $editorialClient->method('getEditorial')->willReturn(new EditorialData(
            title: 'Future article',
            url: 'https://example.com/future',
            scheduledAt: $futureDate,
            journalistId: 'j-1',
            isPublished: true,
        ));

        $capturedStamps = [];
        $bus = $this->createMock(MessageBusInterface::class);
        $bus->method('dispatch')->willReturnCallback(function ($message, $stamps = []) use (&$capturedStamps) {
            $capturedStamps = $stamps;
            return new Envelope($message, $stamps);
        });

        $handler = new EditorialPublishedHandler($repository, $editorialClient, $bus, new NullLogger());
        $handler(new EditorialPublished('ed-future'));

        $this->assertCount(1, $capturedStamps);
        $this->assertInstanceOf(DelayStamp::class, $capturedStamps[0]);
        $this->assertGreaterThan(3_500_000, $capturedStamps[0]->getDelay()); // ~1 hour in ms
    }
}
