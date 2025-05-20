<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Params\GetInputParam;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;

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
            (new GetInputParam('states'))->setAvailableValues($this->recurrentPaymentsRepository->getStates())->setMulti(),
            new GetInputParam('chargeable_from'),
        ];
    }


    public function handle(array $params): ResponseInterface
    {
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, ['status' => 'error', 'message' => 'Cannot authorize user']);
            return $response;
        }
        $user = $data['token']->user;

        $recurrentPayments = $this->recurrentPaymentsRepository->userRecurrentPayments($user->id);
        if ($params['states'] ?? false) {
            $recurrentPayments->where(['state' => $params['states']]);
        }
        if ($params['chargeable_from'] ?? false) {
            $chargeableFrom = DateTime::createFromFormat(DATE_ATOM, $params['chargeable_from']);
            if (!$chargeableFrom) {
                $response = new JsonApiResponse(Response::S400_BAD_REQUEST, [
                    'status' => 'error',
                    'code' => 'invalid_date',
                    'message' => 'Invalid format provided for charge_at parameter, ISO 8601 expected: ' . $params['chargeable_from'],
                ]);
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

        $response = new JsonApiResponse(Response::S200_OK, $results);
        return $response;
    }
}
