<?php

/**
 * Sarah AI Server — Deploy-time configuration.
 *
 * Set SARAH_AI_WHMCS_LICENSE_SECRET to enable HMAC-SHA256 signature
 * verification on WHMCS license check responses.
 *
 * Leave empty ('') to skip signature verification.
 */

if (! defined('ABSPATH')) {
    exit;
}

if (! defined('SARAH_AI_WHMCS_LICENSE_SECRET')) {
    define('SARAH_AI_WHMCS_LICENSE_SECRET', 'O`N$5060eQwU:ou~&ai');
}
