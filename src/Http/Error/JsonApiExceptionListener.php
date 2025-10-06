<?php

declare(strict_types=1);

namespace JsonApi\Symfony\Http\Error;

use DateTimeImmutable;
use JsonApi\Symfony\Http\Negotiation\MediaType;
use JsonApi\Symfony\Http\Exception\BadRequestException;
use JsonApi\Symfony\Http\Exception\ConflictException;
use JsonApi\Symfony\Http\Exception\ForbiddenException;
use JsonApi\Symfony\Http\Exception\JsonApiHttpException;
use JsonApi\Symfony\Http\Exception\MethodNotAllowedException;
use JsonApi\Symfony\Http\Exception\MultiErrorException;
use JsonApi\Symfony\Http\Exception\NotAcceptableException;
use JsonApi\Symfony\Http\Exception\NotFoundException;
use JsonApi\Symfony\Http\Exception\UnprocessableEntityException;
use JsonApi\Symfony\Http\Exception\UnsupportedMediaTypeException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Throwable;

final class JsonApiExceptionListener implements EventSubscriberInterface
{
    private const PRIORITY = 512;

    public function __construct(
        private readonly ErrorMapper $errors,
        private readonly CorrelationIdProvider $correlationIds,
        private readonly bool $exposeDebugMeta,
        private readonly bool $addCorrelationId,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => ['onKernelException', self::PRIORITY],
        ];
    }

    public function onKernelException(ExceptionEvent $event): void
    {
        $throwable = $event->getThrowable();
        [$status, $errors, $headers] = $this->mapThrowable($throwable);

        if ($this->addCorrelationId) {
            $correlationId = $this->correlationIds->generate();
            $headers['X-Request-ID'] = $correlationId;
            $errors = array_map(static fn (ErrorObject $error) => $error->withId($correlationId), $errors);
        }

        if ($this->exposeDebugMeta) {
            $debugMeta = [
                'timestamp' => (new DateTimeImmutable())->format(DATE_ATOM),
                'exceptionClass' => $throwable::class,
            ];

            $previous = $throwable->getPrevious();
            if ($previous instanceof Throwable) {
                $debugMeta['previous'] = $previous::class;
            }

            $debugMeta['message'] = $throwable->getMessage();
            $debugMeta['trace'] = $throwable->getTraceAsString();

            $errors = array_map(static fn (ErrorObject $error) => $error->withMergedMeta($debugMeta), $errors);
        }

        $payload = ['errors' => array_map(static fn (ErrorObject $error) => $error->toArray(), $errors)];

        $response = new JsonResponse(
            $payload,
            $status,
            array_replace(
                $headers,
                [
                    'Content-Type' => MediaType::JSON_API,
                    'Vary' => 'Accept',
                ],
            ),
        );

        $event->setResponse($response);
    }

    /**
     * @return array{int, list<ErrorObject>, array<string, string>}
     */
    private function mapThrowable(Throwable $throwable): array
    {
        $headers = [];
        $status = 500;
        $errors = [];

        if ($throwable instanceof JsonApiHttpException) {
            $status = $throwable->getStatusCode();
            $headers = $throwable->getHeaders();
            $errors = $throwable->getErrors();
        }

        if ($errors === []) {
            $errors = $this->buildErrorsFor($throwable, $status);
            if ($status === 500) {
                $status = $this->determineStatus($throwable) ?? $status;
            }
        }

        if ($errors === []) {
            $errors = [$this->errors->internal()];
            $status = $this->determineStatus($throwable) ?? 500;
        }

        return [$status, $errors, $headers];
    }

    /**
     * @return list<ErrorObject>
     */
    private function buildErrorsFor(Throwable $throwable, int $status): array
    {
        if ($throwable instanceof UnsupportedMediaTypeException) {
            return [$this->errors->invalidContentType($throwable->getMediaType())];
        }

        if ($throwable instanceof NotAcceptableException) {
            return [$this->errors->notAcceptable($throwable->getAcceptHeader())];
        }

        if ($throwable instanceof MultiErrorException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof BadRequestException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof ConflictException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof ForbiddenException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof NotFoundException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof MethodNotAllowedException) {
            return $throwable->getErrors() !== [] ? $throwable->getErrors() : [$this->errors->methodNotAllowed($throwable->getAllowedMethods())];
        }

        if ($throwable instanceof UnprocessableEntityException) {
            return $throwable->getErrors();
        }

        if ($throwable instanceof HttpExceptionInterface) {
            return [$this->errors->internal($throwable->getMessage())];
        }

        return [];
    }

    private function determineStatus(Throwable $throwable): ?int
    {
        if ($throwable instanceof HttpExceptionInterface) {
            return $throwable->getStatusCode();
        }

        return null;
    }
}
