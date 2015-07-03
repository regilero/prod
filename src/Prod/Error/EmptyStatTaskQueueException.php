<?php

namespace Drupal\Prod\Error;

use \Exception as SplException;

class EmptyStatTaskQueueException extends SplException implements Exception
{
}
