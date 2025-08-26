<?php

namespace App\Repository;

use App\Entity\Survey;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Survey Repository.
 *
 * @extends ServiceEntityRepository<Survey>
 *
 * @author navneet0693
 */
class SurveyRepository extends ServiceEntityRepository
{
    /**
     * Constructor initializes repository with Survey entity.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Survey::class);
    }

    /**
     * Save survey entity to database.
     *
     * @param Survey $entity Survey entity to save
     * @param bool   $flush  Whether to flush changes immediately (default: true)
     */
    public function save(Survey $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all active surveys available for responses.
     *
     * Criteria:
     * - Status must be 'active'
     * - Start date must be null or in the past
     * - End date must be null or in the future
     *
     * @return Survey[] Array of active surveys ordered by creation date (newest first)
     */
    public function findActiveSurveys(): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->andWhere('s.startsAt IS NULL OR s.startsAt <= :now')
            ->andWhere('s.endsAt IS NULL OR s.endsAt >= :now')
            ->setParameter('status', 'active')
            ->setParameter('now', new \DateTime())
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find surveys created by a specific property manager.
     *
     * Returns all surveys (regardless of status) created by the specified
     * property manager. Used for property manager dashboard views.
     *
     * @param int $propertyManagerId ID of the property manager
     *
     * @return Survey[] Array of surveys ordered by creation date (newest first)
     */
    public function findByPropertyManager(int $propertyManagerId): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.propertyManagerId = :managerId')
            ->setParameter('managerId', $propertyManagerId)
            ->orderBy('s.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Find survey with eager-loaded questions and options.
     *
     * @param int $id Survey ID to find
     *
     * @return Survey|null Survey with questions and options, or null if not found
     */
    public function findSurveyWithQuestions(int $id): ?Survey
    {
        return $this->createQueryBuilder('s')
            ->leftJoin('s.questions', 'q')
            ->leftJoin('q.options', 'o')
            ->addSelect('q', 'o')
            ->where('s.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
