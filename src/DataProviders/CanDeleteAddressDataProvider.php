<?php

namespace Crm\PaymentsModule\DataProviders;

use Contributte\Translation\Translator;
use Crm\ApplicationModule\DataProvider\DataProviderException;
use Crm\UsersModule\DataProviders\CanDeleteAddressDataProviderInterface;
use Nette\Application\LinkGenerator;

class CanDeleteAddressDataProvider implements CanDeleteAddressDataProviderInterface
{
    private $translator;

    private $linkGenerator;

    public function __construct(
        Translator $translator,
        LinkGenerator $linkGenerator
    ) {
        $this->translator = $translator;
        $this->linkGenerator = $linkGenerator;
    }

    public function provide(array $params): array
    {
        if (!isset($params['address'])) {
            throw new DataProviderException('address param missing');
        }

        $payments = $params['address']->related('payments')->fetchAll();
        if (count($payments) > 0) {
            $listPayments = array_map(function ($payment) {
                $link = $this->linkGenerator->link('Payments:PaymentsAdmin:edit', ['id' => $payment->id]);

                return "<a target='_blank' href='{$link}'><i class='fa fa-edit'></i>{$payment->id}</a>";
            }, $payments);

            return [
                'canDelete' => false,
                'message' => $this->translator->translate(
                    'payments.admin.address.cant_delete',
                    count($payments),
                    [ 'payments' => implode(', ', $listPayments) ]
                )
            ];
        }

        return [
            'canDelete' => true
        ];
    }
}
