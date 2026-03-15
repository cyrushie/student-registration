<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';

logout_user();
session_name('student_registration_session');
session_start();
set_flash('success', 'You have been logged out successfully.');
redirect('/index.php');
