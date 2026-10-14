<?php

namespace App\Repository;

use App\Entity\RoomType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RoomType>
 */
class RoomTypeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RoomType::class);
    }

    public function findOneByLabel(string $label): ?RoomType
    {
        return $this->findOneBy(['label' => $label]);
    }
}
