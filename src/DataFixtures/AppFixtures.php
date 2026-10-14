<?php

namespace App\DataFixtures;

use App\Entity\Foyer;
use App\Entity\Room;
use App\Entity\RoomTask;
use App\Entity\RoomType;
use App\Entity\Task;
use App\Entity\User;
use App\Service\RoomCatalog;
use DateTimeImmutable;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

/**
 * Données de démo pour le développement local : quelques pièces avec des tâches
 * dans des états variés (à jour / bientôt / en retard). Les types de pièce et le
 * catalogue de tâches viennent de la migration ; ils sont créés ici seulement s'ils manquent.
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
        $user->addFoyer($foyer);
        $foyer->setOwner($user);
        $user->setPassword($this->passwordHasher->hashPassword($user, 'demodemo'));
        $manager->persist($user);

        // [type de pièce => [libellé de tâche, fréquence (jours), faite il y a N jours]]
        $rooms = [
            'Cuisine' => [
                ['Serpillère', 4, 5],
                ['Aspirateur', 7, 6],
                ['Nettoyer la plaque', 7, 2],
                ['Essuyer le comptoir', 1, 0],
                ['Nettoyer le réfrigérateur', 14, 20],
            ],
            'Salle de bain' => [
                ['Nettoyer la baignoire', 4, 4],
                ['Nettoyer le lavabo', 3, 1],
                ['Changer les serviettes', 4, 3],
            ],
            'Chambre' => [
                ['Changer les draps', 14, 10],
                ['Aspirateur', 7, 3],
            ],
            'Salon' => [
                ['Aspirateur', 4, 1],
                ['Faire la poussière', 5, 6],
                ['Laver les plaids', 21, 25],
            ],
        ];

        $position = 0;
        foreach ($rooms as $typeLabel => $tasks) {
            $roomType = $this->roomType($manager, $typeLabel);

            $room = new Room();
            $room->setLabel($typeLabel);
            $room->setRoomType($roomType);
            $room->setColor(RoomCatalog::colorFor($typeLabel));
            $room->setPosition($position++);
            $room->setFoyer($foyer);
            $manager->persist($room);

            foreach ($tasks as [$taskLabel, $frequencyDays, $doneDaysAgo]) {
                $roomTask = new RoomTask();
                $roomTask->setRoom($room);
                $roomTask->setTask($this->catalogTask($manager, $roomType, $taskLabel, $frequencyDays));
                $roomTask->setFrequencyDays($frequencyDays);
                $roomTask->setLastDoneAt($now->modify(sprintf('-%d days', $doneDaysAgo)));
                $manager->persist($roomTask);
            }
        }

        $manager->flush();
    }

    private function roomType(ObjectManager $manager, string $label): RoomType
    {
        $roomType = $manager->getRepository(RoomType::class)->findOneBy(['label' => $label]);
        if (!$roomType instanceof RoomType) {
            $roomType = (new RoomType())->setLabel($label);
            $manager->persist($roomType);
            $manager->flush();
        }

        return $roomType;
    }

    private function catalogTask(ObjectManager $manager, RoomType $roomType, string $label, int $frequencyDays): Task
    {
        $task = $manager->getRepository(Task::class)->findOneBy(['roomType' => $roomType, 'label' => $label]);
        if (!$task instanceof Task) {
            $task = (new Task())->setRoomType($roomType)->setLabel($label)->setDefaultFrequencyDays($frequencyDays);
            $manager->persist($task);
            $manager->flush();
        }

        return $task;
    }
}
