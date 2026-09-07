<?php

namespace App\Controller;

use App\Entity\Foyer;
use App\Entity\Room;
use App\Entity\User;
use App\Repository\RoomRepository;
use App\Service\RoomCatalog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[IsGranted('ROLE_USER')]
final class HomeController extends AbstractController
{
    public function __construct(
        private readonly RoomRepository $roomRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(): Response
    {
        $foyer = $this->currentFoyer();
        $rooms = $this->roomRepository->findBy(['foyer' => $foyer], ['position' => 'ASC']);

        $overdueTotal = 0;
        $dueSoonTotal = 0;
        foreach ($rooms as $room) {
            $overdueTotal += $room->getOverdueCount();
            $dueSoonTotal += $room->getDueSoonCount();
        }

        if ($overdueTotal > 0) {
            $summaryLabel = sprintf('%d tâche%s en retard', $overdueTotal, $overdueTotal > 1 ? 's' : '');
            $summarySub = $dueSoonTotal > 0 ? sprintf('%d de plus à faire bientôt', $dueSoonTotal) : 'Rattrape-les dès que possible';
        } elseif ($dueSoonTotal > 0) {
            $summaryLabel = sprintf('%d tâche%s à faire bientôt', $dueSoonTotal, $dueSoonTotal > 1 ? 's' : '');
            $summarySub = "Rien d'urgent pour l'instant";
        } else {
            $summaryLabel = 'Tout est propre';
            $summarySub = 'Aucune tâche en attente';
        }

        $existingNames = array_map(static fn (Room $room): string => $room->getName(), $rooms);

        return $this->render('home/index.html.twig', [
            'rooms' => $rooms,
            'summaryLabel' => $summaryLabel,
            'summarySub' => $summarySub,
            'roomCatalog' => RoomCatalog::excludingNames($existingNames),
        ]);
    }

    #[Route('/rooms', name: 'app_room_create', methods: ['POST'])]
    public function createRoom(Request $request): Response
    {
        $token = new CsrfToken('add_room', (string) $request->request->get('_csrf_token'));
        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $name = trim((string) $request->request->get('name'));
        $color = (string) $request->request->get('color', '#8AA9B8');

        if ($name === '') {
            $this->addFlash('error', 'Le nom de la pièce est obligatoire.');

            return $this->redirectToRoute('app_home');
        }

        $foyer = $this->currentFoyer();
        $position = count($this->roomRepository->findBy(['foyer' => $foyer]));

        $room = new Room();
        $room->setName($name);
        $room->setColor(preg_match('/^#[0-9A-Fa-f]{6}$/', $color) ? $color : '#8AA9B8');
        $room->setPosition($position);
        $room->setFoyer($foyer);

        $this->entityManager->persist($room);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Pièce "%s" créée.', $name));

        return $this->redirectToRoute('app_home');
    }

    private function currentFoyer(): Foyer
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->getFoyer();
    }
}
