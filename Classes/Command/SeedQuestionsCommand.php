<?php

declare(strict_types=1);

namespace Pbn\PbnNewskiatlas27Rate\Command;

use Pbn\PbnNewskiatlas27Rate\Domain\Repository\QuestionRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * Creates the initial question set for ski resorts on the given storage page.
 * Groups that already exist there (same title) are skipped.
 *
 *   vendor/bin/typo3 pbnrate:seed --pid=123
 */
class SeedQuestionsCommand extends Command
{
    /**
     * @var array<int, array{title: string, description: string, questions: array}>
     */
    protected $defaults = array(
        array(
            'title' => 'Ogólna ocena z Twojego wyjazdu',
            'description' => 'Oceń swój ostatni pobyt w ośrodku w skali od 1 do 5 gwiazdek.',
            'questions' => array(
                array('title' => 'Ocena ogólna', 'description' => '', 'required' => 1, 'is_summary' => 1),
                array('title' => 'Różnorodność tras', 'description' => '', 'required' => 0, 'is_summary' => 0),
                array('title' => 'Wyciągi – nowoczesność', 'description' => '', 'required' => 0, 'is_summary' => 0),
                array('title' => 'Wyciągi – obłożenie', 'description' => '5 gwiazdek = brak kolejek, 1 gwiazdka = bardzo długie kolejki', 'required' => 0, 'is_summary' => 0),
                array('title' => 'Przygotowanie tras', 'description' => '', 'required' => 0, 'is_summary' => 0),
            ),
        ),
        array(
            'title' => 'Dla kogo jest ten ośrodek',
            'description' => 'Na ile ośrodek pasuje do danej grupy: 1 – słabo, 5 – idealnie.',
            'questions' => array(
                array('title' => 'Dla rodzin z dziećmi', 'description' => '', 'required' => 0, 'is_summary' => 0),
                array('title' => 'Dla wszystkich', 'description' => '', 'required' => 0, 'is_summary' => 0),
                array('title' => 'Dla dobrych narciarzy', 'description' => '', 'required' => 0, 'is_summary' => 0),
            ),
        ),
    );

    protected function configure()
    {
        $this
            ->setDescription('Creates the default ski resort rating questions.')
            ->addOption('pid', null, InputOption::VALUE_REQUIRED, 'Storage page (sysfolder) uid');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $io = new SymfonyStyle($input, $output);
        $pid = (int)$input->getOption('pid');
        if ($pid <= 0) {
            $io->error('Please pass --pid=<storage page uid>.');

            return 1;
        }

        $pool = GeneralUtility::makeInstance(ConnectionPool::class);
        $groupConnection = $pool->getConnectionForTable(QuestionRepository::GROUP_TABLE);
        $questionConnection = $pool->getConnectionForTable(QuestionRepository::QUESTION_TABLE);
        $now = time();

        foreach ($this->defaults as $groupSorting => $group) {
            $existing = (int)$groupConnection->count('uid', QuestionRepository::GROUP_TABLE, array(
                'pid' => $pid,
                'title' => $group['title'],
                'deleted' => 0,
            ));
            if ($existing > 0) {
                $io->writeln(sprintf('Skipped existing group "%s".', $group['title']));
                continue;
            }

            $groupConnection->insert(QuestionRepository::GROUP_TABLE, array(
                'pid' => $pid,
                'tstamp' => $now,
                'crdate' => $now,
                'sorting' => ($groupSorting + 1) * 256,
                'title' => $group['title'],
                'description' => $group['description'],
                'questions' => count($group['questions']),
            ));
            $groupUid = (int)$groupConnection->lastInsertId(QuestionRepository::GROUP_TABLE);

            foreach ($group['questions'] as $questionSorting => $question) {
                $questionConnection->insert(QuestionRepository::QUESTION_TABLE, array(
                    'pid' => $pid,
                    'tstamp' => $now,
                    'crdate' => $now,
                    'sorting' => ($questionSorting + 1) * 256,
                    'questiongroup' => $groupUid,
                    'title' => $question['title'],
                    'description' => $question['description'],
                    'required' => $question['required'],
                    'is_summary' => $question['is_summary'],
                    'applies_to' => '',
                ));
            }

            $io->writeln(sprintf('Created group "%s" with %d question(s).', $group['title'], count($group['questions'])));
        }

        $io->success('Done.');

        return 0;
    }
}
