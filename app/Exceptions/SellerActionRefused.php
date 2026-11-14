<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * A seller asked for something the rules don't allow right now (publish a
 * listing whose pickup has already started, pause a closed one…). The
 * message is written for the seller and shown on the page.
 */
class SellerActionRefused extends RuntimeException {}
