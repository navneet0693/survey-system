<?php

namespace App\Entity;

/**
 * Survey Status Enumeration.
 *
 * Defines the possible states a survey can be in throughout its lifecycle.
 * This enum ensures type safety and prevents invalid status values.
 *
 * Status Flow:
 * - DRAFT: Survey is being created/edited, not yet available to respondents
 * - ACTIVE: Survey is live and accepting responses from tenants
 * - CLOSED: Survey is no longer accepting responses, results can be viewed
 *
 * @author navneet0693
 */
enum SurveyStatus: string
{
    case DRAFT = 'draft';
    case ACTIVE = 'active';
    case CLOSED = 'closed';
}
