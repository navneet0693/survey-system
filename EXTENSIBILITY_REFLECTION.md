# Extensibility Reflection - Survey System

## Overview
This document outlines how the survey system can be extended to support additional question types and anonymous participation while maintaining clean, maintainable business logic.

## 1. Different Question Types

### Current Question Types
The system currently supports:
- `single_choice` - One option selection
- `multiple_choice` - Multiple option selections  

### Extending for New Question Types

#### 1.1 Text and File Upload Questions
**Implementation Strategy:**
```php
// 1. Add to QuestionType enum
enum QuestionType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
    case TEXT = 'text';
    case FILE_UPLOAD = 'file_upload';
}
```

### Design Principles for Question Type Extension

#### 1. Strategy Pattern
```php
interface QuestionTypeHandler
{
    public function validate(array $data): void;
    public function process(array $data): void;
}

class TextQuestionHandler implements QuestionTypeHandler
{
    public function validate(array $data): void
    {
        // Text validation
    }
    
    public function process(array $data): void
    {
        // Text processing
    }
}
```

#### 2. Factory Pattern
```php
class QuestionTypeHandlerFactory
{
    public function createHandler(QuestionType $type): QuestionTypeHandler
    {
        return match($type) {
            QuestionType::SINGLE_CHOICE => new SingleChoiceHandler(),
            QuestionType::MULTIPLE_CHOICE => new MultipleChoiceHandler(),
            QuestionType::TEXT => new TextQuestionHandler(),
            default => throw new \InvalidArgumentException('Unsupported question type')
        };
    }
}
```

## 2. Anonymous Participation

### Current System Limitations
The current system requires a `user_id` for all responses, which prevents anonymous participation.

### Implementation Strategy

#### 2.1 Anonymous Token System
```php
// 1. Add anonymous support to surveys table
ALTER TABLE surveys ADD COLUMN allow_anonymous BOOLEAN DEFAULT FALSE;
ALTER TABLE surveys ADD COLUMN anonymous_token_length INT DEFAULT 16;

// 2. Modify survey_responses table
ALTER TABLE survey_responses ADD COLUMN anonymous_token VARCHAR(64) NULL;
ALTER TABLE survey_responses ADD COLUMN is_anonymous BOOLEAN DEFAULT FALSE;

// 3. Update unique constraint for anonymous responses
DROP INDEX unique_user_survey;
CREATE UNIQUE INDEX unique_user_survey ON survey_responses (user_id, survey_id) WHERE user_id IS NOT NULL;
CREATE UNIQUE INDEX unique_anonymous_survey ON survey_responses (anonymous_token, survey_id) WHERE anonymous_token IS NOT NULL;
```

#### 2.2 Anonymous Response Submission
```php
// 1. Generate anonymous token
private function generateAnonymousToken(int $length = 16): string
{
    return bin2hex(random_bytes($length / 2));
}

// 2. Modified submit response method
public function submitAnonymousResponse(int $surveyId, array $responses): SurveyResponse
{
    $survey = $this->surveyRepository->find($surveyId);
    
    if (!$survey->isAllowAnonymous()) {
        throw new BadRequestHttpException('Anonymous responses not allowed for this survey');
    }
    
    // Generate unique anonymous token
    do {
        $anonymousToken = $this->generateAnonymousToken();
        $existingResponse = $this->responseRepository->findOneBy([
            'survey' => $survey,
            'anonymousToken' => $anonymousToken
        ]);
    } while ($existingResponse);
    
    $surveyResponse = new SurveyResponse();
    $surveyResponse->setSurvey($survey)
        ->setAnonymousToken($anonymousToken)
        ->setIsAnonymous(true);
    
    // Process responses...
    
    return $surveyResponse;
}
```

#### 2.3 Anonymous Results
```php
// Modified results collection to handle anonymous responses
public function getSurveyResults(int $surveyId): array
{
    $survey = $this->surveyRepository->find($surveyId);
    $responses = $this->responseRepository->findBy(['survey' => $survey]);
    
    $totalResponses = count($responses);
    $anonymousResponses = count(array_filter($responses, fn($r) => $r->isAnonymous()));
    $authenticatedResponses = $totalResponses - $anonymousResponses;
    
    $results = [
        'survey' => [
            'id' => $survey->getId(),
            'title' => $survey->getTitle(),
            'total_responses' => $totalResponses,
            'anonymous_responses' => $anonymousResponses,
            'authenticated_responses' => $authenticatedResponses,
        ],
        'questions' => []
    ];
    
    return $results;
}
```

## Implementation Benefits

### Clean Architecture
- **Separation of Concerns**: Question type logic separated from core business logic
- **Open/Closed Principle**: Easy to add new question types without modifying existing code
- **Strategy Pattern**: Each question type has its own validation and processing logic

This extensibility approach ensures the system remains clean, maintainable, and focused on business logic while supporting future growth and feature requirements.
