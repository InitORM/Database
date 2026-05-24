<?php

/**
 * @package InitORM\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\Database\Exceptions;

use InvalidArgumentException;

/**
 * Thrown when caller supplies an argument that this layer cannot accept —
 * e.g. a connection value that is neither a credentials array nor a
 * {@see \InitORM\DBAL\Connection\Interfaces\ConnectionInterface}, or a
 * transaction attempt count below 1.
 *
 * Extends PHP's native {@see InvalidArgumentException} so it is also catchable
 * by code that wants a broader net.
 */
class DatabaseInvalidArgumentException extends InvalidArgumentException
{
}
