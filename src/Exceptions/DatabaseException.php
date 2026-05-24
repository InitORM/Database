<?php

/**
 * @package InitORM\Database
 * @license MIT
 */

declare(strict_types=1);

namespace InitORM\Database\Exceptions;

use Exception;

/**
 * Base exception for runtime errors inside the InitORM Database layer
 * (transaction lifecycle issues, missing facade state, missing builder
 * methods, …). Catch this to catch every Database-specific error in one go.
 */
class DatabaseException extends Exception
{
}
