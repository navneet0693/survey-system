<?php

namespace App\Repository;

use App\Entity\QuestionOption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Question Option Repository.
 *
 * @extends ServiceEntityRepository<QuestionOption>
 *
 * @author navneet0693
 */
class QuestionOptionRepository extends ServiceEntityRepository
{
    /**
     * Constructor initializes repository with QuestionOption entity.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionOption::class);
    }

    /**
     * Save question option entity to database.
     *
     * @param QuestionOption $entity Question option entity to save
     * @param bool           $flush  Whether to flush changes immediately (default: true)
     */
    public function save(QuestionOption $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Find all options for a specific question, ordered by position.
     *
     * @param int $questionId ID of the question to find options for
     *
     * @return QuestionOption[] Array of options ordered by position
     */
    public function findByQuestion(int $questionId): array
    {
        return $this->createQueryBuilder('qo')
            ->where('qo.question = :questionId')
            ->setParameter('questionId', $questionId)
            ->orderBy('qo.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
