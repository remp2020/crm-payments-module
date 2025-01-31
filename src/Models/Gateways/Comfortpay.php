<?php

namespace Crm\PaymentsModule\Models\Gateways;

use Crm\ApplicationModule\Models\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\CannotCheckExpiration;
use Crm\PaymentsModule\Models\GatewayFail;
use Crm\PaymentsModule\Models\TransactionStatus;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Application\LinkGenerator;
use Nette\Database\Table\ActiveRow;
use Nette\Http\Response;
use Nette\Localization\Translator;
use Nette\Utils\DateTime;
use Nette\Utils\Json;
use Nette\Utils\Strings;
use Omnipay\ComfortPay\Gateway;
use Omnipay\ComfortPay\Message\CardTransactionResponse;
use Omnipay\Omnipay;
use Tracy\Debugger;
use Tracy\ILogger;

class Comfortpay extends GatewayAbstract implements RecurrentPaymentInterface, CancellableTokenInterface
{
    public const GATEWAY_CODE = 'comfortpay';

    protected Gateway $gateway;

    public function __construct(
        LinkGenerator $linkGenerator,
        ApplicationConfig $applicationConfig,
        Response $httpResponse,
        Translator $translator,
        private readonly RecurrentPaymentsRepository $recurrentPaymentsRepository,
    ) {
        parent::__construct(
            linkGenerator: $linkGenerator,
            applicationConfig: $applicationConfig,
            httpResponse: $httpResponse,
            translator: $translator,
        );
    }

    protected function initialize()
    {
        /** @var Gateway $gateway */
        $gateway = Omnipay::create('ComfortPay');

        $this->gateway = $gateway;
        $this->gateway->setMid($this->applicationConfig->get('comfortpay_mid'));
        $this->gateway->setWs($this->applicationConfig->get('comfortpay_ws'));
        $this->gateway->setTerminalId($this->applicationConfig->get('comfortpay_terminalid'));
        $this->gateway->setSharedSecret($this->applicationConfig->get('comfortpay_sharedsecret'));
        $this->gateway->setTestMode(!($this->applicationConfig->get('comfortpay_mode') == 'live'));
        $this->gateway->setTestHost($this->testHost);
    }

    public function begin($payment)
    {
        $this->initialize();

        $name = null;
        if (!empty($payment->ref('user')->last_name)) {
            $name = Strings::webalize($payment->user->last_name . ' ' . $payment->user->first_name);
        }
        if (!$name) {
            $name = Strings::webalize($payment->user->public_name);
        }

        $name = substr($name, 0, 30);

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
            'rurl' => $this->generateReturnUrl($payment),
            'aredir' => true,
            'name' => $name,
            'lang' => \Locale::getPrimaryLanguage($payment->user->locale),
        ];

        $referenceEmail = $this->applicationConfig->get('comfortpay_rem');
        if ($referenceEmail) {
            $request['rem'] = $referenceEmail;
        }

