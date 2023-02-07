<?php

namespace Crm\PaymentsModule\Hermes;

use Crm\PaymentsModule\Repository\RetentionAnalysisJobsRepository;
use Crm\PaymentsModule\Retention\RetentionAnalysis;
use Nette\Localization\Translator;
use Psr\Log\LoggerAwareTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class RetentionAnalysisJobHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    private static $currentJobId = null;

    private int $executionTimeLimit = 30; // in minutes

    public function __construct(
        private RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        private RetentionAnalysis $retentionAnalysis,
        private Translator $translator
    ) {
    }

    /**
     * Set maximum execution time of retention analysis (default is 30 minutes)
     *
     * @param int $minutes maximum execution time
     */
    public function setExecutionTimeLimit(int $minutes): void
    {
        $this->executionTimeLimit = $minutes;
    }

    public function handle(MessageInterface $message): bool
    {
        set_time_limit(60 * $this->executionTimeLimit);

        $payload = $message->getPayload();
        $job = $this->retentionAnalysisJobsRepository->find($payload['id']);

        if (!$job) {
            throw new \InvalidArgumentException("No retention analysis job with id #{$payload['id']} found.");
        }

        // Only first handler being run (in Hermes worker) should register a shutdown function.
        // This prevents multiple shutdown functions registration since worker may run without shutting down between jobs processing
        if (!self::$currentJobId) {
            // If execution takes too long, record it failed
            register_shutdown_function(function () {
                $longExecutionErrorMessage = $this->translator->translate('payments.admin.retention_analysis.errors.long_execution_time', ['minutes' => $this->executionTimeLimit]);
                $this->retentionAnalysisJobsRepository->setFailed(self::$currentJobId, $longExecutionErrorMessage);
            });
        }

        self::$currentJobId = $job->id;

        try {
            $this->retentionAnalysis->runJob($job);
        } catch (\Throwable $exception) {
            $this->retentionAnalysisJobsRepository->setFailed($job->id, $this->translator->translate('payments.admin.retention_analysis.errors.unexpected_error'));
            throw $exception;
        }

        return true;
    }
}
