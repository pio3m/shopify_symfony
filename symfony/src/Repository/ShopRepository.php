<?php


namespace App\Repository;

use App\Entity\Shop;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShopRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Shop::class);
    }

    public function findOneByDomain(string $domain): ?Shop
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.shopDomain = :shop')
            ->setParameter('shop', strtolower($domain))
            ->getQuery()
            ->getOneOrNullResult();
    }
}
