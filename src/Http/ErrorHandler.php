<?php
declare(strict_types=1);

namespace Sgpm\Http;

class ErrorHandler
{
    private $logger;
    private $requestId;
    public function __construct(\Sgpm\Infrastructure\Logger $logger, string $requestId)
    {
        $this->logger = $logger;
        $this->requestId = $requestId;
    }

    public function register()
    {
        ini_set('display_errors', '0');
        set_error_handler(function ($severity, $message, $file, $line) {
            if (!(error_reporting() & $severity)) { return false; }
            throw new \ErrorException($message, 0, $severity, $file, $line);
        });
        set_exception_handler(array($this, 'handle'));
    }

    public function handle(\Throwable $error)
    {
        if ($error instanceof HttpException) {
            Response::error($error->errorCode(), $error->getMessage(), $this->requestId, $error->status())->send();
            return;
        }
        try { $this->logger->incident($this->requestId, get_class($error)); }
        catch (\Throwable $logError) { error_log('SGPM: incidente no registrado.'); }
        Response::error('ERROR_INTERNO', 'No fue posible completar la operación. Inténtalo nuevamente.',
            $this->requestId, 500)->send();
    }
}

