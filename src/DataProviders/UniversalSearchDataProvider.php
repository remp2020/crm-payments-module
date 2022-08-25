<?php

namespace Crm\PaymentsModule\DataProvider;

use Contributte\Translation\Translator;
use Crm\AdminModule\Model\UniversalSearchDataProviderInterface;
use Crm\ApplicationModule\Helpers\UserDateHelper;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Nette\Application\LinkGenerator;

class UniversalSearchDataProvider implements UniversalSearchDataProviderInterface
{
    private PaymentsRepository $paymentsRepository;
    private LinkGenerator $linkGenerator;
    private Translator $translator;
    private UserDateHelper $userDateHelper;

    public function __construct(
        PaymentsRepository $paymentsRepository,
        LinkGenerator $linkGenerator,
        Translator $translator,
        UserDateHelper $userDateHelper
    ) {
        $this->paymentsRepository = $paymentsRepository;
        $this->linkGenerator = $linkGenerator;
        $this->translator = $translator;
        $this->userDateHelper = $userDateHelper;
    }

    public function provide(array $params): array
    {
        $result = [];
        $term = $params['term'];
        $groupName = $this->translator->translate('payments.data_provider.universal_search.payment_group');

        $payments = $this->paymentsRepository->findAllByVS($term)
            ->order('paid_at DESC')
            ->fetchAll();
        foreach ($payments as $payment) {
            $text = "{$payment->user->email} - {$payment->variable_symbol}";
            if ($payment->paid_at) {
                $text .= ' - ' . $this->userDateHelper->process($payment->paid_at);
            }
            $result[$groupName][] = [
                'id' => 'payment_' . $payment->id,
                'text' => $text,
                'url' => $this->linkGenerator->link('Users:UsersAdmin:show', ['id' => $payment->user_id])
            ];
        }

        return $result;
    }
}
