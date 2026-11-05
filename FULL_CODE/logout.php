<?php
/**
 * Citizen Logout - DoseWise
 */

declare(strict_types=1);

require_once __DIR__ . '/config/config.php';
require_once __DIR__ . '/includes/helpers.php';

logoutUser();
setFlash('info', t_plain('flash.signed_out'));
redirect(BASE_URL . 'index.php');
