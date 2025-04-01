<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Models\DataProvider\DataProviderManager;
use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\DataProviders\PaymentReturnGatewayDataProviderInterface;
use Crm\PaymentsModule\Models\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Models\Payment\PaymentStatusEnum;
use Crm\PaymentsModule\Models\PaymentProcessor;
use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectManager;
use Crm\PaymentsModule\Models\SuccessPageResolver\PaymentCompleteRedirectResolver;
use Crm\PaymentsModule\Repositories\PaymentLogsRepository;
use Crm\PaymentsModule\Repositories\PaymentMetaRepository;
use Crm\PaymentsModule\Repositories\PaymentsRepository;
use Crm\UsersModule\Models\User\UserData;
use Crm\UsersModule\Repositories\UserMetaRepository;
use Crm\ViamoModule\Gateways\Viamo;
use Nette\Application\Attributes\Persistent;
use Nette\DI\Attributes\Inject;
use Nette\Database\Table\ActiveRow;

class ReturnPresenter extends FrontendPresenter
{
    #[Inject]
    public PaymentsRepository $paymentsRepository;

    #[Inject]
    public PaymentLogsRepository $paymentLogsRepository;

    #[Inject]
    public PaymentProcessor $paymentProcessor;

    #[Inject]
    public UserMetaRepository $userMetaRepository;

    #[Inject]
    public PaymentMetaRepository $paymentMetaRepository;

    #[Inject]
    public PaymentCompleteRedirectManager $paymentCompleteRedirectManager;

    #[Inject]
    public UserData $userData;

    #[Inject]
    public DataProviderManager $dataProviderManager;

    #[Persistent]
    public $VS;

    public function startup()
    {
        parent::startup();
        if (isset($this->params['vs'])) {
            $this->VS = $this->params['vs'];
        } elseif (isset($_POST['VS'])) {
            $this->VS = $_POST['VS'];
        } elseif (isset($_GET['VS'])) {
            $this->VS = $_GET['VS'];
        } elseif (isset($_POST['vs'])) {
            $this->VS = $_POST['vs'];
        } elseif (isset($_GET['vs'])) {
            $this->VS = $_GET['vs'];
        }
    }

    public function renderGateway($gatewayCode)
    {
        $this->returnPayment($gatewayCode);
    }

    public function renderViamo()
    {
        if (!class_exists(Viamo::class)) {
            throw new \Exception('Unable to process Viamo payment, Viamo module has not been installed yet.');
        }

        $responseString = urldecode($this->params['responseString']);
        $parts = explode('*', $responseString);
        $payment = false;
        foreach ($parts as $pairs) {
            [$key, $value] = explode(':', $pairs);
            if ($key == 'VS') {
                $payment = $this->paymentsRepository->findByVs($value);
                break;
            }
        }

        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Payment not found (viamo)",
                $this->request->getUrl()
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }

        if ($payment->payment_gateway->code != 'viamo') {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type 'viamo'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }
        $this->processPayment($payment);
    }

    private function returnPayment($gatewayCode)
    {
        $payment = $this->getPayment();
        if (!$payment) {
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }

        // Chance to override gateway code before check
        /** @var PaymentReturnGatewayDataProviderInterface[] $providers */
        $providers = $this->dataProviderManager->getProviders('payments.dataprovider.payment_return_gateway', PaymentReturnGatewayDataProviderInterface::class);
        foreach ($providers as $provider) {
            $gatewayCode = $provider->provide(['payment' => $payment]);
        }

        if ($payment->payment_gateway->code !== $gatewayCode) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type '{$gatewayCode}'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
        }

        $this->processPayment($payment);
    }

    private function processPayment($payment)
    {
        $presenter = $this;

        $this->paymentProcessor->complete($payment, function ($payment, GatewayAbstract $gateway) use ($presenter) {
            if (in_array($payment->status, [PaymentStatusEnum::Paid->value, PaymentStatusEnum::Prepaid->value], true)) {
                // confirmed payment == agreed to terms
                if (!$this->userMetaRepository->exists($payment->user, 'gdpr')) {
                    $this->userMetaRepository->setMeta($payment->user, ['gdpr' => 'confirm_payment']);
                }

                // update all user tokens with new access data
                $presenter->userData->refreshUserTokens($payment->user_id);

                $presenter->paymentLogsRepository->add(
                    'OK',
                    "Redirecting to success url with vs '{$payment->variable_symbol}'",
                    $presenter->request->getUrl(),
                    $payment->id
                );

                $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::PAID);
            } elseif ($payment->status === PaymentStatusEnum::Authorized->value) {
                $presenter->paymentLogsRepository->add(
                    'OK',
                    "Redirecting to success url with vs '{$payment->variable_symbol}'",
                    $presenter->request->getUrl(),
                    $payment->id
                );

                $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::PAID);
            } elseif ($gateway->isNotSettled()) {
                $presenter->paymentLogsRepository->add(
                    'ERROR',
                    'Payment not settled, should be confirmed later',
                    $presenter->request->getUrl(),
                    $payment->id
                );

                $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::NOT_SETTLED);
            } elseif ($payment->status === PaymentStatusEnum::Fail->value) {
                $presenter->paymentLogsRepository->add(
                    'ERROR',
                    'Complete payment with unpaid payment',
                    $presenter->request->getUrl(),
                    $payment->id
                );

                if ($gateway->isCancelled()) {
                    $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::CANCELLED);
                }

                $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
            }
        });

        $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::FORM);
    }

    public function getPayment(): ?ActiveRow
    {
        if (!isset($this->VS)) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Missing VS parameter",
                $this->request->getUrl()
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }
        $payment = $this->paymentsRepository->findByVs($this->VS);
        if (!$payment) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Cannot load payment with VS '{$this->VS}'",
                $this->request->getUrl()
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }
        return $payment;
    }

    public function resolveRedirect($payment, $resolverStatus)
    {
        foreach ($this->paymentCompleteRedirectManager->getResolvers() as $resolver) {
            if ($resolver->wantsToRedirect($payment, $resolverStatus)) {
                $this->redirect(...$resolver->redirectArgs($payment, $resolverStatus));
            }
        }

        throw new \Exception("There's no redirect manager handling this scenario. You should register one or enable remp/crm-sales-funnel-module to enable default handling");
    }
}
