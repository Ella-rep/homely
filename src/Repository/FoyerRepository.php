<?php

namespace App\Repository;

use App\Entity\Foyer;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Foyer>
 */
class FoyerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Foyer::class);
    }

    public function findOneByInviteCode(string $inviteCode): ?Foyer
    {
        return $this->findOneBy(['inviteCode' => strtoupper($inviteCode)]);
    }

    /** Code d'invitation à 6 caractères, unique, sans caractères ambigus (0/O, 1/I). */
    public function generateUniqueInviteCode(): string
    {
        $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= $alphabet[random_int(0, strlen($alphabet) - 1)];
            }
        } while ($this->findOneByInviteCode($code) !== null);

        return $code;
    }
}
