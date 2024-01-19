<?php

namespace Crm\PaymentsModule\Hermes;

use Crm\ApplicationModule\Application\Managers\ApplicationMountManager;
use Crm\PaymentsModule\Models\AdminFilterFormData;
use Crm\PaymentsModule\Models\FileSystem;
use League\Csv\ByteSequence;
use League\Csv\Writer;
use Nette\Utils\Random;
use Tomaj\Hermes\Handler\HandlerInterface;
use Tomaj\Hermes\MessageInterface;
use Tracy\Debugger;

class ExportPaymentsHandler implements HandlerInterface
{
    private $adminFilterFormData;

    private $adminMountManager;

    public function __construct(
        ApplicationMountManager $adminMountManager,
        AdminFilterFormData $adminFilterFormData
    ) {
        $this->adminFilterFormData = $adminFilterFormData;
        $this->adminMountManager = $adminMountManager;
    }

    public function handle(MessageInterface $message): bool
    {
        $payload = $message->getPayload();
        $this->adminFilterFormData->parse($payload['form_data']);

        $fileName = 'payments_export_' . date('y-m-d-H-i-') . Random::generate(6) . '.csv';

        $tmpFile = tmpfile();

        $writer = Writer::createFromStream($tmpFile);
        $writer->setDelimiter(';');
        $writer->setEnclosure('"');
        $writer->setOutputBOM(ByteSequence::BOM_UTF8);

        // hopefully temporary, League\Csv\Stream ignores output BOM set on the writer
        fwrite($tmpFile, ByteSequence::BOM_UTF8);

        $writer->insertOne([
            'id',
            'created_at',
            'variable_symbol',
            'amount',
            'status',
            'paid_at',
            'payment_gateway',
            'subscription_id',
            'subscription_type',
            'email',
            'referer'
        ]);

        $lastId = 0;
        while (true) {
            $payments = $this->adminFilterFormData->filteredPayments()
                ->where('payments.id > ?', $lastId)
                ->order('payments.id ASC')
                ->limit(1000)
                ->fetchAll();

            if (!count($payments)) {
                break;
            }

            foreach ($payments as $payment) {
                $writer->insertOne([
                    $payment->id,
                    $payment->created_at,
                    $payment->variable_symbol,
                    $payment->amount,
                    $payment->status,
                    $payment->paid_at,
                    $payment->payment_gateway->code ?? null,
                    $payment->subscription_id,
                    $payment->subscription_type->code ?? null,
                    $payment->user->email ?? null,
                    $payment->referer
                ]);
                $lastId = $payment->id;
            }
        }

        try {
            $filePath = $this->adminMountManager->getFilePath(FileSystem::PAYMENTS_EXPORTS_BUCKET_NAME, $fileName);
            $this->adminMountManager->writeStream($filePath, $tmpFile);
        } catch (\Exception $e) {
            Debugger::log($e, Debugger::EXCEPTION);
            return false;
        } finally {
            fclose($tmpFile);
        }

        return true;
    }
}
