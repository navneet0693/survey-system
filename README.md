# Survey System

A PHP/Symfony-based survey system demonstrating clean business logic for creating surveys, collecting responses, and generating results.

## Functionalities

- [x] **Create surveys with multiple-choice questions**
- [x] **One response per user per survey**
- [x] **View aggregated results**

## Quick Start

### Prerequisites
- PHP 8.3+
- Composer
- MySQL/PostgreSQL

### Installation
```bash
# Install dependencies
composer install

# Setup database
php bin/console doctrine:database:create
php bin/console make:migration
php bin/console doctrine:migrations:migrate

# Start development server
symfony server:start
```

### DDEV Based
```bash
# Install dependencies
ddev start
ddev composer install

# Setup database
ddev console make:migration
ddev console doctrine:migrations:migrate
```

## Working

### 1. Create a Survey
```bash
curl -X POST http://localhost:8000/api/surveys \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Customer Satisfaction Survey",
    "description": "Monthly feedback collection",
    "questions": [
      {
        "question_text": "How satisfied are you with our service?",
        "question_type": "single_choice",
        "is_required": true,
        "has_other_option": true,
        "options": [
          {"option_text": "Very satisfied"},
          {"option_text": "Satisfied"},
          {"option_text": "Neutral"},
          {"option_text": "Dissatisfied"}
        ]
      },
      {
        "question_text": "Which features would you like to see improved?",
        "question_type": "multiple_choice",
        "is_required": false,
        "has_other_option": true,
        "options": [
          {"option_text": "User Interface"},
          {"option_text": "Performance"},
          {"option_text": "Documentation"},
          {"option_text": "Support"}
        ]
      }
    ]
  }'
```

### 2. Submit a Response
```bash
curl -X POST http://localhost:8000/api/surveys/1 \
  -H "Content-Type: application/json" \
  -d '{
    "responses": [
      {
        "question_id": 1,
        "selected_option_ids": [1],
        "other_text": null
      },
      {
        "question_id": 2,
        "selected_option_ids": [1, 2],
        "other_text": "Better mobile support"
      }
    ]
  }'
```

### 3. Get Survey Results
```bash
curl http://localhost:8000/api/surveys/1/responses
```

## 📊 Expected Results

### Survey Creation Response
```json
{
  "id": 1,
  "title": "Customer Satisfaction Survey",
  "status": "draft",
  "created_at": "2025-08-26T08:04:27+05:30",
  "questions": [
    {
      "id": 1,
      "question_text": "How satisfied are you with our service?",
      "question_type": "single_choice",
      "options": [
        {"id": 1, "option_text": "Very satisfied"}
      ]
    }
  ]
}
```

### Survey Results Response
```json
{
  "survey": {
    "id": 1,
    "title": "Customer Satisfaction Survey",
    "total_responses": 25
  },
  "questions": [
    {
      "id": 1,
      "question_text": "How satisfied are you with our service?",
      "results": {
        "options": [
          {
            "id": 1,
            "option_text": "Very satisfied",
            "count": 15
          }
        ],
        "other_responses": []
      }
    }
  ]
}
```

## System Architecture

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   REST API      │    │   Business      │    │   Database      │
│   (Symfony)     │◄──►│   Logic Layer   │◄──►│   (Doctrine)    │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## Key Business Logic

### Survey Creation
- Validates survey title (3-255 characters)
- Ensures at least one question per survey
- Validates question text (5-500 characters)
- Enforces choice questions have options

### Response Submission
- Prevents duplicate responses per user per survey
- Validates required questions are answered
- Ensures selected options belong to questions

### Results Collection
- Returns survey submissions with count
- Calculates option counts

## Quality Assurance

```bash
# Run code quality checks
composer quality

# Individual quality checks
composer cs-check    # Code style check
composer cs-fix      # Fix code style issues
composer phpstan     # Static analysis
```

## Documentation

- [Database Schema](DATABASE_SCHEMA.md) - Relational model and relationships
- [API Documentation](API_DOCUMENTATION.md) - REST endpoints and examples
- [Extensibility Reflection](EXTENSIBILITY_REFLECTION.md) - How to extend for new question types and anonymous participation

---

**Built with Symfony 7.3 and PHP 8.3**
