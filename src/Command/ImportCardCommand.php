<?php

namespace App\Command;

use App\Entity\Artist;
use App\Entity\Card;
use App\Repository\ArtistRepository;
use App\Repository\CardRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'import:card',
    description: 'Add a short description for your command',
)]
class ImportCardCommand extends Command
{
    public function __construct(
        private readonly CardRepository         $cardRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface        $logger,
        private array                           $csvHeader = []
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $filepath = __DIR__ . '/../../data/cards.csv';
        $handle = fopen($filepath, 'r');

        $this->logger->info('Importing cards from ' . $filepath);
        $startTime = microtime(true);

        if ($handle === false) {
            $this->logger->error('File not found');
            $io->error('File not found');
            return Command::FAILURE;
        }

        $i = 0;
        $batchSize = 500; // flush and clear every 500 cards
        $this->csvHeader = fgetcsv($handle);
        while (($row = $this->readCSV($handle)) !== false) {
            $i++;
            $this->addCard($row);

            if (($i % $batchSize) === 0) {
                $this->entityManager->flush();
                $this->entityManager->clear(); // Detaches all objects from Doctrine!
            }

            // if ($i > 10000) {
            // break;
            // }
        }

        // Flush and clear one last time to catch any remaining cards
        $this->entityManager->flush();
        $this->entityManager->clear();

        $endTime = microtime(true);
        $this->logger->info('End importing cards. Duration: ' . ($endTime - $startTime) . ' seconds');

        fclose($handle);
        $io->success('Import completed successfully in ' . ($endTime - $startTime) . ' seconds');

        return Command::SUCCESS;
    }

    private function readCSV(mixed $handle): array|false
    {
        $row = fgetcsv($handle);
        if ($row === false) {
            return false;
        }
        return array_combine($this->csvHeader, $row);
    }

    private function addCard(array $row): Card
    {
        $uuid = $row['uuid'];

        $card = $this->cardRepository->findOneBy(['uuid' => $uuid]);
        if ($card === null) {
            $card = new Card();
            $card->setUuid($uuid);
            $card->setManaValue($row['manaValue']);
            $card->setManaCost($row['manaCost']);
            $card->setName($row['name']);
            $card->setRarity($row['rarity']);
            $card->setSetCode($row['setCode']);
            $card->setSubtype($row['subtypes']);
            $card->setText($row['text']);
            $card->setType($row['type']);
            $this->entityManager->persist($card);
        } else {
            $this->logger->info('Card already exists with UUID: ' . $uuid);
        }
        return $card;
    }
}
