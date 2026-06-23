<?php

declare(strict_types=1);

namespace zRoute\Examples\Controllers;

use zRoute\Request;

/**
 * Example process controller demonstrating a separate controller class.
 */
class ExampleProcessController
{
    /**
     * POST /package-prefix/process
     *
     * Triggers a background job.  Body params: job_type, async, payload.
     */
    public function run(Request $request): string
    {
        $jobType = $request->getParam('job_type');
        $async   = $request->getParam('async', false) ? 'true' : 'false';

        return "process: job_type={$jobType} async={$async}";
    }
}
