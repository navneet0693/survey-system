<?php

namespace App\Entity;

/**
 * Question Type Enumeration.
 *
 * Defines the different types of questions that can be created in surveys.
 *
 * Supported Question Types:
 * - SINGLE_CHOICE: Radio button selection, only one option allowed
 * - MULTIPLE_CHOICE: Checkbox selection, multiple options allowed
 *
 * Extensibility:
 * - New question types can be added by extending this enum
 * - Each new type requires corresponding validation and aggregation logic
 *
 * @author navneet0693
 */
enum QuestionType: string
{
    case SINGLE_CHOICE = 'single_choice';
    case MULTIPLE_CHOICE = 'multiple_choice';
}
