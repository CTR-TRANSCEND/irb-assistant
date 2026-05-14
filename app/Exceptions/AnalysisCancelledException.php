<?php

declare(strict_types=1);

namespace App\Exceptions;

/**
 * SPEC-IRB-GUIDE-002: thrown by AnalyzeProjectJob's progress callback when
 * it observes that the analysis_run row's status was flipped to 'cancelling'
 * by the user (via POST /projects/{uuid}/analyze/cancel).
 *
 * ProjectAnalysisService catches this specifically (vs. the generic Throwable
 * handler) and marks the run as 'cancelled' instead of 'failed'. The user
 * sees a distinct UI treatment in the progress modal.
 */
class AnalysisCancelledException extends \RuntimeException {}
