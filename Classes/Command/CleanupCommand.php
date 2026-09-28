<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Command;

use Pbn\PbnNewskiatlas27Rate\Domain\Repository\VoteRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Removes anonymised client hashes after the lock period (GDPR data minimisation).
 * Votes and answers are kept.
 *
 *   vendor/bin/typo3 pbnrate:cleanup --age=259200
 *
 * Can also be scheduled via the "Execute console commands" scheduler task.
 */
class CleanupCommand extends Command
{
    protected function configure()
    {
        $this
            ->setDescription('Removes rating lock hashes older than the lock period.')
            ->addOption('age', null, InputOption::VALUE_REQUIRED, 'Age in seconds; use the same value as settings.lockPeriod', '259200')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only count affected votes');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $age = max(60, (int)$input->getOption('age'));
        $olderThan = time() - $age;
        $repository = GeneralUtility::makeInstance(VoteRepository::class);

        if ($input->getOption('dry-run')) {
            $io->writeln(sprintf('%d vote(s) would be anonymised.', $repository->countIpHashesOlderThan($olderThan)));

            return 0;
        }

        $io->success(sprintf('%d vote(s) anonymised.', $repository->clearIpHashesOlderThan($olderThan)));

        return 0;
    }
}
