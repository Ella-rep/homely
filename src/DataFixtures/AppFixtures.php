<?php

namespace App\DataFixtures;

use App\Entity\Foyer;
use App\Entity\Room;
use App\Entity\Task;
use App\Entity\User;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo pour le développement local : quelques pièces avec des tâches
 * dans des états variés (à jour / bientôt / en retard), pour visualiser l'app
 * sans devoir tout ressaisir à la main.
 */
class AppFixtures extends Fixture
{
    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $now = new DateTimeImmutable();

        $foyer = new Foyer();
        $foyer->setName('Foyer de démo');
        $foyer->setInviteCode('DEMO1234');
        $manager->persist($foyer);

        $user = new User();
        $user->setEmail('demo@homely.app');
        $user->setPseudo('Démo');
        $user->setFoyer($foyer);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demodemo'));
        $manager->persist($user);

        $rooms = [
            [
                'name' => 'Cuisine',
                'color' => '#5FA875',
                'tasks' => [
                    ['name' => 'Passer la serpillère', 'frequencyDays' => 4, 'lastDoneDaysAgo' => 5],
                    ['name' => "Passer l'aspirateur", 'frequencyDays' => 7, 'lastDoneDaysAgo' => 6],
                    ['name' => 'Nettoyer les plaques de cuisson', 'frequencyDays' => 7, 'lastDoneDaysAgo' => 2],
                    ['name' => 'Vider la poubelle', 'frequencyDays' => 1, 'lastDoneDaysAgo' => 0],
                    ['name' => 'Nettoyer le réfrigérateur', 'frequencyDays' => 14, 'lastDoneDaysAgo' => 20],
                ],
            ],
            [
                'name' => 'Salle de bain',
                'color' => '#5FA0A0',
                'tasks' => [
                    ['name' => 'Nettoyer la douche', 'frequencyDays' => 4, 'lastDoneDaysAgo' => 4],
                    ['name' => 'Nettoyer les toilettes', 'frequencyDays' => 3, 'lastDoneDaysAgo' => 1],
                    ['name' => 'Changer les serviettes', 'frequencyDays' => 4, 'lastDoneDaysAgo' => 3],
                ],
            ],
            [
                'name' => 'Chambre',
                'color' => '#6A7FC7',
                'tasks' => [
                    ['name' => 'Changer les draps', 'frequencyDays' => 14, 'lastDoneDaysAgo' => 10],
                    ['name' => 'Passer l\'aspirateur', 'frequencyDays' => 7, 'lastDoneDaysAgo' => 3],
                ],
            ],
            [
                'name' => 'Salon',
                'color' => '#6FA0D8',
                'tasks' => [
                    ['name' => "Passer l'aspirateur", 'frequencyDays' => 4, 'lastDoneDaysAgo' => 1],
                    ['name' => 'Faire la poussière', 'frequencyDays' => 5, 'lastDoneDaysAgo' => 6],
                    ['name' => 'Nettoyer les vitres', 'frequencyDays' => 21, 'lastDoneDaysAgo' => 25],
                ],
            ],
        ];

        foreach ($rooms as $position => $roomData) {
            $room = new Room();
            $room->setName($roomData['name']);
            $room->setColor($roomData['color']);
            $room->setPosition($position);
            $room->setFoyer($foyer);
            $manager->persist($room);

            foreach ($roomData['tasks'] as $taskData) {
                $task = new Task();
                $task->setName($taskData['name']);
                $task->setRoom($room);
                $task->setFrequencyDays($taskData['frequencyDays']);
                $task->setLastDoneAt($now->modify(sprintf('-%d days', $taskData['lastDoneDaysAgo'])));
                $manager->persist($task);
            }
        }

        $manager->flush();
    }
}
