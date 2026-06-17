<?php

declare(strict_types=1);

namespace Hydra\Http;

/**
 * HTTP status codes as a named, int-backed vocabulary.
 *
 * The point is not to forbid the literal `200` — HTTP codes are a standard
 * vocabulary, not magic numbers — but to give the less-memorable ones a name at
 * the call site and to be the single home for their reason phrases (which the
 * error handler previously kept as a private table of its own).
 *
 * Nothing is forced to use it: {@see Responder} and the base controller accept
 * `int|Status`, so you reach for a case where a name reads better and a literal
 * where it doesn't. Only the common subset this app emits is enumerated — this
 * is a vocabulary, not an exhaustive registry.
 */
enum Status: int
{
    case Ok = 200;
    case Created = 201;
    case NoContent = 204;
    case MovedPermanently = 301;
    case Found = 302;
    case BadRequest = 400;
    case Unauthorized = 401;
    case Forbidden = 403;
    case NotFound = 404;
    case MethodNotAllowed = 405;
    case UnprocessableEntity = 422;
    case InternalServerError = 500;
    case ServiceUnavailable = 503;

    /** The RFC reason phrase for this status. */
    public function reason(): string
    {
        return match ($this) {
            self::Ok => 'OK',
            self::Created => 'Created',
            self::NoContent => 'No Content',
            self::MovedPermanently => 'Moved Permanently',
            self::Found => 'Found',
            self::BadRequest => 'Bad Request',
            self::Unauthorized => 'Unauthorized',
            self::Forbidden => 'Forbidden',
            self::NotFound => 'Not Found',
            self::MethodNotAllowed => 'Method Not Allowed',
            self::UnprocessableEntity => 'Unprocessable Entity',
            self::InternalServerError => 'Internal Server Error',
            self::ServiceUnavailable => 'Service Unavailable',
        };
    }

    /**
     * The reason phrase for a raw status code, or null if it isn't one of the
     * enumerated codes. Lets callers that hold a plain int (an HttpException's
     * status, say) resolve a phrase without a table of their own.
     */
    public static function reasonFor(int $code): ?string
    {
        return self::tryFrom($code)?->reason();
    }
}
