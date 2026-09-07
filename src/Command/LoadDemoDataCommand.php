<?php

namespace App\Command;

use App\Entity\Foyer;
use App\Entity\Room;
use App\Entity\Task;
use App\Entity\TaskLog;
use App\Entity\User;
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
    description: 'Vide les tables et recrée des pièces/tâches de démo pour tester le rendu (statuts clean / due_soon / overdue).',
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
        $connection->executeStatement('DELETE FROM task');
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

        $rooms = [
            ['name' => 'Cuisine', 'color' => '#F0A868', 'tasks' => [
                ['name' => "Faire la vaisselle", 'frequencyDays' => 1, 'lastDoneDaysAgo' => 0],
                ['name' => 'Nettoyer le plan de travail', 'frequencyDays' => 2, 'lastDoneDaysAgo' => 1],
                ['name' => 'Nettoyer le four', 'frequencyDays' => 30, 'lastDoneDaysAgo' => 40],
                ['name' => 'Vider le frigo', 'frequencyDays' => 14, 'lastDoneDaysAgo' => 10],
            ]],
            ['name' => 'Salle de bain', 'color' => '#6FB3B8', 'tasks' => [
                ['name' => 'Nettoyer la douche', 'frequencyDays' => 7, 'lastDoneDaysAgo' => 6],
                ['name' => 'Nettoyer les toilettes', 'frequencyDays' => 5, 'lastDoneDaysAgo' => 6],
                ['name' => 'Laver les serviettes', 'frequencyDays' => 7, 'lastDoneDaysAgo' => 2],
            ]],
            ['name' => 'Salon', 'color' => '#8A8FD1', 'tasks' => [
                ['name' => "Passer l'aspirateur", 'frequencyDays' => 7, 'lastDoneDaysAgo' => 3],
                ['name' => 'Dépoussiérer', 'frequencyDays' => 10, 'lastDoneDaysAgo' => 4],
                ['name' => 'Ranger', 'frequencyDays' => 3, 'lastDoneDaysAgo' => 1],
            ]],
            ['name' => 'Chambre', 'color' => '#D186A3', 'tasks' => [
                ['name' => 'Changer les draps', 'frequencyDays' => 14, 'lastDoneDaysAgo' => 12],
                ['name' => 'Passer l\'aspirateur', 'frequencyDays' => 7, 'lastDoneDaysAgo' => 1],
            ]],
        ];

        foreach ($rooms as $position => $roomData) {
            $room = new Room();
            $room->setName($roomData['name']);
            $room->setColor($roomData['color']);
            $room->setPosition($position);
            $room->setFoyer($foyer);
            $this->entityManager->persist($room);

            foreach ($roomData['tasks'] as $taskData) {
                $task = new Task();
                $task->setName($taskData['name']);
                $task->setRoom($room);
                $task->setFrequencyDays($taskData['frequencyDays']);

                $lastDoneAt = $now->modify(sprintf('-%d days', $taskData['lastDoneDaysAgo']));
                $task->setLastDoneAt($lastDoneAt);
                $this->entityManager->persist($task);

                $log = new TaskLog();
                $log->setDoneAt($lastDoneAt);
                $log->setDoneBy('Démo');
                $task->addLog($log);
                $this->entityManager->persist($log);
            }
        }

        $this->entityManager->flush();

        $io->success('Données de démo chargées : 1 foyer (code DEMO1234), 1 utilisateur (demo@homely.app / demodemo), 4 pièces, 12 tâches.');

        return Command::SUCCESS;
    }
}
