<?php
declare(strict_types=1);

namespace Sgpm\Http;

class HttpException extends \RuntimeException
{
    private $status;
    private $errorCode;

    public function __construct(int $status, string $errorCode, string $message)
    {
        parent::__construct($message);
        $this->status = $status;
        $this->errorCode = $errorCode;
    }

    public function status(): int { return $this->status; }
    public function errorCode(): string { return $this->errorCode; }
}

