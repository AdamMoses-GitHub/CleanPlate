<?php
require_once __DIR__ . '/../../includes/Config.php';
Config::load();
require_once __DIR__ . '/../../includes/AdminAuth.php';
AdminAuth::logout();   // destroys session and redirects to login.php
