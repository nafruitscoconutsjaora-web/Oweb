<?php
declare(strict_types=1);
require_once dirname(__DIR__) . '/config/config.php';

logout();
header('Location: /auth/login.php');
exit;
