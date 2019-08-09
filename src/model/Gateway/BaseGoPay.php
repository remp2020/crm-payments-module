<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Hermes\HermesMessage;
use Crm\PaymentsModule\Events\RecurrentPaymentRenewedEvent;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Database\Table\IRow;
use Omnipay\GoPay\Gateway;
use Omnipay\Omnipay;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Nette\Application\LinkGenerator;
use Nette\Http\Response;
use Nette\Localization\ITranslator;
use Tomaj\Hermes\Emitter;

abstract class BaseGoPay extends GatewayAbstract implements PaymentInterface
{
    // https://doc.gopay.com/cs/#stavy-plateb
    const STATE_PAID = 'PAID';

    // https://doc.gopay.com/en/#payment-substate
    const PENDING_PAYMENT_SUB_STATE = ['_101', '_102'];

    /** @var Gateway */
    protected $gateway;

    protected $paymentMetaRepository;

    protected $paymentsRepository;

    protected $recurrentPaymentsRepository;

    protected $emitter;

    protected $eventEmitter;

    protected $eetEnabled = false;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        ITranslator $translator,
        PaymentMetaRepository $paymentMetaRepository,
        PaymentsRepository $paymentsRepository,
        RecurrentPaymentsRepository $recurrentPaymentsRepository,
        Emitter $emitter,
        \League\Event\Emitter $eventEmitter
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
        $this->paymentMetaRepository = $paymentMetaRepository;
        $this->paymentsRepository = $paymentsRepository;
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
        $this->emitter = $emitter;
        $this->eventEmitter = $eventEmitter;
    }

    protected function initialize()
    {
        $this->gateway = Omnipay::create('GoPay');

        $this->gateway->initialize([
            'goId' => $this->applicationConfig->get('gopay_go_id'),
            'clientId' => $this->applicationConfig->get('gopay_client_id'),
            'clientSecret' => $this->applicationConfig->get('gopay_client_secret'),
            'testMode' => !($this->applicationConfig->get('gopay_mode') == 'live'),
        ]);

        if ($this->applicationConfig->get('gopay_eet_enabled')) {
            $this->eetEnabled = true;
        }
    }

    public function begin($payment)
    {
        $this->initialize();

        $goPayOrder = $this->preparePaymentData($payment);

        $this->response = $this->gateway->purchase($goPayOrder);

        if ($this->response->isSuccessful()) {
            $this->paymentMetaRepository->add($payment, 'gopay_transaction_id', $this->response->getTransactionId());
            $this->paymentMetaRepository->add($payment, 'gopay_transaction_reference', $this->response->getTransactionReference());
        }
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $reference = $this->paymentMetaRepository->values($payment, 'gopay_transaction_reference')->limit(1)->fetch();
        if (!$reference) {
            throw new \Exception('Cannot find gopay_transaction_reference for payment - ' . $payment->id);
        }

        $request = [
            'transactionReference' => $reference->value,
        ];

        $this->response = $this->gateway->completePurchase($request);

        $data = $this->response->getData();
        $this->setMeta($payment, $data);

        if (isset($data['sub_state']) && in_array($data['sub_state'], self::PENDING_PAYMENT_SUB_STATE)) {
            return null;
        }

        return $data['state'] == self::STATE_PAID;
    }

    protected function handleSuccessful($response)
    {
        header('Location: ' . $response->getRedirectUrl());
        exit;
    }

    protected function setMeta($payment, array $data)
    {
        $this->paymentMetaRepository->add($payment, 'gopay_state', $data['state']);
        if (isset($data['sub_state'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_sub_state', $data['sub_state']);
        }
        if (isset($data['payment_instrument'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_payment_instrument', $data['payment_instrument']);
        }
        if (isset($data['payer']['payment_card'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_card_number', $data['payer']['payment_card']['card_number']);
            $this->paymentMetaRepository->add($payment, 'gopay_card_expiration', $data['payer']['payment_card']['card_expiration']);
            $this->paymentMetaRepository->add($payment, 'gopay_card_brand', $data['payer']['payment_card']['card_brand']);
            $this->paymentMetaRepository->add($payment, 'gopay_issuer_country', $data['payer']['payment_card']['card_issuer_country']);
            $this->paymentMetaRepository->add($payment, 'gopay_issuer_bank', $data['payer']['payment_card']['card_issuer_bank']);
        }
        if (isset($data['payer']['bank_account'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_account_number', $data['payer']['bank_account']['account_number']);
            $this->paymentMetaRepository->add($payment, 'gopay_bank_code', $data['payer']['bank_account']['bank_code']);
            $this->paymentMetaRepository->add($payment, 'gopay_account_name', $data['payer']['bank_account']['account_name']);
        }
        if (isset($data['payer']['contact']['email'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_contact_email', $data['payer']['contact']['email']);
        }
        if (isset($data['payer']['contact']['country_code'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_contact_country_code', $data['payer']['contact']['country_code']);
        }
        $this->paymentMetaRepository->add($payment, 'gopay_url', $data['gw_url']);
        if (isset($data['recurrence'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_recurrence_cycle', $data['recurrence']['recurrence_cycle']);
            $this->paymentMetaRepository->add($payment, 'gopay_recurrence_date_to', $data['recurrence']['recurrence_date_to']);
            $this->paymentMetaRepository->add($payment, 'gopay_recurrence_state', $data['recurrence']['recurrence_state']);
        }
        if (isset($data['eet'])) {
            $this->paymentMetaRepository->add($payment, 'gopay_eet_fik', $data['recurrence']['eet']['fik']);
            $this->paymentMetaRepository->add($payment, 'gopay_eet_bkp', $data['recurrence']['eet']['bkp']);
            $this->paymentMetaRepository->add($payment, 'gopay_eet_pkp', $data['recurrence']['eet']['pkp']);
        }
    }

    protected function preparePaymentData(IRow $payment): array
    {
        $returnUrl = $this->linkGenerator->link(
            'Payments:Return:goPay'
        );
        $notifyUrl = $this->linkGenerator->link(
            'Api:Api:api',
            [
                'version' => 1,
                'category' => 'payments',
                'apiaction' => 'gopay-notification',
            ]
        );

        $paymentItems = $payment->related('payment_items');
        $items = $this->prepareItems($paymentItems);
        $description = $this->prepareDescription($paymentItems, $payment);

        $data = [
            'purchaseData' => [
                'payer' => [
                    'default_payment_instrument' => 'PAYMENT_CARD',
                    'contact' => [
                        'email' => $payment->user->email,
                    ]
                ],
                'target' => [
                    'type' => 'ACCOUNT',
                    'goid' => $this->applicationConfig->get('gopay_go_id'),
                ],
                'amount' => intval(round($payment->amount * 100)),
                'currency' => $this->applicationConfig->get('currency'),
                'order_number' => $payment->variable_symbol,
                'order_description' => $description,
                'items' => $items,
                'callback' => [
                    'return_url' => $returnUrl,
                    'notification_url' => $notifyUrl,
                ],
            ],
        ];

        if ($this->eetEnabled) {
            $data['purchaseData']['eet'] = $this->prepareEetItems($paymentItems);
        }

        return $data;
    }

    protected function prepareItems($paymentItems): array
    {
        $items = [];
        foreach ($paymentItems as $paymentItem) {
            $items[] = [
                'count' => $paymentItem->count,
                'name' => $paymentItem->name,
                'amount' => intval(round($paymentItem->amount * $paymentItem->count * 100)),
            ];
        }
        return $items;
    }

    protected function prepareEetItems($paymentItems): array
    {
        $eet = [
            'celk_trzba' => 0,
            'mena' => $this->applicationConfig->get('currency'),
        ];
        foreach ($paymentItems as $paymentItem) {
            // EET specialiaty - see dokumentacia https://doc.gopay.com/cs/#eet - we have three fixed vat level
            // if it will be changed in future we need to update this "pretty" code
            $eet['celk_trzba'] += intval(round($paymentItem->amount * $paymentItem->count * 100));
            if ($paymentItem->vat == 21) {
                if (!isset($eet['zakl_dan1'])) {
                    $eet['zakl_dan1'] = 0;
                }
                if (!isset($eet['dan1'])) {
                    $eet['dan1'] = 0;
                }
                $total = $paymentItem->amount * $paymentItem->count;
                $base = intval(round($total / (1 + $paymentItem->vat / 100) * 100));
                $vat = intval(round($total * 100 - $base));
                $eet['zakl_dan1'] += $base;
                $eet['dan1'] += $vat;
            } elseif ($paymentItem->vat == 15) {
                if (!isset($eet['zakl_dan2'])) {
                    $eet['zakl_dan2'] = 0;
                }
                if (!isset($eet['dan2'])) {
                    $eet['dan2'] = 0;
                }
                $total = $paymentItem->amount * $paymentItem->count;
                $base = intval(round($total / (1 + $paymentItem->vat / 100) * 100));
                $vat = intval(round($total * 100 - $base));
                $eet['zakl_dan2'] += $base;
                $eet['dan2'] += $vat;
            } elseif ($paymentItem->vat == 10) {
                if (!isset($eet['zakl_dan3'])) {
                    $eet['zakl_dan3'] = 0;
                }
                if (!isset($eet['dan3'])) {
                    $eet['dan3'] = 0;
                }
                $total = $paymentItem->amount * $paymentItem->count;
                $base = intval(round($total / (1 + $paymentItem->vat / 100) * 100));
                $vat = intval(round($total * 100 - $base));
                $eet['zakl_dan3'] += $base;
                $eet['dan3'] += $vat;
            } elseif ($paymentItem->vat == 0) {
                if (!isset($eet['zakl_nepodl_dph'])) {
                    $eet['zakl_nepodl_dph'] = 0;
                }
                $eet['zakl_nepodl_dph'] += intval(round($paymentItem->amount * $paymentItem->count * 100));
            } else {
                throw new \Exception("Unknown vat rate '{$paymentItem->vat}' for EET reporting");
            }
        }
        return $eet;
    }

    protected function prepareDescription($paymentItems, IRow $payment): string
    {
        if (count($paymentItems) == 1) {
            foreach ($paymentItems as $paymentItem) {
                return $paymentItem->name . ' / ' . $payment->variable_symbol;
            }
        }
        return $this->applicationConfig->get('site_title');
    }
}
