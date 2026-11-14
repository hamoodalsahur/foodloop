<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * An admin asked for something the rules don't allow (approve a shop with
 * no SSM number, take down a listing that is already down…). The message
 * is written for the admin and shown on the page.
 */
class AdminActionRefused extends RuntimeException {}
