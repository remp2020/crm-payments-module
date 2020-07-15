<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Api\ApiHandler;
use Crm\ApiModule\Api\JsonResponse;
use Crm\ApiModule\Api\JsonValidationTrait;
use Crm\ApiModule\Authorization\ApiAuthorizationInterface;
use Crm\PaymentsModule\Repository\RecurrentPaymentsRepository;
use Nette\Http\Response;
use Tracy\Debugger;

class StopRecurrentPaymentApiHandler extends ApiHandler
{
    use JsonValidationTrait;

    private $recurrentPaymentsRepository;

    public function __construct(RecurrentPaymentsRepository $recurrentPaymentsRepository)
    {
        $this->recurrentPaymentsRepository = $recurrentPaymentsRepository;
    }

    public function params()
    {
        return [];
    }

    public function handle(ApiAuthorizationInterface $authorization)
    {
        // authorize user
        $data = $authorization->getAuthorizedData();
        if (!isset($data['token']) || !isset($data['token']->user) || empty($data['token']->user)) {
            $response = new JsonResponse([
                'message' => 'Cannot authorize user',
                'code' => 'cannot_authorize_user',
            ]);
            $response->setHttpCode(Response::S403_FORBIDDEN);
            return $response;
        }
        $user = $data['token']->user;

        // validate JSON payload
        $validation = $this->validateInput(
            __DIR__ . '/stop-recurrent-payment.schema.json',
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
            $response = new JsonResponse([
                'message' => "Recurrent payment with ID [$recurrentPaymentID] not found.",
                'code' => 'recurrent_payment_not_found'
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        if ($recurrentPayment->user_id !== $user->id) {
            $response = new JsonResponse([
                'message' => "User with [$user->id] doesn't have recurrent payment with ID [$recurrentPaymentID].",
                'code' => 'recurrent_payment_not_found',
            ]);
            $response->setHttpCode(Response::S404_NOT_FOUND);
            return $response;
        }

        // check state; user can stop only active recurrent payments
        if ($recurrentPayment->state !== RecurrentPaymentsRepository::STATE_ACTIVE) {
            $response = new JsonResponse([
                'message' => "Only active recurrent payment can be stopped by user. Recurrent payment with ID [$recurrentPaymentID] is in state [$recurrentPayment->state].",
                'code' => 'recurrent_payment_not_active'
            ]);
            $response->setHttpCode(Response::S409_CONFLICT);
            return $response;
        }

        // stop recurrent payment
        $stoppedRecurrentPayment = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPaymentID, $user->id);
        if (!$stoppedRecurrentPayment) {
            Debugger::log("User is unable to stop recurrent payment with ID [$recurrentPaymentID].", Debugger::ERROR);
            $response = new JsonResponse([
                'message' => "Internal server error. Unable to stop recurrent payment ID [$recurrentPaymentID].",
                'code' => 'internal_server_error'
            ]);
            $response->setHttpCode(Response::S500_INTERNAL_SERVER_ERROR);
            return $response;
        }

        $result = [
            'id' => $stoppedRecurrentPayment->id,
            'parent_payment_id' => $stoppedRecurrentPayment->parent_payment_id,
            'charge_at' => $stoppedRecurrentPayment->charge_at,
            'payment_gateway_code' => $stoppedRecurrentPayment->payment_gateway->code,
            'subscription_type_code' => $stoppedRecurrentPayment->subscription_type->code,
            'state' => $stoppedRecurrentPayment->state,
            'retries' => $stoppedRecurrentPayment->retries,
        ];

        $response = new JsonResponse($result);
        $response->setHttpCode(Response::S200_OK);
        return $response;
    }
}
