<?php

namespace Crm\PaymentsModule\Gateways;

use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\ApplicationModule\Request;
use Crm\PaymentsModule\GatewayFail;
use Crm\PaymentsModule\RecurrentPaymentFailStop;
use Crm\PaymentsModule\RecurrentPaymentFailTry;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\UsersModule\Auth\UserManager;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Nette\Utils\Strings;
use Omnipay\Common\Exception\InvalidRequestException;
use Omnipay\Csob\Gateway;
use OndraKoupil\Csob\Exception;
use Psr\Log\LoggerInterface;
use Tracy\Debugger;

class CsobOneClick extends GatewayAbstract implements RecurrentPaymentInterface, ReusableCardPaymentInterface
{
    public const GATEWAY_CODE = 'csob_one_click';

    private const MERCHANT_BLOCKED = 120; // merchant is not authorised to accept payments
    private const PAYMENT_NOT_AUTHORIZED_ONECLICK_EXPIRED = 150; // orig payment not authorized, oneclick card expired
    private const MASTERPASS_SERVER_ERROR = 260; // masterPass payment can not be completed due to a technical error
    private const INTERNAL_ERROR = 260; // internal error in request processing

    private const ONECLICK_TEMPLATE_PAYMENT_EXPIRED = 710;
    private const ONECLICK_TEMPLATE_CARD_EXPIRED = 720;
    private const ONECLICK_TEMPLATE_CUSTOMER_REJECTED = 730;
    private const ONECLICK_TEMPLATE_PAYMENT_REVERSED = 740;

    private const SERVER_FAILURE_CODES = [
        self::MERCHANT_BLOCKED,
        self::MASTERPASS_SERVER_ERROR,
        self::INTERNAL_ERROR,
    ];

    protected Gateway $gateway;

    private $resultCode;

    private $resultMessage;

    private bool $purchaseMode = false;

    protected $cancelErrorCodes = [
        self::PAYMENT_NOT_AUTHORIZED_ONECLICK_EXPIRED,
        self::ONECLICK_TEMPLATE_PAYMENT_EXPIRED,
        self::ONECLICK_TEMPLATE_CARD_EXPIRED,
        self::ONECLICK_TEMPLATE_CUSTOMER_REJECTED,
        self::ONECLICK_TEMPLATE_PAYMENT_REVERSED,
    ];

    public function __construct(
        private LoggerInterface $logger,
        private PaymentMetaRepository $paymentMetaRepository,
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        Translator $translator
    ) {
        parent::__construct($linkGenerator, $applicationConfig, $httpResponse, $translator);
    }

    protected function initialize()
    {
        /** @var Gateway $gateway */
        $gateway = new Gateway();
        $this->gateway = $gateway;

        $this->gateway->setMerchantId($this->applicationConfig->get('csob_merchant_id'));
        $this->gateway->setShopName($this->applicationConfig->get('csob_shop_name'));
        $this->gateway->setBankPublicKeyFilePath($this->applicationConfig->get('csob_bank_public_key_file_path'));
        $this->gateway->setPrivateKeyFilePath($this->applicationConfig->get('csob_private_key_file_path'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('csob_mode') === 'live'));
        $this->gateway->setClosePayment(true);
        $this->gateway->setOneClickPayment(true);
        $this->gateway->setOneClickPaymentCheckbox(Gateway::ONECLICK_PAYMENT_CHECKBOX_HIDDEN_CHECKED);
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

        $requestParams = [
            'returnUrl' => $this->generateReturnUrl($payment, [
                'VS' => $payment->variable_symbol,
            ]),
            'transactionId' => $payment->variable_symbol,
            'cart' => $this->getCart($payment),
            'customerId' => UserManager::hashedUserId($payment->user->id),
            'email' => $payment->user->email,
            'createdAt' => $payment->user->created_at,
            'changedAt' => $payment->user->modified_at,
        ];

        if (!empty($payment->user->last_name)) {
            $requestParams['name'] = $this->getUserName($payment->user);
        }

        if ($this->purchaseMode) {
            $this->response = $this->gateway->purchase($requestParams)->send();
        } else {
            $this->response = $this->gateway->checkout($requestParams)->send();
        }

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
        if (!$result && $payId && !in_array((int) $data['status'], [
            Gateway::STATUS_CANCELLED,
            Gateway::STATUS_DENIED,
            Gateway::STATUS_REVERSED
        ], true)) {
            return null;
        }

        return $result;
    }

    private function getCart($payment)
    {
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

        return $cart;
    }

