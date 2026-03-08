<?php
/**
 * Gemini.ai Plesk Entry Point
 * Document root: gemini-ai/public
 * App root: gemini-ai/ (parent of public)
 *
 * @package TruAi
 * @copyright My Deme, LLC © 2026
 */

define('APP_ROOT', dirname(__DIR__));
define('PUBLIC_ROOT', __DIR__);
chdir(APP_ROOT);

require __DIR__ . '/router.php';
