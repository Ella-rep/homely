<?php

namespace App\Command;

use App\Entity\Foyer;
use App\Entity\Room;
use App\Entity\RoomTask;
use App\Entity\RoomType;
use App\Entity\Task;
use App\Entity\TaskLog;
use App\Entity\User;
use App\Service\RoomCatalog;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:load-demo-data',
    description: 'Vide foyers/pièces/tâches planifiées (le catalogue room_type/task est conservé) et recrée des données de démo (statuts clean / due_soon / overdue).',
)]
final class LoadDemoDataCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $connection = $this->entityManager->getConnection();
        $connection->executeStatement('DELETE FROM task_log');
        $connection->executeStatement('DELETE FROM room_task');
        $connection->executeStatement('DELETE FROM room');
        $connection->executeStatement('DELETE FROM "user"');
        $connection->executeStatement('DELETE FROM foyer');

        $now = new DateTimeImmutable();

        $foyer = new Foyer();
        $foyer->setName('Foyer de démo');
        $foyer->setInviteCode('DEMO1234');
        $this->entityManager->persist($foyer);

        $user = new User();
        $user->setEmail('demo@homely.app');
        $user->setPseudo('Démo');
        $user->setFoyer($foyer);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demodemo'));
        $this->entityManager->persist($user);

        // [type de pièce => [libellé de tâche du catalogue, fréquence (jours), faite il y a N jours]]
        $rooms = [
            'Cuisine' => [
                ['Essuyer le comptoir', 2, 1],
                ['Nettoyer le four', 30, 40],
                ['Organiser le réfrigérateur', 14, 10],
                ['Nettoyer la plaque', 7, 0],
            ],
            'Salle de bain' => [
                ['Nettoyer la baignoire', 7, 6],
                ['Nettoyer les toilettes', 5, 6],
                ['Changer les serviettes', 7, 2],
            ],
            'Salon' => [
                ['Aspirateur', 7, 3],
                ['Faire la poussière', 10, 4],
                ['Ranger', 3, 1],
            ],
            'Chambre' => [
                ['Changer les draps', 14, 12],
                ['Aspirateur', 7, 1],
            ],
        ];

        $roomCount = 0;
        $taskCount = 0;
        foreach ($rooms as $typeLabel => $tasks) {
            $roomType = $this->entityManager->getRepository(RoomType::class)->findOneBy(['label' => $typeLabel]);
            if (!$roomType instanceof RoomType) {
                $io->error(sprintf('Type de pièce "%s" introuvable : lance d\'abord les migrations.', $typeLabel));

                return Command::FAILURE;
            }

            $room = new Room();
            $room->setLabel($typeLabel);
            $room->setRoomType($roomType);
            $room->setColor(RoomCatalog::colorFor($typeLabel));
            $room->setPosition($roomCount++);
            $room->setFoyer($foyer);
            $this->entityManager->persist($room);

            foreach ($tasks as [$taskLabel, $frequencyDays, $doneDaysAgo]) {
                $task = $this->entityManager->getRepository(Task::class)->findOneBy(['roomType' => $roomType, 'label' => $taskLabel]);
                if (!$task instanceof Task) {
                    $task = (new Task())->setRoomType($roomType)->setLabel($taskLabel)->setDefaultFrequencyDays($frequencyDays);
                    $this->entityManager->persist($task);
                }

                $lastDoneAt = $now->modify(sprintf('-%d days', $doneDaysAgo));

                $roomTask = new RoomTask();
                $roomTask->setRoom($room);
                $roomTask->setTask($task);
                $roomTask->setFrequencyDays($frequencyDays);
                $roomTask->setLastDoneAt($lastDoneAt);
                $this->entityManager->persist($roomTask);

                $log = new TaskLog();
                $log->setDoneAt($lastDoneAt);
                $log->setDoneBy('Démo');
                $roomTask->addLog($log);
                $this->entityManager->persist($log);

                $taskCount++;
            }
        }

        $this->entityManager->flush();

        $io->success(sprintf('Données de démo chargées : 1 foyer (code DEMO1234), 1 utilisateur (demo@homely.app / demodemo), %d pièces, %d tâches.', $roomCount, $taskCount));

        return Command::SUCCESS;
    }
}
