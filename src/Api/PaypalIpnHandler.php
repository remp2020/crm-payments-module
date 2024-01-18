<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\ApiParamsValidatorInterface;
use Crm\ApiModule\Models\Response\EmptyResponse;
use Crm\ApplicationModule\Config\ApplicationConfig;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use League\Fractal\ScopeFactoryInterface;
use Nette\Http\Response;
use Tomaj\NetteApi\Params\PostInputParam;
use Tomaj\NetteApi\Params\RawInputParam;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;
use Tracy\ILogger;

class PaypalIpnHandler extends ApiHandler implements ApiParamsValidatorInterface
{
    const CONF_EMAIL_KEY = 'paypal_ipn_email';
    const CONF_BASE_URL_KEY = 'paypal_ipn_baseurl';

    const PARAM_STATUS = 'payment_status';
    const PARAM_INVOICE = 'invoice';
    const PARAM_EMAIL = 'receiver_email';
    const PARAM_AMOUNT = 'mc_gross';
    const PARAM_RAW = 'raw';

    public function __construct(
        private PaymentsRepository $paymentsRepository,
        private PaymentProcessor $paymentProcessor,
        private ApplicationConfig $applicationConfig,
        ScopeFactoryInterface $scopeFactory = null
    ) {
        parent::__construct($scopeFactory);
    }

    public function params(): array
    {
        return [
            new PostInputParam(self::PARAM_STATUS),
            new PostInputParam(self::PARAM_INVOICE),
            new PostInputParam(self::PARAM_EMAIL),
            new PostInputParam(self::PARAM_AMOUNT),
            new RawInputParam(self::PARAM_RAW)
        ];
    }

    public function validateParams(array $params): ?ResponseInterface
    {
        // param checking is here instead of the `params` method to avoid returning an error response to PayPal,
        // which would result in PayPal resending the notification
        if (!isset($params[self::PARAM_INVOICE]) ||
            !isset($params[self::PARAM_STATUS]) ||
            !isset($params[self::PARAM_EMAIL]) ||
            !isset($params[self::PARAM_AMOUNT])) {
            Debugger::log('Missing params', 'paypal-ipn');

            // invalid message, ignoring
            return $this->emptyResponse();
        }

        return null;
    }

    /**
     * @throws Exception|GuzzleException
     */
    public function handle(array $params): ResponseInterface
    {
        $email = $params[self::PARAM_EMAIL];
        $cfgEmail = $this->applicationConfig->get(self::CONF_EMAIL_KEY);
        if ($cfgEmail && $email !== $cfgEmail) {
            Debugger::log('Wrong IPN email: ' . $email, 'paypal-ipn');

            // invalid message, ignoring
            return $this->emptyResponse();
        }

        $vs = $params[self::PARAM_INVOICE];
        $payment = $this->paymentsRepository->findByVs($vs);
        if (!$payment) {
            Debugger::log("Payment {$vs} not found.", 'paypal-ipn');

            // return OK to prevent resending of this notification
            return $this->emptyResponse();
        }

        $status = $params[self::PARAM_STATUS];
        if ($status !== 'Completed') {
            Debugger::log("Wrong status {$status}.", 'paypal-ipn');

            // return OK to prevent resending of this notification
            return $this->emptyResponse();
        }

        $amount = $params[self::PARAM_AMOUNT];
        if ($amount != $payment->amount) {
            Debugger::log("Wrong amount {$amount}, should be {$payment->amount}.", 'paypal-ipn');

            // return OK to prevent resending of this notification
            return $this->emptyResponse();
        }

        // verify the notification
        $baseUrl = $this->applicationConfig->get(self::CONF_BASE_URL_KEY);
        if (!$baseUrl) {
            Debugger::log('Missing baseUrl config', ILogger::WARNING);
            throw new \Exception('Missing baseUrl config');
        }
        $rawPostData = $params[self::PARAM_RAW];
        $req = 'cmd=_notify-validate&' . $rawPostData;
        $client = new Client([
            'base_uri' => $baseUrl
        ]);
        $response = $client->post('/cgi-bin/webscr', [
            'body' => $req,
            'headers' => [
                'Content-Type' => 'application/x-www-form-urlencoded',
            ]
        ]);

        $result = $response->getBody()->getContents();
        if ($result === 'VERIFIED') {
            try {
                $this->paymentProcessor->complete($payment, function ($payment, GatewayAbstract $gateway) {
                    // no need to do anything...
                });
            } catch (Exception $ex) {
                Debugger::log('paypal raw data: ' . $rawPostData, 'paypal-ipn');
                throw $ex;
            }
        }

        return $this->emptyResponse();
    }

    private function emptyResponse(): EmptyResponse
    {
        $response = new EmptyResponse();
        $response->setCode(Response::S200_OK); // required by PayPal
        return $response;
    }
}
