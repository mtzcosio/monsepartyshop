<?php
require_once __DIR__ . '/includes/auth.php';
admin_logout();
redirect(admin_url('login.php'));
