<?php

namespace Crm\PaymentsModule\Models\Gateways;

interface CancellableTokenInterface
{
    public function cancelToken(string $token): bool;
}
