<?php

namespace App\Repository;

use App\Entity\QuestionResponse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Question Response Repository.
 *
 * @extends ServiceEntityRepository<QuestionResponse>
 *
 * @author navneet0693
 */
class QuestionResponseRepository extends ServiceEntityRepository
{
    /**
     * Constructor initializes repository with QuestionResponse entity.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, QuestionResponse::class);
    }

    /**
     * Get all responses for a specific question.
     *
     * @param int $questionId ID of the question to get responses for
     *
     * @return QuestionResponse[] Array of responses for the question
     */
    public function getResponsesForQuestion(int $questionId): array
    {
        return $this->createQueryBuilder('qr')
            ->where('qr.question = :questionId')
            ->setParameter('questionId', $questionId)
            ->getQuery()
            ->getResult();
    }

    /**
     * Get aggregated results for a survey.
     *
     * @param int $surveyId ID of the survey to get aggregated results for
     *
     * @return QuestionResponse[] Array of responses with survey responses and questions
     */
    public function getAggregatedResults(int $surveyId): array
    {
        // This is a complex query that would aggregate responses by question
        // For now, we'll handle this in the service layer
        return $this->createQueryBuilder('qr')
            ->leftJoin('qr.surveyResponse', 'sr')
            ->leftJoin('qr.question', 'q')
            ->where('sr.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->getQuery()
            ->getResult();
    }
}
