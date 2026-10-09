<?php

declare(strict_types=1);

namespace Sunrice\Resources;

use RuntimeException;

/**
 * Thrown from an Action's handler to stop it and show the message to the
 * admin as an error ("This order was already refunded.").
 */
class ActionFailed extends RuntimeException {}