    /**
     * @param string $token
     * @return boolean
     */
    public function checkValid($token)
    {
        throw new InvalidRequestException("csob one click gateway doesn't support checking if token is still valid");
    }

    public function usePurchaseMode(): void
    {
        $this->purchaseMode = true;
    }

    /**
     * @param array $tokens
     * @return array [$cid => (DateTime)$expiration]
     */
    public function checkExpire($tokens)
    {
        $this->initialize();

        $result = [];
        foreach ($tokens as $token) {
            $response = $this->gateway->paymentStatus([
                'payId' => $token,
            ])->send();

            if (!$response->isSuccessful()) {
                continue;
            }

            $expiration = $response->getExpiration(); // mm/yy
            if (!$expiration) {
                continue;
            }

            $month = substr($expiration, 0, 2);
            $year = substr($expiration, 3, 2);
            $result[$token] = DateTime::from("$year-$month-01 00:00 next month");
        }

        return $result;
    }

    public function charge($payment, $token): string
    {
        $this->initialize();
        $clientIp = Request::getIp();

        $oneClickPaymentRequest = [
            'payId' => $token,
            'transactionId' => $payment->variable_symbol,
            'cart' => $this->getCart($payment),
            'email' => $payment->user->email,
            'createdAt' => $payment->user->created_at,
            'changedAt' => $payment->user->modified_at,
            'clientIp' => $clientIp,

            // This parameter doesn't make sense. CSOB requires it even for offline payments and even when the library
            // indicates that client is just not there (clientInitiated: false).
            'returnUrl' => $this->generateReturnUrl($payment, [
                'VS' => $payment->variable_symbol,
            ]),
        ];

        // clientIp for offline payment (cli) should be the same as the one used during initial payment
        // https://github.com/csob/paymentgateway/issues/471
        $initialPaymentMeta = $this->paymentMetaRepository->findByMeta('pay_id', $token);
        if ($clientIp === 'cli' && $initialPaymentMeta) {
            $oneClickPaymentRequest['clientIp'] = $initialPaymentMeta->payment->ip;
        }

        if (!empty($payment->user->last_name)) {
            $oneClickPaymentRequest['name'] = $this->getUserName($payment->user);
        }

        try {
            $this->response = $this->gateway->oneClickPayment($oneClickPaymentRequest)->send();
        } catch (\Exception $exception) {
            if ($exception instanceof Exception) {
                $this->resultCode = $exception->getCode();
                $this->resultMessage = $exception->getMessage();
            }

            // CSOB library doesn't return any response here and throws Exception on any kind of error.
            // We can't rely on the future checks to throw FailTry and we need to do it here manually.
            if (!in_array((int) $this->resultCode, self::SERVER_FAILURE_CODES, true)) {
                if ($this->hasStopRecurrentPayment($payment, $this->resultCode)) {
                    throw new RecurrentPaymentFailStop($exception->getMessage(), $exception->getCode());
                }
                Debugger::log($exception);
                throw new RecurrentPaymentFailTry($exception->getMessage(), $exception->getCode());
            }

            Debugger::log($exception);
            throw new GatewayFail($exception->getMessage(), $exception->getCode());
        }

        if ($payId = $this->response->getTransactionReference()) {
            $this->paymentMetaRepository->add($payment, 'pay_id', $payId);
        }

        $this->checkChargeStatus($payment, $this->getResultCode());

        return self::CHARGE_OK;
    }

    public function hasRecurrentToken(): bool
    {
        $data = $this->getResponseData();
        return isset($data['payId']);
    }

    /**
     * @return string
     */
    public function getRecurrentToken()
    {
        $data = $this->getResponseData();
        return $data['payId'];
    }

    /**
     * @return string
     */
    public function getResultCode()
    {
        $data = $this->getResponseData();
        return $data['resultCode'] ?? $this->resultCode;
    }

    /**
     * @return string
     */
    public function getResultMessage()
    {
        $data = $this->getResponseData();
        return $data['resultMessage'] ?? $this->resultMessage;
    }

    private function getUserName(ActiveRow $user): string
    {
        return Strings::trim(Strings::substring(
            "{$user->first_name} {$user->last_name}",
            0,
            45
        ));
    }

    public function isCardReusable(ActiveRow $recurrentPayment): bool
    {
        if (!isset($recurrentPayment->parent_payment->paid_at)) {
            return false;
        }

        // CSOB deactivates card token after 365 days without payment
        return $recurrentPayment->parent_payment->paid_at > new DateTime('-365 days');
    }
}
