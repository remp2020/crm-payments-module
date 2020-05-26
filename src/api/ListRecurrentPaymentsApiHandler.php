<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Http\Response;

class ListRecurrentPaymentsApiHandler extends ApiHandler
{
    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params()
    {
        return [
            new InputParam(
                InputParam::TYPE_GET,
                'state',
                InputParam::OPTIONAL,
                $this->recurrentPaymentsRepository->getStates()
            ),
        ];
    }

    /**
     * @param ApiAuthorizationInterface $authorization
     * @return \Nette\Application\IResponse
     */
    public function handle(ApiAuthorizationInterface $authorization)
    {
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }
        $user = $data['token']->user;

        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->isError();
        if ($error) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Wrong input - ' . $error]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $recurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($user->id);
        if (isset($params['state'])) {
            $recurrentPayments->where('state = ?', $params['state']);
        }

        $results = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $results[] = [
                'id' => $recurrentPayment->id,
                'parent_payment_id' => $recurrentPayment->parent_payment_id,
                'charge_at' => $recurrentPayment->charge_at,
                'payment_gateway_code' => $recurrentPayment->payment_gateway->code,
                'subscription_type_code' => $recurrentPayment->subscription_type->code,
                'state' => $recurrentPayment->state,
                'retries' => $recurrentPayment->retries,
            ];
        }

        $response = new JsonResponse($results);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
