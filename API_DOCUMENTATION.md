# API Documentation - Survey System

## Overview
RESTful API for survey management and response collection.

## Base URL
```
http://localhost:8000/api
```

## Core Endpoints

### 1. Create Survey
**POST** `/surveys`

Creates a new survey with questions and options.

**Request Body:**
```json
{
    "title": "Tenant Satisfaction Survey",
    "description": "Monthly feedback collection",
    "questions": [
        {
            "question_text": "How satisfied are you with maintenance response?",
            "question_type": "single_choice",
            "is_required": true,
            "has_other_option": true,
            "options": [
                {"option_text": "Very satisfied", "order_position": 1},
                {"option_text": "Satisfied", "order_position": 2},
                {"option_text": "Neutral", "order_position": 3},
                {"option_text": "Dissatisfied", "order_position": 4}
            ]
        },
        {
            "question_text": "What amenities would you like to see improved?",
            "question_type": "multiple_choice",
            "is_required": false,
            "has_other_option": true,
            "options": [
                {"option_text": "Gym", "order_position": 1},
                {"option_text": "Pool", "order_position": 2},
                {"option_text": "Parking", "order_position": 3},
                {"option_text": "Common Areas", "order_position": 4}
            ]
        }
    ]
}
```

**Response:** `201 Created`
```json
{
    "id": 1,
    "title": "Tenant Satisfaction Survey",
    "status": "draft",
    "created_at": "2025-08-26T00:53:09+05:30",
    "questions": [
        {
            "id": 1,
            "question_text": "How satisfied are you with maintenance response?",
            "question_type": "single_choice",
            "is_required": true,
            "has_other_option": true,
            "options": [
                {
                    "id": 1,
                    "option_text": "Very satisfied",
                    "order_position": 1
                },
                {
                    "id": 2,
                    "option_text": "Satisfied",
                    "order_position": 2
                },
                {
                    "id": 3,
                    "option_text": "Neutral",
                    "order_position": 3
                },
                {
                    "id": 4,
                    "option_text": "Dissatisfied",
                    "order_position": 4
                }
            ]
        },
        {
            "id": 2,
            "question_text": "What amenities would you like to see improved?",
            "question_type": "multiple_choice",
            "is_required": false,
            "has_other_option": true,
            "options": [
                {
                    "id": 5,
                    "option_text": "Gym",
                    "order_position": 1
                },
                {
                    "id": 6,
                    "option_text": "Pool",
                    "order_position": 2
                },
                {
                    "id": 7,
                    "option_text": "Parking",
                    "order_position": 3
                },
                {
                    "id": 8,
                    "option_text": "Common Areas",
                    "order_position": 4
                }
            ]
        }
    ]
}
```

**Validation Rules:**
- Title: 3-255 characters, required
- At least one question required
- Question text: 5-500 characters
- Choice questions must have at least one option

### 2. List Surveys
**GET** `/surveys`

Retrieves a list of surveys with basic information.

**Query Parameters (To be Implemented):**
- `status` (optional): Filter by status (`draft`, `active`, `closed`)
- `page` (optional): Page number (default: 1)
- `limit` (optional): Items per page (default: 10, max: 100)

**Response:** `200 OK`
```json
{
    "data": [
        {
            "id": 1,
            "title": "Tenant Satisfaction Survey",
            "status": "active",
            "response_count": 0,
            "created_at": "2025-08-26T00:53:09+05:30"
        }
    ]
}
```

### 3. Get Survey Details
**GET** `/surveys/{id}`

Retrieves detailed information about a specific survey.

**Response:** `200 OK`
```json
{
    "id": 1,
    "title": "Tenant Satisfaction Survey",
    "description": "Monthly feedback collection",
    "status": "active",
    "questions": [
        {
            "id": 1,
            "question_text": "How satisfied are you with maintenance response?",
            "question_type": "single_choice",
            "is_required": true,
            "has_other_option": true,
            "options": [
                {
                    "id": 1,
                    "option_text": "Very satisfied",
                    "order_position": 1
                },
                {
                    "id": 2,
                    "option_text": "Satisfied",
                    "order_position": 2
                },
                {
                    "id": 3,
                    "option_text": "Neutral",
                    "order_position": 3
                },
                {
                    "id": 4,
                    "option_text": "Dissatisfied",
                    "order_position": 4
                }
            ]
        },
        {
            "id": 2,
            "question_text": "What amenities would you like to see improved?",
            "question_type": "multiple_choice",
            "is_required": false,
            "has_other_option": true,
            "options": [
                {
                    "id": 5,
                    "option_text": "Gym",
                    "order_position": 1
                },
                {
                    "id": 6,
                    "option_text": "Pool",
                    "order_position": 2
                },
                {
                    "id": 7,
                    "option_text": "Parking",
                    "order_position": 3
                },
                {
                    "id": 8,
                    "option_text": "Common Areas",
                    "order_position": 4
                }
            ]
        }
    ]
}
```

