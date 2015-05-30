<?php

namespace Drupal\Prod\Error;

use \Exception as SplException;

class ProdException extends SplException implements Exception
{
}
