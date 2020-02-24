<?php

namespace Crm\PaymentsModule\Presenters;

use Crm\ApplicationModule\Presenters\FrontendPresenter;
use Crm\PaymentsModule\Gateways\GatewayAbstract;
use Crm\PaymentsModule\Model\PaymentCompleteRedirectManager;
use Crm\PaymentsModule\Model\PaymentCompleteRedirectResolver;
use Crm\PaymentsModule\PaymentProcessor;
use Crm\PaymentsModule\Repository\PaymentLogsRepository;
use Crm\PaymentsModule\Repository\PaymentMetaRepository;
use Crm\PaymentsModule\Repository\PaymentsRepository;
use Crm\UsersModule\Auth\Access\AccessToken;
use Crm\UsersModule\Repository\UserMetaRepository;
use Crm\UsersModule\Repository\UsersRepository;

class ReturnPresenter extends FrontendPresenter
{
    /** @var PaymentsRepository @inject */
    public $paymentsRepository;

    /** @var PaymentLogsRepository @inject */
    public $paymentLogsRepository;

    /** @var PaymentProcessor @inject */
    public $paymentProcessor;

    /** @var UserMetaRepository @inject */
    public $userMetaRepository;

    /** @var PaymentMetaRepository @inject */
    public $paymentMetaRepository;

    /** @var PaymentCompleteRedirectManager @inject */
    public $paymentCompleteRedirectManager;

    /** @var AccessToken @inject */
    public $accessToken;

    /** @persistent */
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
        return $this->returnPayment($gatewayCode);
    }

    public function renderGoPay($id)
    {
        if ($id === null) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Gopay returned without transaction reference: '{$this->VS}'",
                $this->request->getUrl(),
                null
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }

        $meta = $this->paymentMetaRepository->findByMeta('gopay_transaction_reference', $id);
        if (!$meta) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Cannot find gopay transaction reference '{$id}'",
                $this->request->getUrl(),
                null
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }

        $payment = $meta->payment;

        if (!in_array($payment->payment_gateway->code, ['gopay', 'gopay_recurrent'])) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type 'gopay'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        }
        return $this->processPayment($payment);
    }

    public function renderViamo()
    {
        $responseString = urldecode($this->params['responseString']);
        $parts = explode('*', $responseString);
        $payment = false;
        foreach ($parts as $pairs) {
            list($key, $value) = explode(':', $pairs);
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
        if ($payment->payment_gateway->code !== $gatewayCode) {
            $this->paymentLogsRepository->add(
                'ERROR',
                "Return to wrong payment type '{$gatewayCode}'",
                $this->request->getUrl(),
                $payment->id
            );
            $this->resolveRedirect($payment, PaymentCompleteRedirectResolver::ERROR);
        }
        return $this->processPayment($payment);
    }

    private function processPayment($payment)
    {
        $presenter = $this;

        $this->paymentProcessor->complete($payment, function ($payment, GatewayAbstract $gateway) use ($presenter) {
            if (in_array($payment->status, [PaymentsRepository::STATUS_PAID, PaymentsRepository::STATUS_PREPAID])) {
                // confirmed payment == agreed to terms
                if (!$this->userMetaRepository->exists($payment->user, 'gdpr')) {
                    $this->userMetaRepository->setMeta($payment->user, ['gdpr' => 'confirm_payment']);
                }

                // autologin user after the payment (unless he's an admin)
                if (!$this->getUser()->isLoggedIn()) {
                    // autologin regular user with regular payment
                    if ($payment->user->role !== UsersRepository::ROLE_ADMIN) {
                        $presenter->getUser()->login(['username' => $payment->user->email, 'alwaysLogin' => true]);
                    } else {
                        // redirect admin user to sign in form (no autologin allowed)
                        $presenter->flashMessage($this->translator->translate('sales_funnel.frontend.disabled_auto_login.title'), 'warning');
                        $presenter->redirect($this->applicationConfig->get('not_logged_in_route'), ['back' => $this->storeRequest()]);
                    }
                }

                // issue new access token with new access data (old token will be removed)
                if ($presenter->getUser()->isLoggedIn()) {
                    $presenter->accessToken->addUserToken(
                        $presenter->getUser(),
                        $presenter->request,
                        $presenter->response
                    );
                }
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
            } else {
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

    public function getPayment()
    {
        if (isset($this->VS)) {
            $payment = $this->paymentsRepository->findByVs($this->VS);
            return $payment;
        }
        $this->paymentLogsRepository->add(
            'ERROR',
            "Cannot load payment with VS '{$this->VS}'",
            $this->request->getUrl()
        );
        $this->resolveRedirect(null, PaymentCompleteRedirectResolver::ERROR);
        return false;
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