### 4. Submit Survey Response
**POST** `/surveys/{id}`

Submits a survey response (one per user per survey).

**Request Body:**
```json
{
  "responses": [
    {
      "question_id": 1,
      "selected_option_ids": [1],
      "other_text": null // To be Implemented
    },
    {
      "question_id": 2,
      "selected_option_ids": [5, 6],
      "other_text": "Better mobile support" // To be Implemented
    }
  ]
}
```

**Response:** `201 Created`
```json
{
    "id": 1,
    "survey_id": 1,
    "submitted_at": "2025-08-26T08:04:27+05:30",
    "message": "Response submitted successfully"
}
```

**Business Rules:**
- One response per user per survey (enforced by unique constraint)
- Required questions must be answered
- Selected options must belong to the question

**Error Response:** `409 Conflict` (already submitted)
```json
{
  "error": "You have already submitted a response to this survey",
  "code": "RESPONSE_ALREADY_EXISTS"
}
```

### 5. Get Survey Results
**GET** `/surveys/{id}/responses`

Retrieves aggregated results for a survey.

**Response:** `200 OK`
```json
{
    "survey": {
        "id": 1,
        "title": "Tenant Satisfaction Survey",
        "total_responses": 1
    },
    "questions": [
        {
            "id": 1,
            "question_text": "How satisfied are you with maintenance response?",
            "question_type": "single_choice",
            "total_responses": 1,
            "results": {
                "options": [
                    {
                        "id": 1,
                        "option_text": "Very satisfied",
                        "count": 1
                    },
                    {
                        "id": 2,
                        "option_text": "Satisfied",
                        "count": 0
                    },
                    {
                        "id": 3,
                        "option_text": "Neutral",
                        "count": 0
                    },
                    {
                        "id": 4,
                        "option_text": "Dissatisfied",
                        "count": 0
                    }
                ]
            }
        },
        {
            "id": 2,
            "question_text": "What amenities would you like to see improved?",
            "question_type": "multiple_choice",
            "total_responses": 1,
            "results": {
                "options": [
                    {
                        "id": 5,
                        "option_text": "Gym",
                        "count": 1
                    },
                    {
                        "id": 6,
                        "option_text": "Pool",
                        "count": 1
                    },
                    {
                        "id": 7,
                        "option_text": "Parking",
                        "count": 0
                    },
                    {
                        "id": 8,
                        "option_text": "Common Areas",
                        "count": 0
                    }
                ]
            }
        }
    ]
}
```

## Error Handling

### Common Error Responses

**400 Bad Request** - Validation Error
```json
{
  "error": "Validation failed",
  "details": {
    "title": ["Title is required"],
    "questions": ["At least one question is required"]
  }
}
```

**404 Not Found** - Resource Not Found
```json
{
  "error": "Survey not found"
}
```

**409 Conflict** - Business Rule Violation
```json
{
  "error": "You have already submitted a response to this survey",
  "code": "RESPONSE_ALREADY_EXISTS"
}
```

**422 Unprocessable Entity** - Invalid Data
```json
{
  "error": "Invalid option IDs provided for question",
  "code": "INVALID_OPTION_SELECTION"
}
```

## Testing with curl

### Create Survey
```bash
curl -X POST http://localhost:8000/api/surveys \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Test Survey",
    "questions": [
      {
        "question_text": "Test Question",
        "question_type": "single_choice",
        "options": [{"option_text": "Option 1"}]
      }
    ]
  }'
```

### Submit Response
```bash
curl -X POST http://localhost:8000/api/surveys/1 \
  -H "Content-Type: application/json" \
  -d '{
    "responses": [
      {
        "question_id": 1,
        "selected_option_ids": [1]
      }
    ]
  }'
```

### Get Results
```bash
curl http://localhost:8000/api/surveys/1/responses
```

## HTTP Status Codes

- `200 OK` - Success
- `201 Created` - Resource created
- `400 Bad Request` - Validation error
- `404 Not Found` - Resource not found
- `409 Conflict` - Business rule violation
- `422 Unprocessable Entity` - Invalid data
- `500 Internal Server Error` - Server error

## Data Types

### Question Types
- `single_choice` - One option selection
- `multiple_choice` - Multiple option selections
- `text` - Free text response // ToDO
- `file_upload` - File upload response // ToDo

### Survey Status
- `draft` - Survey not yet active
- `active` - Survey accepting responses
- `closed` - Survey no longer accepting responses
