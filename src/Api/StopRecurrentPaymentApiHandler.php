<?php

namespace Crm\PaymentsModule\Api;

use Crm\ApiModule\Models\Api\ApiHandler;
use Crm\ApiModule\Models\Api\JsonValidationTrait;
use Crm\PaymentsModule\Models\RecurrentPayment\RecurrentPaymentStateEnum;
use Crm\PaymentsModule\Repositories\RecurrentPaymentsRepository;
use Nette\Http\Response;
use Tomaj\NetteApi\Response\JsonApiResponse;
use Tomaj\NetteApi\Response\ResponseInterface;
use Tracy\Debugger;

class StopRecurrentPaymentApiHandler extends ApiHandler
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
            __DIR__ . '/stop-recurrent-payment.schema.json',
            $this->rawPayload(),
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
                'code' => 'recurrent_payment_not_found',
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

        // check state; user can stop only active recurrent payments
        if ($recurrentPayment->state !== RecurrentPaymentStateEnum::Active->value) {
            $response = new JsonApiResponse(Response::S409_CONFLICT, [
                'message' => "Only active recurrent payment can be stopped by user. Recurrent payment with ID [$recurrentPaymentID] is in state [$recurrentPayment->state].",
                'code' => 'recurrent_payment_not_active',
            ]);
            return $response;
        }

        if (!$this->recurrentPaymentsRepository->canBeStoppedByUser($recurrentPayment)) {
            $response = new JsonApiResponse(Response::S403_FORBIDDEN, [
                'message' => "Payment gateway [{$recurrentPayment->payment_gateway->code}] is unstoppable by user.",
                'code' => 'user_unstoppable_recurrent_payment_gateway',
            ]);
            return $response;
        }

        // stop recurrent payment
        $stoppedRecurrentPayment = $this->recurrentPaymentsRepository->stoppedByUser($recurrentPaymentID, $user->id);
        if (!$stoppedRecurrentPayment) {
            Debugger::log("User is unable to stop recurrent payment with ID [$recurrentPaymentID].", Debugger::ERROR);
            $response = new JsonApiResponse(Response::S500_INTERNAL_SERVER_ERROR, [
                'message' => "Internal server error. Unable to stop recurrent payment ID [$recurrentPaymentID].",
                'code' => 'internal_server_error',
            ]);
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

        $response = new JsonApiResponse(Response::S200_OK, $result);
        return $response;
    }
}
