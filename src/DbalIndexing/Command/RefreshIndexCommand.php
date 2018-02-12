<?php declare(strict_types=1);
/**
 * Shopware 5
 * Copyright (c) shopware AG
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Shopware" is a registered trademark of shopware AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

namespace Shopware\DbalIndexing\Command;

use Shopware\DbalIndexing\Event\ProgressAdvancedEvent;
use Shopware\DbalIndexing\Event\ProgressFinishedEvent;
use Shopware\DbalIndexing\Event\ProgressStartedEvent;
use Shopware\DbalIndexing\Indexer\IndexerInterface;
use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RefreshIndexCommand extends ContainerAwareCommand implements EventSubscriberInterface
{
    /**
     * @var SymfonyStyle
     */
    private $io;

    /**
     * @var IndexerInterface
     */
    private $indexer;

    public function __construct(IndexerInterface $indexer)
    {
        parent::__construct('dbal:refresh:index');
        $this->indexer = $indexer;
    }

    public static function getSubscribedEvents()
    {
        return [
            ProgressStartedEvent::NAME => 'startProgress',
            ProgressAdvancedEvent::NAME => 'advanceProgress',
            ProgressFinishedEvent::NAME => 'finishProgress',
        ];
    }

    public function finishProgress(ProgressFinishedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressFinish();
        $this->io->success($event->getMessage());
    }

    public function startProgress(ProgressStartedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->comment($event->getMessage());
        $this->io->progressStart($event->getTotal());
    }

    public function advanceProgress(ProgressAdvancedEvent $event)
    {
        if (!$this->io) {
            return;
        }
        $this->io->progressAdvance($event->getStep());
    }

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        $this
            ->setName('dbal:refresh:index')
            ->setDescription('Refreshs the shop indices');
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->indexer->index(new \DateTime());
    }
}