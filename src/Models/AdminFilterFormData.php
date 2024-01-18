<?php


namespace Crm\PaymentsModule\Models;

use Crm\ApplicationModule\DataProvider\DataProviderManager;
use Crm\PaymentsModule\DataProviders\AdminFilterFormDataProviderInterface;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Nette\Utils\DateTime;

class AdminFilterFormData
{
    private $formData;

    public function __construct(
        private DataProviderManager $dataProviderManager,
        private PaymentsRepository $paymentsRepository
    ) {
    }

    public function parse($formData)
    {
        $this->formData = $formData;
    }

    public function filteredPayments()
    {
        $payments = $this->paymentsRepository->all(
            $this->getText(),
            $this->getPaymentGateway(),
            $this->getSubscriptionType(),
            $this->getStatus(),
            null,
            null,
            null,
            $this->getDonation(),
            $this->getRecurrentChargeFilterValue(),
            $this->getReferer()
        );

        if ($this->getId()) {
            $payments->where('payments.id = ?', $this->getId());
        }

        if ($this->getPaidAtFrom()) {
            $payments->where('paid_at >= ?', DateTime::from($this->getPaidAtFrom()));
        }

        if ($this->getPaidAtTo()) {
            $payments->where('paid_at < ?', DateTime::from($this->getPaidAtTo()));
        }

        /** @var AdminFilterFormDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.payments_filter_form', AdminFilterFormDataProviderInterface::class);
        foreach ($providers as $sorting => $provider) {
            $payments = $provider->filter($payments, $this->formData);
        }

        return $payments;
    }

    public function getFormValues()
    {
        return [
            'id' => $this->getId(),
            'text' => $this->getText(),
            'payment_gateway' => $this->getPaymentGateway(),
            'paid_at_from' => $this->getPaidAtFrom(),
            'paid_at_to' => $this->getPaidAtTo(),
            'subscription_type' => $this->getSubscriptionType(),
            'status' => $this->getStatus(),
            'donation' => $this->getDonation(),
            'recurrent_charge' => $this->getRecurrentCharge(),
            'referer' => $this->getReferer(),
            'external_id' => $this->getExternalId(),
        ];
    }

    private function getId()
    {
        return $this->formData['id'] ?? null;
    }

    private function getText()
    {
        return $this->formData['text'] ?? null;
    }

    private function getPaymentGateway()
    {
        return $this->formData['payment_gateway'] ?? null;
    }

    private function getSubscriptionType()
    {
        return $this->formData['subscription_type'] ?? null;
    }

    private function getStatus()
    {
        return $this->formData['status'] ?? null;
    }

    private function getDonation()
    {
        return $this->formData['donation'] ?? null;
    }

    private function getRecurrentCharge()
    {
        return $this->formData['recurrent_charge'] ?? null;
    }

    private function getPaidAtFrom()
    {
        return $this->formData['paid_at_from'] ?? null;
    }

    private function getPaidAtTo()
    {
        return $this->formData['paid_at_to'] ?? null;
    }

    private function getReferer()
    {
        return $this->formData['referer'] ?? null;
    }

    private function getExternalId()
    {
        return $this->formData['external_id'] ?? null;
    }

    private function getRecurrentChargeFilterValue()
    {
        $recurrentChargeValues = [
            'all' => null,
            'recurrent' => true,
            'manual' => false,
        ];

        return $recurrentChargeValues[$this->getRecurrentCharge()] ?? null;
    }
}
