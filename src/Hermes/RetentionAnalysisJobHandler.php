<?php

namespace Crm\PaymentsModule\Hermes;

use Crm\PaymentsModule\Repository\RetentionAnalysisJobsRepository;
use Crm\PaymentsModule\Retention\RetentionAnalysis;
use Nette\Localization\ITranslator;
use Psr\Log\LoggerAwareTrait;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;

class RetentionAnalysisJobHandler implements HandlerInterface
{
    use LoggerAwareTrait;

    private $executionTimeLimit = 30; // in minutes

    private $retentionAnalysisJobsRepository;

    private $retentionAnalysis;

    private $translator;

    public function __construct(
        RetentionAnalysisJobsRepository $retentionAnalysisJobsRepository,
        RetentionAnalysis $retentionAnalysis,
        ITranslator $translator
    ) {
        $this->retentionAnalysisJobsRepository = $retentionAnalysisJobsRepository;
        $this->retentionAnalysis = $retentionAnalysis;
        $this->translator = $translator;
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

        // If execution takes too long, record it failed
        register_shutdown_function(function () use ($job) {
            $longExecutionErrorMessage = $this->translator->translate('payments.admin.retention_analysis.errors.long_execution_time', ['minutes' => $this->executionTimeLimit]);
            $this->retentionAnalysisJobsRepository->setFailed($job, $longExecutionErrorMessage);
        });

        try {
            $this->retentionAnalysis->runJob($job);
        } catch (\Throwable $exception) {
            $this->retentionAnalysisJobsRepository->setFailed($job, $this->translator->translate('payments.admin.retention_analysis.errors.unexpected_error'));
            throw $exception;
        }

        return true;
    }
}
