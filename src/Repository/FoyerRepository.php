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
}
