<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Utils\Strings;
use Omnipay\Csob\Gateway;
use Omnipay\Omnipay;
use Psr\Log\LoggerInterface;

class Csob extends GatewayAbstract
{
    public const GATEWAY_CODE = 'csob';

    /** @var \Omnipay\Csob\Gateway */
    protected $gateway;

    private $paymentMetaRepository;

    private $logger;

    public function __construct(
        LoggerInterface $logger,
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        Translator $translator,
        PaymentMetaRepository $paymentMetaRepository
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
        $this->logger = $logger;
        $this->paymentMetaRepository = $paymentMetaRepository;
    }

    protected function initialize()
    {
        $this->gateway = Omnipay::create('Csob');
        $this->gateway->setMerchantId($this->applicationConfig->get('csob_merchant_id'));
        $this->gateway->setShopName($this->applicationConfig->get('csob_shop_name'));
        $this->gateway->setBankPublicKeyFilePath($this->applicationConfig->get('csob_bank_public_key_file_path'));
        $this->gateway->setPrivateKeyFilePath($this->applicationConfig->get('csob_private_key_file_path'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('csob_mode') === 'live'));
        $this->gateway->setClosePayment(true);
        $this->gateway->setOneClickPayment(false);
        $this->gateway->setOneClickPaymentCheckbox(Gateway::ONECLICK_PAYMENT_CHECKBOX_HIDDEN_UNCHECKED);
        $this->gateway->setDisplayOmnibox(false);
        $this->gateway->setCurrency('CZK'); // TODO: replace with system currency once implemented
        $this->gateway->setLanguage('CZ');
        $this->gateway->setTraceLog(function ($message) {
            $this->logger->info($message);
        });
    }

    public function begin($payment)
    {
        $this->initialize();

        $cart = [];
        $paymentItemsCount = $payment->related('payment_items')->count('*');

        // CSOB api limits number of cart products to 2 (https://github.com/csob/paymentgateway/wiki/eAPI-v1.7#cart-items)
        // for now we bundle the products under one cart item if we need to
        if ($paymentItemsCount > 2) {
            $totalAmount = 0;
            $itemsCount = 0;
            foreach ($payment->related('payment_items') as $paymentItem) {
                $totalAmount += $paymentItem->amount * $paymentItem->count;
                $itemsCount += $paymentItem->count;
            }
            $cart[] = [
                'name' => $this->applicationConfig->get('csob_shop_name') ?? $this->applicationConfig->get('site_title'),
                'quantity' => $itemsCount,
                'price' => $totalAmount,
            ];
        } else {
            foreach ($payment->related('payment_items') as $paymentItem) {
                $cart[] = [
                    'name' => $paymentItem->name,
                    'quantity' => $paymentItem->count,
                    'price' => $paymentItem->amount * $paymentItem->count,
                ];
            }
        }

        $checkoutRequest = [
            'returnUrl' => $this->generateReturnUrl($payment, [
                'VS' => $payment->variable_symbol,
            ]),
            'transactionId' => $payment->variable_symbol,
            'cart' => $cart,
            'customerId' => UserManager::hashedUserId($payment->user->id),
            'email' => $payment->user->email,
            'createdAt' => $payment->user->created_at,
            'changedAt' => $payment->user->modified_at,
        ];

        if (!empty($payment->user->last_name)) {
            $checkoutRequest['name'] = $this->getUserName($payment->user);
        }

        $this->response = $this->gateway->checkout($checkoutRequest)->send();
        $this->paymentMetaRepository->add($payment, 'pay_id', $this->response->getTransactionReference());
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $payId = $this->paymentMetaRepository->getTable()
            ->where('payment_id = ?', $payment->id)
            ->where('key = ?', 'pay_id')
            ->fetch();

        $this->response = $this->gateway->completePurchase([
            'amount' => $payment->amount,
            'currency' => $this->applicationConfig->get('currency'),
            'transactionId' => $payment->variable_symbol,
            'payId' => $payId ? $payId->value : null,
        ])->send();

        $result = $this->response->isSuccessful();
        $data = $this->response->getData();

        // we don't want to return unsuccessful result, if response returned one of error codes
        // because it was just an attempt to confirm offline payment
        if (!$result && $payId && !in_array($data['status'], [
                Gateway::STATUS_CANCELLED,
                Gateway::STATUS_DENIED,
                Gateway::STATUS_REVERSED])
        ) {
            return null;
        }

        return $result;
    }

    private function getUserName(ActiveRow $user): string
    {
        return Strings::trim(Strings::substring(
            "{$user->first_name} {$user->last_name}",
            0,
            45
        ));
    }
}
