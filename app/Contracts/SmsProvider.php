<?php

namespace App\Contracts;

interface SmsProvider
{
    /** @return array{accepted:bool,messageId:?string,status:string,response:array<string, mixed>} */
    public function send(string $phoneE164, string $message): array;
}
