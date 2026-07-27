<?php

namespace App\Contracts;

interface StkPushGateway
{
    /**
     * @param  array{phone:string,amountMinor:int,accountReference:string,description:string,callbackUrl:string,idempotencyKey:string}  $request
     * @return array{merchantRequestId:?string,checkoutRequestId:?string,accepted:bool,responseCode:?string,responseDescription:string}
     */
    public function initiate(array $request): array;
}
