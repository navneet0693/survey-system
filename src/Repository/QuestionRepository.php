<?php

namespace App\Repository;

use App\Entity\Question;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Question Repository.
 *
 * @extends ServiceEntityRepository<Question>
 *
 * @author navneet0693
 */
class QuestionRepository extends ServiceEntityRepository
{
    /**
     * Constructor initializes repository with Question entity.
     *
     * @param ManagerRegistry $registry Doctrine manager registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Question::class);
    }

    /**
     * Find all questions for a survey with their options, ordered by position.
     *
     * @param int $surveyId ID of the survey to find questions for
     *
     * @return Question[] Array of questions with options, ordered by position
     */
    public function findBySurveyOrderedByPosition(int $surveyId): array
    {
        return $this->createQueryBuilder('q')
            ->leftJoin('q.options', 'o')
            ->addSelect('o')
            ->where('q.survey = :surveyId')
            ->setParameter('surveyId', $surveyId)
            ->orderBy('q.orderPosition', 'ASC')
            ->addOrderBy('o.orderPosition', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
