<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Http\Response;
use Nette\Utils\DateTime;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;

class ReactivateRecurrentPaymentApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params(): array
    {
        return [];
    }

    public function handle(array $params): ResponseInterface
    {
        // authorize user
        $authorization = $this->getAuthorization();
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, [
                'message' => 'Cannot authorize user',
                'code' => 'cannot_authorize_user',
            ]);
            return $response;
        }
        $user = $data['token']->user;

        // validate JSON payload
        $validation = $this->validateInput(
            __DIR__ . '/reactivate-recurrent-payment.schema.json',
            $this->rawPayload()
        );
        if ($validation->hasErrorResponse()) {
            return $validation->getErrorResponse();
        }
        $validationResult = $validation->getParsedObject();

        // load recurrent payment
        $recurrentPaymentID = $validationResult->id;
        $recurrentPayment = $this->recurrentPaymentsRepository->find($recurrentPaymentID);
        if (!$recurrentPayment) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'message' => "Recurrent payment with ID [$recurrentPaymentID] not found.",
                'code' => 'recurrent_payment_not_found'
            ]);
            return $response;
        }

        if ($recurrentPayment->user_id !== $user->id) {
            $response = new JsonApiResponse(Response::S404_NOT_FOUND, [
                'message' => "User with [$user->id] doesn't have recurrent payment with ID [$recurrentPaymentID].",
                'code' => 'recurrent_payment_not_found',
            ]);
            return $response;
        }

        // check state; user can stop only `user_stop` recurrent payments
        if ($recurrentPayment->state !== RecurrentPaymentsRepository::STATE_USER_STOP) {
            $response = new JsonApiResponse(Response::S409_CONFLICT, [
                'message' => "Only recurrent payment in state [" . RecurrentPaymentsRepository::STATE_USER_STOP . "] can be reactivated by user. Recurrent payment with ID [$recurrentPaymentID] is in state [$recurrentPayment->state].",
                'code' => 'recurrent_payment_not_active'
            ]);
            return $response;
        }

        if (!$recurrentPayment->cid) {
            Debugger::log("User stopped recurrent payment with ID [$recurrentPaymentID] is missing CID.", Debugger::ERROR);
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, [
                'message' => "Recurrent payment with ID [$recurrentPaymentID] cannot be reactivated. Recurrent payment is missing CID.",
                'code' => 'recurrent_payment_missing_cid',
            ]);
            return $response;
        }
        if ($recurrentPayment->charge_at < new DateTime()) {
            $response = new JsonApiResponse(Response::S400_BAD_REQUEST, [
                'message' => "Recurrent payment with ID [$recurrentPaymentID] cannot be reactivated. Next charge is in past.",
                'code' => 'recurrent_payment_next_charge_in_past',
            ]);
            return $response;
        }

        // reactivate recurrent payment
        $reactivatedRecurrentPayment = $this->recurrentPaymentsRepository->reactivateByUser($recurrentPaymentID, $user->id);
        if (!$reactivatedRecurrentPayment) {
            Debugger::log("User is unable to reactivate recurrent payment with ID [$recurrentPaymentID].", Debugger::ERROR);
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, [
                'message' => "Internal server error. Unable to reactivate recurrent payment ID [$recurrentPaymentID].",
                'code' => 'internal_server_error'
            ]);
            return $response;
        }

        $result = [
            'id' => $reactivatedRecurrentPayment->id,
            'parent_payment_id' => $reactivatedRecurrentPayment->parent_payment_id,
            'charge_at' => $reactivatedRecurrentPayment->charge_at,
            'payment_gateway_code' => $reactivatedRecurrentPayment->payment_gateway->code,
            'subscription_type_code' => $reactivatedRecurrentPayment->subscription_type->code,
            'state' => $reactivatedRecurrentPayment->state,
            'retries' => $reactivatedRecurrentPayment->retries,
        ];

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
