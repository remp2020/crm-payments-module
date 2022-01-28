<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Params\InputParam;
use Crm\ApiModule\Params\ParamsProcessor;
use Crm\ApiModule\Response\ApiResponseInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Http\Response;
use Nette\Utils\DateTime;

class ListRecurrentPaymentsApiHandler extends ApiHandler
{
    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params(): array
    {
        return [
            new InputParam(
                InputParam::TYPE_GET,
                'states',
                InputParam::OPTIONAL,
                $this->recurrentPaymentsRepository->getStates(),
                true
            ),
            new InputParam(
                InputParam::TYPE_GET,
                'chargeable_from',
                InputParam::OPTIONAL
            ),
        ];
    }


    public function handle(array $params): ApiResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Cannot authorize user']);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }
        $user = $data['token']->user;

        $paramsProcessor = new ParamsProcessor($this->params());
        $error = $paramsProcessor->hasError();
        if ($error) {
            $response = new JsonResponse(['status' => 'error', 'message' => 'Wrong input - ' . $error]);
            $response->setHttpCode(Response::S400_BAD_REQUEST);
            return $response;
        }
        $params = $paramsProcessor->getValues();

        $recurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($user->id);
        if ($params['states'] ?? false) {
            $recurrentPayments->where(['state' => $params['states']]);
        }
        if ($params['chargeable_from'] ?? false) {
            $chargeableFrom = DateTime::createFromFormat(DATE_ATOM, $params['chargeable_from']);
            if (!$chargeableFrom) {
                $response = new JsonResponse([
                    'status' => 'error',
                    'code' => 'invalid_date',
                    'message' => 'Invalid format provided for charge_at parameter, ISO 8601 expected: ' . $params['chargeable_from']
                ]);
                $response->setHttpCode(Response::S400_BAD_REQUEST);
                return $response;
            }
            $recurrentPayments->where('charge_at >= ?', $chargeableFrom);
        }

        $results = [];
        foreach ($recurrentPayments as $recurrentPayment) {
            $results[] = [
                'id' => $recurrentPayment->id,
                'parent_payment_id' => $recurrentPayment->parent_payment_id,
                'charge_at' => $recurrentPayment->charge_at->format('c'),
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
