<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

function e(?string $value): string
{
    // Escape all dynamic output before rendering it into HTML.
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function format_full_name(array $student, bool $lastNameFirst = false): string
{
    $firstName = trim((string) ($student['first_name'] ?? ''));
    $middleName = trim((string) ($student['middle_name'] ?? ''));
    $lastName = trim((string) ($student['last_name'] ?? ''));

    if ($lastNameFirst) {
        $givenNames = trim($firstName . ' ' . $middleName);
        return trim($lastName . ', ' . $givenNames, ', ');
    }

    return trim($firstName . ' ' . $middleName . ' ' . $lastName);
}

function redirect(string $path): void
{
    header('Location: ' . BASE_URL . $path);
    exit;
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message,
    ];
}

function get_flash(): ?array
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }

    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);

    return $flash;
}

function enforce_session_timeout(): void
{
    $now = time();

    // Destroy inactive sessions to reduce the risk of session hijacking.
    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT) {
        session_unset();
        session_destroy();
        session_start();
        set_flash('error', 'Your session expired due to inactivity. Please log in again.');
        redirect('/index.php');
    }

    $_SESSION['last_activity'] = $now;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function require_login(array $roles = []): void
{
    enforce_session_timeout();

    if (!is_logged_in()) {
        set_flash('error', 'Please log in to continue.');
        redirect('/index.php');
    }

    if ($roles !== []) {
        $user = current_user();
        if ($user === null || !in_array($user['role'], $roles, true)) {
            set_flash('error', 'You do not have permission to access that page.');
            redirect('/dashboard.php');
        }
    }
}

function is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

function generate_csrf_token(): string
{
    // Reuse a single token per session for POST form protection.
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verify_csrf_token(?string $token): bool
{
    return isset($_SESSION['csrf_token']) && is_string($token) && hash_equals($_SESSION['csrf_token'], $token);
}

function validate_required(array $data, array $fields): array
{
    $errors = [];

    foreach ($fields as $field => $label) {
        if (!isset($data[$field]) || trim((string) $data[$field]) === '') {
            $errors[] = $label . ' is required.';
        }
    }

    return $errors;
}

function get_departments(PDO $pdo): array
{
    $statement = $pdo->query('SELECT id, name, head_of_department FROM departments ORDER BY name');
    return $statement->fetchAll();
}

function get_programs(PDO $pdo, ?int $departmentId = null): array
{
    if ($departmentId !== null) {
        $statement = $pdo->prepare('SELECT id, name, department_id, duration FROM programs WHERE department_id = :department_id ORDER BY name');
        $statement->execute(['department_id' => $departmentId]);
        return $statement->fetchAll();
    }

    $statement = $pdo->query('SELECT id, name, department_id, duration FROM programs ORDER BY name');
    return $statement->fetchAll();
}

function ensure_valid_program(PDO $pdo, int $programId, int $departmentId): bool
{
    $statement = $pdo->prepare('SELECT COUNT(*) FROM programs WHERE id = :program_id AND department_id = :department_id');
    $statement->execute([
        'program_id' => $programId,
        'department_id' => $departmentId,
    ]);

    return (int) $statement->fetchColumn() > 0;
}

function generate_next_student_id(PDO $pdo): string
{
    $baseId = 1002026;
    $statement = $pdo->query('SELECT student_id FROM students ORDER BY CAST(student_id AS UNSIGNED) DESC LIMIT 1');
    $lastStudentId = $statement->fetchColumn();

    // Start at 1002026 and increment from the latest stored student number.
    if ($lastStudentId === false) {
        return (string) $baseId;
    }

    $nextId = max((int) $lastStudentId + 1, $baseId);
    return (string) $nextId;
}

function handle_document_upload(array $file): ?string
{
    if (($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if (($file['error'] ?? UPLOAD_ERR_OK) !== UPLOAD_ERR_OK) {
        $uploadErrors = [
            UPLOAD_ERR_INI_SIZE => 'The uploaded file exceeds the server upload size limit.',
            UPLOAD_ERR_FORM_SIZE => 'The uploaded file exceeds the form upload size limit.',
            UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded. Please try again.',
            UPLOAD_ERR_NO_TMP_DIR => 'The server is missing a temporary upload directory.',
            UPLOAD_ERR_CANT_WRITE => 'The server could not write the uploaded file.',
            UPLOAD_ERR_EXTENSION => 'A PHP extension stopped the file upload.',
        ];

        $errorCode = (int) ($file['error'] ?? UPLOAD_ERR_OK);
        throw new RuntimeException($uploadErrors[$errorCode] ?? 'Document upload failed. Please try again.');
    }

    if (!is_dir(UPLOAD_DIR)) {
        if (!mkdir(UPLOAD_DIR, 0777, true) && !is_dir(UPLOAD_DIR)) {
            throw new RuntimeException('The uploads folder could not be created on the server.');
        }
    }

    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException('The uploads folder is not writable by the web server. Please check folder permissions.');
    }

    // Trust the detected MIME type instead of the original file extension.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = $finfo ? finfo_file($finfo, $file['tmp_name']) : null;
    if ($finfo) {
        finfo_close($finfo);
    }

    if (!is_string($mimeType) || !isset(ALLOWED_UPLOAD_TYPES[$mimeType])) {
        throw new RuntimeException('Only PDF, JPG, PNG, GIF, and WEBP files are allowed.');
    }

    $extension = ALLOWED_UPLOAD_TYPES[$mimeType];
    $fileName = bin2hex(random_bytes(16)) . '.' . $extension;
    $destination = UPLOAD_DIR . '/' . $fileName;

    if (!move_uploaded_file($file['tmp_name'], $destination)) {
        throw new RuntimeException('Unable to save the uploaded document.');
    }

    return 'uploads/' . $fileName;
}

function delete_document_file(?string $relativePath): void
{
    if ($relativePath === null || $relativePath === '') {
        return;
    }

    $fullPath = __DIR__ . '/' . ltrim($relativePath, '/');
    if (is_file($fullPath)) {
        unlink($fullPath);
    }
}

function login_user(array $user): void
{
    // Regenerate the session ID immediately after authentication.
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'username' => $user['username'],
        'role' => $user['role'],
    ];
    $_SESSION['last_activity'] = time();
}

function logout_user(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], (bool) $params['secure'], (bool) $params['httponly']);
    }

    session_destroy();
}

