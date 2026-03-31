<?php

declare(strict_types=1);

namespace App\Infrastructure\Command;

use App\Application\Service\CampaignReconciler;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:reconcile-campaigns',
    description: 'Reconcile campaigns stuck in sending state',
)]
final class ReconcileCampaignsCommand extends Command
{
    public function __construct(
        private readonly CampaignReconciler $reconciler,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $count = $this->reconciler->reconcile();
        $output->writeln("<info>Reconciled {$count} campaign(s).</info>");

        return Command::SUCCESS;
    }
}
