<?php

namespace App\Controller;

use App\Entity\Foyer;
use App\Entity\User;
use App\Repository\FoyerRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Inscription : soit on crée un nouveau foyer, soit on rejoint un foyer
 * existant grâce à son code d'invitation. Dans les deux cas on crée un
 * compte (email + mot de passe) rattaché à ce foyer.
 */
final class RegisterController extends AbstractController
{
    private const CSRF_TOKEN_ID = 'register';

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
        private readonly FoyerRepository $foyerRepository,
        private readonly UserRepository $userRepository,
    ) {
    }

    #[Route('/register', name: 'app_register', methods: ['GET'])]
    public function index(): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/register.html.twig', [
            'errors' => [],
            'mode' => null,
            'values' => [],
        ]);
    }

    #[Route('/register/creer', name: 'app_register_create_foyer', methods: ['POST'])]
    public function createFoyer(Request $request): Response
    {
        $this->assertCsrf($request);

        $foyerName = trim((string) $request->request->get('foyer_name'));
        $errors = $this->registerCommonErrors($request);

        if ($foyerName === '') {
            $errors[] = 'Le nom du foyer est obligatoire.';
        } elseif (mb_strlen($foyerName) > 80) {
            $errors[] = 'Le nom du foyer est trop long (80 caractères max).';
        }

        if ($errors !== []) {
            return $this->renderRegisterErrors($request, $errors, 'creer');
        }

        $foyer = new Foyer();
        $foyer->setName($foyerName);
        $foyer->setInviteCode($this->generateUniqueInviteCode());
        $this->entityManager->persist($foyer);

        return $this->persistUserAndRedirect($request, $foyer);
    }

    #[Route('/register/rejoindre', name: 'app_register_join_foyer', methods: ['POST'])]
    public function joinFoyer(Request $request): Response
    {
        $this->assertCsrf($request);

        $inviteCode = strtoupper(trim((string) $request->request->get('invite_code')));
        $errors = $this->registerCommonErrors($request);

        $foyer = $inviteCode !== '' ? $this->foyerRepository->findOneByInviteCode($inviteCode) : null;

        if ($inviteCode === '') {
            $errors[] = 'Le code du foyer est obligatoire.';
        } elseif (!$foyer instanceof Foyer) {
            $errors[] = "Aucun foyer ne correspond à ce code.";
        }

        if ($errors !== []) {
            return $this->renderRegisterErrors($request, $errors, 'rejoindre');
        }

        return $this->persistUserAndRedirect($request, $foyer);
    }

    /**
     * @return list<string>
     */
    private function registerCommonErrors(Request $request): array
    {
        $errors = [];

        $email = trim((string) $request->request->get('email'));
        $pseudo = trim((string) $request->request->get('pseudo'));
        $password = (string) $request->request->get('password');
        $passwordConfirm = (string) $request->request->get('password_confirm');

        if ($email === '' || $this->validator->validate($email, new Email())->count() > 0) {
            $errors[] = 'Adresse email invalide.';
        } elseif ($this->userRepository->findOneByEmail($email) !== null) {
            $errors[] = 'Un compte existe déjà avec cet email.';
        }

        if ($pseudo === '') {
            $errors[] = 'Le prénom / pseudo est obligatoire.';
        } elseif (mb_strlen($pseudo) > 60) {
            $errors[] = 'Le prénom / pseudo est trop long (60 caractères max).';
        }

        if (mb_strlen($password) < 8) {
            $errors[] = 'Le mot de passe doit faire au moins 8 caractères.';
        } elseif ($password !== $passwordConfirm) {
            $errors[] = 'Les deux mots de passe ne correspondent pas.';
        }

        return $errors;
    }

    private function persistUserAndRedirect(Request $request, Foyer $foyer): Response
    {
        $email = strtolower(trim((string) $request->request->get('email')));
        $pseudo = trim((string) $request->request->get('pseudo'));
        $password = (string) $request->request->get('password');

        $user = new User();
        $user->setEmail($email);
        $user->setPseudo($pseudo);
        $user->setFoyer($foyer);
        $user->setPassword($this->passwordHasher->hashPassword($user, $password));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $this->addFlash('success', sprintf('Bienvenue %s ! Ton foyer "%s" est prêt (code à partager : %s).', $pseudo, $foyer->getName(), $foyer->getInviteCode()));

        return $this->redirectToRoute('app_login');
    }

    private function renderRegisterErrors(Request $request, array $errors, string $mode): Response
    {
        return $this->render('security/register.html.twig', [
            'errors' => $errors,
            'mode' => $mode,
            'values' => [
                'foyer_name' => $request->request->get('foyer_name', ''),
                'invite_code' => $request->request->get('invite_code', ''),
                'email' => $request->request->get('email', ''),
                'pseudo' => $request->request->get('pseudo', ''),
            ],
        ]);
    }

    private function generateUniqueInviteCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->foyerRepository->findOneByInviteCode($code) !== null);

        return $code;
    }

    private function assertCsrf(Request $request): void
    {
        $token = new CsrfToken(self::CSRF_TOKEN_ID, (string) $request->request->get('_csrf_token'));

        if (!$this->csrfTokenManager->isTokenValid($token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }
    }
}
