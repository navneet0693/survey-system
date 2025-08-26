<?php

namespace App\Repository;

use App\Entity\SurveyResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Survey Response Repository.
 *
 * @extends ServiceEntityRepository<SurveyResponse>
 *
 * @author navneet0693
 */
class SurveyResponseRepository extends ServiceEntityRepository
{
    /**
     * Constructor initializes repository with SurveyResponse entity.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, SurveyResponse::class);
    }

    /**
     * Save survey response entity to database.
     *
     * @param SurveyResponse $entity Survey response entity to save
     * @param bool           $flush  Whether to flush changes immediately (default: true)
     */
    public function save(SurveyResponse $entity, bool $flush = true): void
    {
        $this->getEntityManager()->persist($entity);
        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /**
     * Check if a user has already responded to a specific survey.
     *
     * @param int $surveyId ID of the survey to check
     * @param int $userId   ID of the user to check
     *
     * @return bool True if user has already responded, false otherwise
     */
    public function hasUserResponded(int $surveyId, int $userId): bool
    {
        $count = $this->createQueryBuilder('sr')
            ->select('COUNT(sr.id)')
            ->where('sr.survey = :surveyId')
            ->andWhere('sr.userId = :userId')
            ->setParameter('surveyId', $surveyId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    /**
     * Get all responses for a specific survey with question responses.
     *
     * @param int $surveyId ID of the survey to get responses for
     *
     * @return SurveyResponse[] Array of responses with question responses, ordered by submission date
     */
    public function getResponsesForSurvey(int $surveyId): array
    {
        return $this->createQueryBuilder('sr')
            ->leftJoin('sr.questionResponses', 'qr')
            ->leftJoin('qr.question', 'q')
            ->addSelect('qr', 'q')
            ->where('sr.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('sr.submittedAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
