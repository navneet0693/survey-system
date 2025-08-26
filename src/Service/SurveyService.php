<?php

namespace App\Service;

use App\Entity\Question;
use App\Entity\QuestionOption;
use App\Entity\QuestionResponse;
use App\Entity\QuestionType;
use App\Entity\Survey;
use App\Entity\SurveyResponse;
use App\Repository\SurveyRepository;
use App\Repository\SurveyResponseRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * Survey Service.
 *
 * Central service for all survey-related business operations. Handles survey
 * creation, response submission, results aggregation, and validation.
 *
 * Error Handling:
 * - Validation errors return BadRequestHttpException
 * - Duplicate responses return ConflictHttpException
 * - Not found resources return NotFoundHttpException
 * - Database errors are wrapped in generic exceptions
 *
 * @author navneet0693
 */
class SurveyService
{
    /**
     * Constructor injects required dependencies.
     *
     * @param EntityManagerInterface   $entityManager      Doctrine entity manager for database operations
     * @param SurveyRepository         $surveyRepository   Repository for survey data access
     * @param SurveyResponseRepository $responseRepository Repository for response data access
     * @param ValidatorInterface       $validator          Symfony validator for data validation
     */
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly SurveyRepository $surveyRepository,
        private readonly SurveyResponseRepository $responseRepository,
        private readonly ValidatorInterface $validator,
    ) {
    }

    /**
     * Creates a new survey with questions and options.
     *
     * Validates all input data, creates survey entity with associated questions
     * and options.
     *
     * @param array $data Survey data containing:
     *                    - title: Survey title (3-255 chars, required)
     *                    - description: Optional survey description (max 1000 chars)
     *                    - property_manager_id: ID of creating manager (required)
     *                    - property_id: Optional property association
     *                    - questions: Array of question objects (required)
     *                    - question_text: Question text (5-500 chars, required)
     *                    - question_type: Type enum value (required)
     *                    - is_required: Whether question is mandatory (default: true)
     *                    - has_other_option: Include "other" option (default: false) (ToDo)
     *                    - order_position: Display order (default: 0)
     *                    - options: Array of option objects for choice questions
     *                    - option_text: Option text (required)
     *                    - order_position: Display order (default: 0)
     *
     * @return Survey Created survey entity with all relationships
     *
     * @throws BadRequestHttpException When validation fails for survey, questions, or options
     * @throws \Exception              When database operation fails
     */
    public function createSurvey(array $data): Survey
    {
        $survey = new Survey();
        $survey->setTitle($data['title'])
            ->setDescription($data['description'] ?? NULL);

        // Add questions to the survey
        foreach ($data['questions'] as $questionData) {
            $question = new Question();
            $question->setQuestionText($questionData['question_text'])
                ->setQuestionType(QuestionType::from($questionData['question_type']))
                ->setIsRequired($questionData['is_required'] ?? true)
                ->setOrderPosition($questionData['order_position'] ?? 0);

            // Add options for multiple choice questions
            if (isset($questionData['options'])) {
                foreach ($questionData['options'] as $optionData) {
                    $option = new QuestionOption();
                    $option->setOptionText($optionData['option_text'])
                        ->setOrderPosition($optionData['order_position'] ?? 0);

                    // Validate each option before adding to question
                    $optionViolations = $this->validator->validate($option);
                    if (count($optionViolations) > 0) {
                        $errors = [];
                        foreach ($optionViolations as $violation) {
                            $errors[] = $violation->getMessage();
                        }
                        throw new BadRequestHttpException('Option validation failed: '.implode(', ', $errors));
                    }

                    $question->addOption($option);
                }
            }

            // Validate each question before adding to survey
            $questionViolations = $this->validator->validate($question);
            if (count($questionViolations) > 0) {
                $errors = [];
                foreach ($questionViolations as $violation) {
                    $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
                }
                throw new BadRequestHttpException('Question validation failed: '.implode(', ', $errors));
            }

            $survey->addQuestion($question);
        }

        // Validate the entire survey before saving
        $surveyViolations = $this->validator->validate($survey);
        if (count($surveyViolations) > 0) {
            $errors = [];
            foreach ($surveyViolations as $violation) {
                $errors[] = $violation->getPropertyPath().': '.$violation->getMessage();
            }
            throw new BadRequestHttpException('Survey validation failed: '.implode(', ', $errors));
        }

        $this->entityManager->persist($survey);
        $this->entityManager->flush();

        return $survey;
    }

    /**
     * Submits a survey response for a user.
     *
     * Business Rules:
     * - One response per user per survey
     * - Survey must be active
     * - Required questions must be answered
     * - Selected options must belong to the question
     *
     * @param int   $surveyId  Survey ID
     * @param int   $userId    User ID (hardcoded for assignment)
     * @param array $responses Array of question responses
     *
     * @return SurveyResponse Created response entity
     *
     * @throws NotFoundHttpException   When survey not found
     * @throws ConflictHttpException   When user already responded
     * @throws BadRequestHttpException When validation fails
     */
    public function submitResponse(int $surveyId, int $userId, array $responses): SurveyResponse
    {
        $survey = $this->surveyRepository->find($surveyId);
        if (!$survey) {
            throw new NotFoundHttpException('Survey not found');
        }

        if (!$survey->isActive()) {
            throw new ConflictHttpException('Survey is not active');
        }

        // Check if user already responded
        $existingResponse = $this->responseRepository->findOneBy([
            'survey' => $survey,
            'userId' => $userId,
        ]);

        if ($existingResponse) {
            throw new ConflictHttpException('You have already submitted a response to this survey');
        }

        $surveyResponse = new SurveyResponse();
        $surveyResponse->setSurvey($survey)
            ->setUserId($userId);

        // Process each question response
        foreach ($responses as $responseData) {
            $question = $this->entityManager->getRepository(Question::class)->find($responseData['question_id']);
            if (!$question || $question->getSurvey() !== $survey) {
                continue; // Skip invalid questions
            }

            $questionResponse = new QuestionResponse();
            $questionResponse->setSurveyResponse($surveyResponse)
                ->setQuestion($question);

            // Handle different response types and validate them
            switch ($question->getQuestionType()) {
                case QuestionType::SINGLE_CHOICE:
                case QuestionType::MULTIPLE_CHOICE:
                    if (!empty($responseData['selected_option_ids'])) {
                        // Validate that selected options belong to this question
                        if (!$question->validateOptionIds($responseData['selected_option_ids'])) {
                            throw new BadRequestHttpException('Invalid option IDs provided for question: '.$question->getQuestionText());
                        }
                        $questionResponse->setSelectedOptionIds($responseData['selected_option_ids']);
                    }
                    break;
            }

            // Validate each question response
            $responseViolations = $this->validator->validate($questionResponse);
            if (count($responseViolations) > 0) {
                $errors = [];
                foreach ($responseViolations as $violation) {
                    $errors[] = $violation->getMessage();
                }
                throw new BadRequestHttpException('Response validation failed for question "'.$question->getQuestionText().'": '.implode(', ', $errors));
            }

            // Validate required questions have responses
            if ($question->isRequired() && !$questionResponse->hasResponse()) {
                throw new BadRequestHttpException('Response required for question: '.$question->getQuestionText());
            }

            $surveyResponse->addQuestionResponse($questionResponse);
        }

        // Validate the entire survey response
        $surveyResponseViolations = $this->validator->validate($surveyResponse);
        if (count($surveyResponseViolations) > 0) {
            $errors = [];
            foreach ($surveyResponseViolations as $violation) {
                $errors[] = $violation->getMessage();
            }
            throw new BadRequestHttpException('Survey response validation failed: '.implode(', ', $errors));
        }

        $this->entityManager->persist($surveyResponse);
        $this->entityManager->flush();

        return $surveyResponse;
    }

    /**
     * Retrieves aggregated results for a survey.
     *
     * Calculates option counts and response statistics
     * for all questions in the survey.
     *
     * @param int $surveyId Survey ID
     *
     * @return array Aggregated results with statistics
     *
     * @throws NotFoundHttpException When survey not found
     */
    public function getSurveyResults(int $surveyId): array
    {
        $survey = $this->surveyRepository->find($surveyId);
        if (!$survey) {
            throw new NotFoundHttpException('Survey not found');
        }

        // Implementation for results aggregation
        $responses = $this->responseRepository->findBy(['survey' => $survey]);
        $totalResponses = count($responses);

        $results = [
            'survey' => [
                'id' => $survey->getId(),
                'title' => $survey->getTitle(),
                'total_responses' => $totalResponses,
            ],
            'questions' => [],
        ];

        foreach ($survey->getQuestions() as $question) {
            $questionResults = [
                'id' => $question->getId(),
                'question_text' => $question->getQuestionText(),
                'question_type' => $question->getQuestionType()->value,
                'total_responses' => 0,
                'results' => [],
            ];

            if ($question->isChoiceQuestion()) {
                $optionCounts = [];

                // Initialize option counts
                foreach ($question->getOptions() as $option) {
                    $optionCounts[$option->getId()] = [
                        'id' => $option->getId(),
                        'option_text' => $option->getOptionText(),
                        'count' => 0,
                    ];
                }

                // Count responses
                foreach ($responses as $response) {
                    foreach ($response->getQuestionResponses() as $questionResponse) {
                        if ($questionResponse->getQuestion()->getId() === $question->getId()) {
                            ++$questionResults['total_responses'];

                            // Count selected options
                            if (!empty($questionResponse->getSelectedOptionIds())) {
                                foreach ($questionResponse->getSelectedOptionIds() as $optionId) {
                                    if (isset($optionCounts[$optionId])) {
                                        ++$optionCounts[$optionId]['count'];
                                    }
                                }
                            }
                        }
                    }
                }

                $questionResults['results'] = [
                    'options' => array_values($optionCounts),
                ];
            }

            $results['questions'][] = $questionResults;
        }

        return $results;
    }
}