function fetch_student_counts(PDO $pdo): array
{
    $statement = $pdo->query("
        SELECT
            COUNT(*) AS total_students,
            SUM(gender = 'Male') AS male_students,
            SUM(gender = 'Female') AS female_students
        FROM students
    ");

    $counts = $statement->fetch() ?: [];

    return [
        'total_students' => (int) ($counts['total_students'] ?? 0),
        'male_students' => (int) ($counts['male_students'] ?? 0),
        'female_students' => (int) ($counts['female_students'] ?? 0),
    ];
}

function fetch_students(PDO $pdo, array $filters = [], ?array $user = null): array
{
    $conditions = [];
    $params = [];

    $sql = "
        SELECT
            s.*,
            d.name AS department_name,
            p.name AS program_name
        FROM students s
        INNER JOIN departments d ON d.id = s.department_id
        INNER JOIN programs p ON p.id = s.program_id
    ";

    if ($user !== null) {
        // Students can only view their own record.
        if ($user['role'] === 'student') {
            $conditions[] = 's.user_id = :user_id';
            $params['user_id'] = $user['id'];
        }
    }

    if (!empty($filters['name'])) {
        $searchValue = '%' . trim($filters['name']) . '%';
        $conditions[] = '(s.student_id LIKE :search_student_id OR s.first_name LIKE :search_first_name OR s.last_name LIKE :search_last_name)';
        $params['search_student_id'] = $searchValue;
        $params['search_first_name'] = $searchValue;
        $params['search_last_name'] = $searchValue;
    }

    if (!empty($filters['department_id'])) {
        $conditions[] = 's.department_id = :department_id';
        $params['department_id'] = (int) $filters['department_id'];
    }

    if (!empty($filters['program_id'])) {
        $conditions[] = 's.program_id = :program_id';
        $params['program_id'] = (int) $filters['program_id'];
    }

    if (!empty($filters['gender'])) {
        $conditions[] = 's.gender = :gender';
        $params['gender'] = $filters['gender'];
    }

    if (!empty($filters['year_level'])) {
        $conditions[] = 's.year_level = :year_level';
        $params['year_level'] = $filters['year_level'];
    }

    if ($conditions !== []) {
        $sql .= ' WHERE ' . implode(' AND ', $conditions);
    }

    $sql .= ' ORDER BY s.created_at DESC, s.last_name ASC, s.first_name ASC';

    $statement = $pdo->prepare($sql);
    $statement->execute($params);

    return $statement->fetchAll();
}

function fetch_student_by_id(PDO $pdo, int $id): ?array
{
    $statement = $pdo->prepare("
        SELECT
            s.*,
            d.name AS department_name,
            p.name AS program_name
        FROM students s
        INNER JOIN departments d ON d.id = s.department_id
        INNER JOIN programs p ON p.id = s.program_id
        WHERE s.id = :id
        LIMIT 1
    ");
    $statement->execute(['id' => $id]);
    $student = $statement->fetch();

    return $student ?: null;
}

function export_students_to_csv(array $students): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=students_export_' . date('Ymd_His') . '.csv');

    $output = fopen('php://output', 'wb');
    fputcsv($output, [
        'Student ID',
        'First Name',
        'Middle Name',
        'Last Name',
        'Gender',
        'Year Level',
        'Date of Birth',
        'Email',
        'Contact Number',
        'Department',
        'Program',
        'Document Path',
    ]);

    foreach ($students as $student) {
        fputcsv($output, [
            $student['student_id'],
            $student['first_name'],
            $student['middle_name'] ?? '',
            $student['last_name'],
            $student['gender'],
            $student['year_level'],
            $student['dob'],
            $student['email'],
            $student['contact_number'] ?? '',
            $student['department_name'],
            $student['program_name'],
            $student['document_path'] ?? '',
        ]);
    }

    fclose($output);
    exit;
}

function page_title(string $title): string
{
    return $title . ' | ' . APP_NAME;
}