        $this->response = $this->gateway->purchase($request)->send();
    }

    public function complete($payment): ?bool
    {
        $this->initialize();

        $request = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'currency' => $this->applicationConfig->get('currency'),
        ];

        $this->response = $this->gateway->completePurchase($request)->send();

        return $this->response->isSuccessful();
    }

    public function checkValid($token)
    {
        $this->initialize();

        $this->gateway->setCertPath($this->applicationConfig->get('comfortpay_local_cert_path'));
        $this->gateway->setCertPass($this->applicationConfig->get('comfortpay_local_passphrase_path'));

        $this->response = $this->gateway->checkCard(
            ['cardId' => $token]
        )->send();

        return $this->response->isSuccessful();
    }

    public function checkExpire($tokens)
    {
        $this->initialize();

        $this->gateway->setCertPath($this->applicationConfig->get('comfortpay_local_cert_path'));
        $this->gateway->setCertPass($this->applicationConfig->get('comfortpay_local_passphrase_path'));

        $response = [];

        // chunk requests to 1000 cards, max allowed limit allowed by Comfortpay
        foreach (array_chunk($tokens, 1000) as $chunk) {
            $this->response = $this->gateway->listOfExpirePerId(
                ['cardIds' => $chunk]
            )->send();

            if (!$this->response->isSuccessful()) {
                throw new CannotCheckExpiration();
            }

            foreach ($this->response->getData() as $card) {
                $month = substr($card['date'], 0, 2);
                $year = substr($card['date'], 2, 2);

                $response[$card['id']] = DateTime::from(strtotime("$year-$month-01 00:00 next month"));
            }
        }

        return $response;
    }

    public function charge($payment, $token): string
    {
        $this->initialize();

        $this->gateway->setCertPath($this->applicationConfig->get('comfortpay_local_cert_path'));
        $this->gateway->setCertPass($this->applicationConfig->get('comfortpay_local_passphrase_path'));

        $params = [
            'amount' => $payment->amount,
            'vs' => $payment->variable_symbol,
            'ss' => '0308',
            'currency' => $this->applicationConfig->get('currency'),
            'referedCardId' => $token,
            'transactionId' => $payment->id,
            'transactionType' => Gateway::TRANSACTION_TYPE_PURCHASE,
        ];

        try {
            $request = $this->gateway->charge($params);
            $this->response = $request->send();
        } catch (\Exception $exception) {
            $log = [
                'exception' => get_class($exception),
                'message' => $exception->getMessage(),
                'params' => $params,
            ];
            if ($exception instanceof \SoapFault) {
                $log['soap_fault_name'] = $exception->faultname ?? null;
                $log['soap_fault_code'] = $exception->faultcode ?? null;
                $log['soap_fault_string'] = $exception->faultstring ?? null;
                $log['soap_fault_actor'] = $exception->faultactor ?? null;
            }
            if (isset($request)) {
                $log['request'] = $request;
            }
            Debugger::log(Json::encode($log));
            throw new GatewayFail($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->checkChargeStatus($payment, $this->getResultCode());

        return self::CHARGE_OK;
    }

    public function getTransactionStatus(ActiveRow $payment): ?TransactionStatus
    {
        $this->initialize();

        $this->gateway->setCertPath($this->applicationConfig->get('comfortpay_local_cert_path'));
        $this->gateway->setCertPass($this->applicationConfig->get('comfortpay_local_passphrase_path'));

        $recurrentPayment = $this->recurrentPaymentsRepository->findByPayment($payment);
        if (!$recurrentPayment) {
            return null;
        }

        $request = [
            'transactionId' => $payment->id,
            'cid' => $recurrentPayment->payment_method->external_token,
        ];

        /** @var CardTransactionResponse $response */
        $response = $this->gateway->checkTransaction($request)->send();

        return new TransactionStatus(
            isSuccessful: $response->isSuccessful(),
            status: $response->getTransactionStatus() ?: null,
            message: $response->getTransactionApproval() ?: null,
        );
    }

    public function hasRecurrentToken(): bool
    {
        $data = $this->getResponseData();
        return isset($data['CID']) && isset($data['TRES']) && $data['TRES'] === 'OK';
    }

    public function getRecurrentToken()
    {
        $data = $this->getResponseData();
        return $data['CID'];
    }

    public function getResultCode(): ?string
    {
        $data = $this->getResponseData();
        return isset($data['transactionStatus']) ? $data['transactionStatus'] : null;
    }

    public function getResultMessage(): ?string
    {
        $data = $this->getResponseData();
        if (!isset($data['transactionApproval'])) {
            Debugger::log("Comfortpay response data doesn't include transactionApproval: " . Json::encode($data), ILogger::WARNING);
            return null;
        }
        return $data['transactionApproval'];
    }

    public function isNotSettled()
    {
        $data = $this->getResponseData();
        if (!isset($data['RES'])) {
            Debugger::log("Comfortpay response data doesn't include RES: " . Json::encode($data), ILogger::WARNING);
        }
        return $data['RES'] === "TOUT";
    }

    public function cancelToken(string $token): bool
    {
        $this->initialize();

        $this->gateway->setCertPath($this->applicationConfig->get('comfortpay_local_cert_path'));
        $this->gateway->setCertPass($this->applicationConfig->get('comfortpay_local_passphrase_path'));

        $this->response = $this->gateway->unRegisterCard(
            ['cardId' => $token]
        )->send();

        return $this->response->isSuccessful();
    }
}
