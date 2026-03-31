<?php

declare(strict_types=1);

namespace App\Tests\Application\Handler;

use App\Application\Handler\SendCampaignHandler;
use App\Application\Message\SendCampaign;
use App\Application\Service\CampaignProcessorInterface;
use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use App\Domain\ErrorType;
use App\Domain\TransientErrorException;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;

final class SendCampaignHandlerTest extends TestCase
{
    private CampaignRepositoryInterface $repository;
    private CampaignProcessorInterface $processor;
    private MessageBusInterface $bus;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(CampaignRepositoryInterface::class);
        $this->processor = $this->createMock(CampaignProcessorInterface::class);
        $this->bus = $this->createMock(MessageBusInterface::class);
    }

    private function handler(int $maxRetries = 3): SendCampaignHandler
    {
        return new SendCampaignHandler(
            $this->repository,
            $this->processor,
            $this->bus,
            new NullLogger(),
            $maxRetries,
        );
    }

    public function testHappyPathProcessesCampaign(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $this->repository->method('find')->willReturn($campaign);
        $this->processor->expects($this->once())->method('process')->with($campaign);

        $this->handler()(new SendCampaign($campaign->getId()->toRfc4122()));

        $this->assertSame(CampaignStatus::Sending, $campaign->getStatus());
    }

    public function testIgnoresCampaignNotFound(): void
    {
        $this->repository->method('find')->willReturn(null);
        $this->processor->expects($this->never())->method('process');

        $this->handler()(new SendCampaign('nonexistent-id'));
    }

    public function testIgnoresNonScheduledCampaign(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->cancel('test');
        $this->repository->method('find')->willReturn($campaign);
        $this->processor->expects($this->never())->method('process');

        $this->handler()(new SendCampaign($campaign->getId()->toRfc4122()));
    }

    public function testRetriesOnTransientError(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $this->repository->method('find')->willReturn($campaign);
        $this->processor->method('process')->willThrowException(new TransientErrorException('Mailchimp 500'));

        $this->bus->expects($this->once())->method('dispatch')
            ->willReturnCallback(fn ($msg) => new Envelope($msg));

        $this->handler()(new SendCampaign($campaign->getId()->toRfc4122()));

        $this->assertSame(CampaignStatus::Scheduled, $campaign->getStatus());
        $this->assertSame(1, $campaign->getRetryCount());
    }

    public function testFailsAfterMaxRetries(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->incrementRetry();
        $campaign->incrementRetry();
        // retry_count = 2, one more and it hits max of 3

        $this->repository->method('find')->willReturn($campaign);
        $this->processor->method('process')->willThrowException(new TransientErrorException('Mailchimp 500'));
        $this->bus->expects($this->never())->method('dispatch');

        $this->handler()(new SendCampaign($campaign->getId()->toRfc4122()));

        $this->assertSame(CampaignStatus::Failed, $campaign->getStatus());
        $this->assertSame(ErrorType::Transient, $campaign->getErrorType());
        $this->assertSame(3, $campaign->getRetryCount());
    }
}
