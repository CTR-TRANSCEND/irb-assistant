<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * Thrown when a Submission status transition is rejected by domain rules.
 *
 * Primary rule: tracking_only is a terminal state — no transition out of it.
 * Per REQ-IRB-FORMSV2-014a.
 */
class InvalidSubmissionStateTransition extends \DomainException {}
