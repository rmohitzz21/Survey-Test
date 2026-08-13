<?php
require_once '../includes/auth.php';
sp_session_start();
$_SESSION = [];
session_destroy();
header('Location: ../login.php');
exit;
