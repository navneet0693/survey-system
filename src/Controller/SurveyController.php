<?php

namespace App\Controller;

use App\Entity\SurveyStatus;
use App\Repository\SurveyRepository;
use App\Service\SurveyService;
use Doctrine\Common\Collections\Collection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Survey Controller.
 *
 * REST API controller for survey management operations. Provides endpoints for
 * creating, listing, viewing, and managing surveys and their responses.
 *
 * Key Endpoints:
 * - POST /api/surveys - Create a new survey
 * - GET /api/surveys - List all surveys with pagination
 * - GET /api/surveys/{id} - Get survey details with questions
 * - POST /api/surveys/{id} - Submit survey response
 * - GET /api/surveys/{id}/responses - Get aggregated survey results
 *
 * Business Rules:
 * - Only ACTIVE surveys can receive responses
 * - Users can only submit one response per survey
 * - Required questions must be answered
 * - Survey responses are immutable once submitted
 * - Results are aggregated for privacy and analytics
 *
 * @author navneet0693
 *
 * @Route("/api/surveys")
 */
#[Route('/api/surveys')]
class SurveyController extends AbstractController
{
    /**
     * Constructor injects required services.
     *
     * @param SurveyService    $surveyService    Service for survey business logic
     * @param SurveyRepository $surveyRepository Repository for survey data access
     */
    public function __construct(
        private readonly SurveyService $surveyService,
        private readonly SurveyRepository $surveyRepository,
    ) {
    }

