<?php

declare(strict_types=1);

namespace App\Tests\Application\Service;

use App\Application\Service\CampaignProcessor;
use App\Domain\Campaign;
use App\Domain\CampaignRepositoryInterface;
use App\Domain\CampaignStatus;
use App\Domain\EditorialData;
use App\Domain\EditorialServiceInterface;
use App\Domain\JournalistData;
use App\Domain\JournalistServiceInterface;
use App\Domain\MailchimpClientInterface;
use App\Domain\TransientErrorException;
use App\Domain\EmailRendererInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class CampaignProcessorTest extends TestCase
{
    private EditorialServiceInterface $editorialService;
    private JournalistServiceInterface $journalistService;
    private MailchimpClientInterface $mailchimpClient;
    private EmailRendererInterface $emailRenderer;
    private CampaignRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->editorialService = $this->createMock(EditorialServiceInterface::class);
        $this->journalistService = $this->createMock(JournalistServiceInterface::class);
        $this->mailchimpClient = $this->createMock(MailchimpClientInterface::class);
        $this->emailRenderer = $this->createMock(EmailRendererInterface::class);
        $this->repository = $this->createMock(CampaignRepositoryInterface::class);
    }

    private function processor(): CampaignProcessor
    {
        return new CampaignProcessor(
            $this->editorialService,
            $this->journalistService,
            $this->mailchimpClient,
            $this->emailRenderer,
            $this->repository,
            new NullLogger(),
        );
    }

    public function testHappyPath(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());

        $this->editorialService->method('getEditorial')->willReturn(new EditorialData(
            'Title', 'https://example.com', new \DateTimeImmutable(), 'j-1', true,
        ));
        $this->journalistService->method('getJournalist')->willReturn(new JournalistData('Carlos', 'aud-1'));
        $this->emailRenderer->method('render')->willReturn('<h1>Hello</h1>');
        $this->mailchimpClient->method('createCampaign')->willReturn('mc-123');
        $this->mailchimpClient->expects($this->once())->method('sendCampaign')->with('mc-123');

        $this->processor()->process($campaign);

        $this->assertSame(CampaignStatus::Done, $campaign->getStatus());
        $this->assertSame('mc-123', $campaign->getMailchimpCampaignId());
    }

    public function testCancelsWhenEditorialNotPublished(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());

        $this->editorialService->method('getEditorial')->willReturn(new EditorialData(
            'Title', 'https://example.com', new \DateTimeImmutable(), 'j-1', false,
        ));
        $this->journalistService->expects($this->never())->method('getJournalist');
        $this->mailchimpClient->expects($this->never())->method('createCampaign');

        $this->processor()->process($campaign);

        $this->assertSame(CampaignStatus::Cancelled, $campaign->getStatus());
    }

    public function testCancelsWhenJournalistHasNoAudience(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());

        $this->editorialService->method('getEditorial')->willReturn(new EditorialData(
            'Title', 'https://example.com', new \DateTimeImmutable(), 'j-1', true,
        ));
        $this->journalistService->method('getJournalist')->willReturn(new JournalistData('Miguel', null));
        $this->mailchimpClient->expects($this->never())->method('createCampaign');

        $this->processor()->process($campaign);

        $this->assertSame(CampaignStatus::Cancelled, $campaign->getStatus());
    }

    public function testPropagatesTransientErrorFromMailchimp(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());

        $this->editorialService->method('getEditorial')->willReturn(new EditorialData(
            'Title', 'https://example.com', new \DateTimeImmutable(), 'j-1', true,
        ));
        $this->journalistService->method('getJournalist')->willReturn(new JournalistData('Carlos', 'aud-1'));
        $this->emailRenderer->method('render')->willReturn('<h1>Hello</h1>');
        $this->mailchimpClient->method('createCampaign')->willReturn('mc-123');
        $this->mailchimpClient->method('sendCampaign')->willThrowException(
            new TransientErrorException('Mailchimp 500'),
        );

        $this->expectException(TransientErrorException::class);

        $this->processor()->process($campaign);
    }

    public function testSkipsMailchimpCreateIfCampaignIdAlreadyExists(): void
    {
        $campaign = new Campaign('ed-1', new \DateTimeImmutable());
        $campaign->setMailchimpCampaignId('mc-existing');

        $this->editorialService->method('getEditorial')->willReturn(new EditorialData(
            'Title', 'https://example.com', new \DateTimeImmutable(), 'j-1', true,
        ));
        $this->journalistService->method('getJournalist')->willReturn(new JournalistData('Carlos', 'aud-1'));
        $this->emailRenderer->method('render')->willReturn('<h1>Hello</h1>');
        $this->mailchimpClient->expects($this->never())->method('createCampaign');
        $this->mailchimpClient->expects($this->once())->method('sendCampaign')->with('mc-existing');

        $this->processor()->process($campaign);

        $this->assertSame(CampaignStatus::Done, $campaign->getStatus());
    }
}
