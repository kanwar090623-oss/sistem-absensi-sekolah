<?php
require __DIR__ . '/config.php';
redirect(isset($_SESSION['uid']) ? 'dashboard.php' : 'login.php');