    /**
     * Create a new survey with questions.
     *
     * Accepts JSON payload with survey metadata and question definitions.
     * Validates input data and creates survey with associated questions.
     *
     * Request Body:
     * {
     *   "title": "Survey Title",
     *   "description": "Optional description",
     *   "questions": [
     *     {
     *       "question_text": "What is your favorite color?",
     *       "question_type": "single_choice",
     *       "is_required": true,
     *       "has_other_option": false, (ToDo)
     *       "options": [
     *         {"option_text": "Red", "order_position": 1},
     *         {"option_text": "Blue", "order_position": 2}
     *       ]
     *     }
     *   ]
     * }
     *
     * @param Request $request HTTP request containing survey data
     *
     * @return JsonResponse Created survey data or error response
     *
     * @throws BadRequestHttpException When validation fails
     * @throws \Exception              When database operations fail
     */
    #[Route('', name: 'create_survey', methods: ['POST'])]
    public function createSurvey(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Basic validation for required fields
        if (empty($data['title']) || empty($data['questions'])) {
            return new JsonResponse([
                'error' => 'Title and questions are required',
                'code' => 'VALIDATION_ERROR',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // For demo purposes, hardcode property_manager_id
            $data['property_manager_id'] = 1;

            $survey = $this->surveyService->createSurvey($data);

            return new JsonResponse([
                'id' => $survey->getId(),
                'title' => $survey->getTitle(),
                'status' => $survey->getStatus()->value,
                'created_at' => $survey->getCreatedAt()->format('c'),
                'questions' => $this->serializeQuestions($survey->getQuestions()),
            ], Response::HTTP_CREATED);
        } catch (BadRequestHttpException $e) {
            // Validation errors from the validator are caught here
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Failed to create survey',
                'message' => $e->getMessage(),
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * List all surveys with pagination and filtering.
     *
     * Supports pagination and status filtering. Returns survey metadata
     * without detailed questions for performance.
     *
     * Query Parameters (ToDo):
     * - page: Page number (default: 1)
     * - limit: Items per page (default: 10, max: 100)
     * - status: Filter by survey status (draft, active, closed)
     *
     * @param Request $request HTTP request with query parameters
     *
     * @return JsonResponse Paginated list of surveys
     */
    #[Route('', name: 'get_all_surveys', methods: ['GET'])]
    public function listSurveys(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 10)));
        $status = $request->query->get('status');

        $criteria = [];
        if ($status) {
            $criteria['status'] = $status;
        }

        $surveys = $this->surveyRepository->findBy(
            $criteria,
            ['createdAt' => 'DESC'],
            $limit,
            ($page - 1) * $limit
        );

        $data = [];
        foreach ($surveys as $survey) {
            $data[] = [
                'id' => $survey->getId(),
                'title' => $survey->getTitle(),
                'status' => $survey->getStatus()->value,
                'response_count' => $survey->getResponseCount(),
                'created_at' => $survey->getCreatedAt()->format('c'),
            ];
        }

        return new JsonResponse(['data' => $data]);
    }

    /**
     * Get detailed survey information including questions.
     *
     * Returns complete survey data with all questions and their options.
     * Used for displaying survey forms to respondents.
     *
     * @param int $id Survey ID from URL parameter
     *
     * @return JsonResponse Survey details or 404 error
     */
    #[Route('/{id}', name: 'get_survey', methods: ['GET'])]
    public function getSurvey(int $id): JsonResponse
    {
        $survey = $this->surveyRepository->find($id);

        if (!$survey) {
            return new JsonResponse([
                'error' => 'Survey not found',
            ], Response::HTTP_NOT_FOUND);
        }

        return new JsonResponse([
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'description' => $survey->getDescription(),
            'status' => $survey->getStatus()->value,
            'questions' => $this->serializeQuestions($survey->getQuestions()),
        ]);
    }

    /**
     * Submit a survey response.
     *
     * Accepts JSON payload with question responses and submits them
     * to the survey system. Validates that survey is active and user
     * hasn't already responded.
     *
     * Request Body:
     * {
     *   "responses": [
     *     {
     *       "question_id": 1,
     *       "selected_option_ids": [1, 2],
     *     }
     *   ]
     * }
     *
     * @param int     $id      Survey ID from URL parameter
     * @param Request $request HTTP request containing response data
     *
     * @return JsonResponse Submission result or error response
     *
     * @throws \Exception When survey not found, already responded, or not active
     */
    #[Route('/{id}', name: 'submit_survey_response', methods: ['POST'])]
    public function submitResponse(int $id, Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        if (empty($data['responses'])) {
            return new JsonResponse([
                'error' => 'Responses are required',
                'code' => 'VALIDATION_ERROR',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            // For demo purposes, hardcode user_id
            $userId = 1;

            $response = $this->surveyService->submitResponse($id, $userId, $data['responses']);

            return new JsonResponse([
                'id' => $response->getId(),
                'survey_id' => $response->getSurvey()->getId(),
                'submitted_at' => $response->getSubmittedAt()->format('c'),
                'message' => 'Response submitted successfully',
            ], Response::HTTP_CREATED);
        } catch (\Exception $e) {
            $statusCode = Response::HTTP_INTERNAL_SERVER_ERROR;
            $errorCode = 'SERVER_ERROR';

            if (str_contains($e->getMessage(), 'already submitted')) {
                $statusCode = Response::HTTP_CONFLICT;
                $errorCode = 'RESPONSE_ALREADY_EXISTS';
            } elseif (str_contains($e->getMessage(), 'not found')) {
                $statusCode = Response::HTTP_NOT_FOUND;
                $errorCode = 'SURVEY_NOT_FOUND';
            } elseif (str_contains($e->getMessage(), 'not active')) {
                $statusCode = Response::HTTP_CONFLICT;
                $errorCode = 'SURVEY_NOT_ACTIVE';
            }

            return new JsonResponse([
                'error' => $e->getMessage(),
                'code' => $errorCode,
            ], $statusCode);
        }
    }

    /**
     * Get aggregated survey results.
     *
     * Retrieves and aggregates all responses for a survey, providing
     * statistics and analytics for each question. Results are anonymized
     * for privacy protection.
     *
     * @param int $id Survey ID from URL parameter
     *
     * @return JsonResponse Aggregated results or error response
     *
     * @throws \Exception When survey not found
     */
    #[Route('/{id}/responses', name: 'get_survey_results', methods: ['GET'])]
    public function getSurveyResults(int $id): JsonResponse
    {
        try {
            $results = $this->surveyService->getSurveyResults($id);

            return new JsonResponse($results);
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => $e->getMessage(),
            ], Response::HTTP_NOT_FOUND);
        }
    }

    #[Route('/{id}/status', name: 'update_survey_status', methods: ['PUT'])]
    public function updateSurveyStatus(int $id, Request $request): JsonResponse
    {
        $survey = $this->surveyRepository->find($id);

        if (!$survey) {
            return new JsonResponse([
                'error' => 'Survey not found',
            ], Response::HTTP_NOT_FOUND);
        }

        $data = json_decode($request->getContent(), true);
        $status = $data['status'] ?? null;

        if (!$status || !in_array($status, ['draft', 'active', 'closed'])) {
            return new JsonResponse([
                'error' => 'Invalid status',
                'code' => 'VALIDATION_ERROR',
            ], Response::HTTP_BAD_REQUEST);
        }

        $survey->setStatus(SurveyStatus::from($status));

        $this->surveyRepository->save($survey);

        return new JsonResponse([
            'id' => $survey->getId(),
            'status' => $survey->getStatus()->value,
            'message' => 'Survey status updated successfully',
        ]);
    }

    /**
     * Serialize questions collection to array format.
     *
     * Converts a collection of Question entities to an array format
     * suitable for JSON response. Includes all question data and
     * associated options with their ordering.
     *
     * @param Collection $questions Collection of Question entities
     *
     * @return array Array representation of questions with options
     */
    private function serializeQuestions(Collection $questions): array
    {
        $result = [];
        foreach ($questions as $question) {
            $questionData = [
                'id' => $question->getId(),
                'question_text' => $question->getQuestionText(),
                'question_type' => $question->getQuestionType()->value,
                'is_required' => $question->isRequired(),
                'options' => [],
            ];

            foreach ($question->getOptions() as $option) {
                $questionData['options'][] = [
                    'id' => $option->getId(),
                    'option_text' => $option->getOptionText(),
                    'order_position' => $option->getOrderPosition(),
                ];
            }

            $result[] = $questionData;
        }

        return $result;
    }
}
