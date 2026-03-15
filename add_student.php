<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login(['admin']);

$pageTitle = page_title('Add Student');
$errors = [];
$departments = get_departments($pdo);
$programs = get_programs($pdo);
$nextStudentId = generate_next_student_id($pdo);

if (is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        $errors[] = 'Invalid form submission. Please refresh the page and try again.';
    }

    $requiredFields = [
        'first_name' => 'First name',
        'middle_name' => 'Middle name',
        'last_name' => 'Last name',
        'gender' => 'Gender',
        'year_level' => 'Year level',
        'dob' => 'Date of birth',
        'email' => 'Email',
        'contact_number' => 'Contact number',
        'department_id' => 'Department',
        'program_id' => 'Program',
    ];

    $errors = array_merge($errors, validate_required($_POST, $requiredFields));

    $firstName = trim((string) ($_POST['first_name'] ?? ''));
    $middleName = trim((string) ($_POST['middle_name'] ?? ''));
    $lastName = trim((string) ($_POST['last_name'] ?? ''));
    $gender = trim((string) ($_POST['gender'] ?? ''));
    $yearLevel = trim((string) ($_POST['year_level'] ?? ''));
    $dob = trim((string) ($_POST['dob'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $contactNumber = trim((string) ($_POST['contact_number'] ?? ''));
    $departmentId = (int) ($_POST['department_id'] ?? 0);
    $programId = (int) ($_POST['program_id'] ?? 0);

    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($contactNumber !== '' && !preg_match('/^[0-9+\\-()\\s]{7,20}$/', $contactNumber)) {
        $errors[] = 'Please enter a valid contact number.';
    }

    if (!in_array($gender, ['Male', 'Female', 'Other'], true)) {
        $errors[] = 'Please select a valid gender.';
    }

    if (!ensure_valid_program($pdo, $programId, $departmentId)) {
        $errors[] = 'The selected program does not belong to the selected department.';
    }

    $studentId = generate_next_student_id($pdo);

    if ($errors === []) {
        try {
            $documentPath = handle_document_upload($_FILES['document'] ?? []);

            $pdo->beginTransaction();

            // Every student record gets a matching student login automatically.
            $userStatement = $pdo->prepare('INSERT INTO users (username, password_hash, role) VALUES (:username, :password_hash, :role)');
            $userStatement->execute([
                'username' => $studentId,
                'password_hash' => password_hash(DEFAULT_STUDENT_PASSWORD, PASSWORD_BCRYPT),
                'role' => 'student',
            ]);
            $studentUserId = (int) $pdo->lastInsertId();

            // Store the generated student ID and link the new user account.
            $studentStatement = $pdo->prepare("
                INSERT INTO students (
                    user_id,
                    student_id,
                    first_name,
                    middle_name,
                    last_name,
                    gender,
                    year_level,
                    dob,
                    email,
                    contact_number,
                    department_id,
                    program_id,
                    document_path
                ) VALUES (
                    :user_id,
                    :student_id,
                    :first_name,
                    :middle_name,
                    :last_name,
                    :gender,
                    :year_level,
                    :dob,
                    :email,
                    :contact_number,
                    :department_id,
                    :program_id,
                    :document_path
                )
            ");

            $studentStatement->execute([
                'user_id' => $studentUserId,
                'student_id' => $studentId,
                'first_name' => $firstName,
                'middle_name' => $middleName,
                'last_name' => $lastName,
                'gender' => $gender,
                'year_level' => $yearLevel,
                'dob' => $dob,
                'email' => $email,
                'contact_number' => $contactNumber,
                'department_id' => $departmentId,
                'program_id' => $programId,
                'document_path' => $documentPath,
            ]);

            $pdo->commit();

            set_flash('success', 'Student added successfully. Username: ' . $studentId . ' | Default password: ' . DEFAULT_STUDENT_PASSWORD);
            redirect('/manage_students.php');
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if (isset($documentPath)) {
                delete_document_file($documentPath);
            }

            $errors[] = 'Unable to add student: ' . $throwable->getMessage();
        }
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-header">
    <div>
        <h2>Add Student</h2>
        <p class="muted">Student IDs are generated automatically starting at <?= e($nextStudentId) ?>.</p>
    </div>
</section>

<section class="card">
    <?php if ($errors !== []): ?>
        <div class="alert alert-error">
            <?php foreach ($errors as $error): ?>
                <p><?= e($error) ?></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data" class="form-grid two-columns">
        <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">

        <label>
            First Name
            <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? '') ?>" required>
        </label>

        <label>
            Middle Name
            <input type="text" name="middle_name" value="<?= e($_POST['middle_name'] ?? '') ?>" required>
        </label>

        <label>
            Last Name
            <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? '') ?>" required>
        </label>

        <label>
            Gender
            <select name="gender" required>
                <option value="">Select gender</option>
                <?php foreach (['Male', 'Female', 'Other'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= (($_POST['gender'] ?? '') === $option) ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Year Level
            <select name="year_level" required>
                <option value="">Select year level</option>
                <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= (($_POST['year_level'] ?? '') === $option) ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Date of Birth
            <input type="date" name="dob" value="<?= e($_POST['dob'] ?? '') ?>" required>
        </label>

        <label>
            Email
            <input type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required>
        </label>

        <label>
            Contact Number
            <input type="text" name="contact_number" value="<?= e($_POST['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX or +63..." required>
        </label>

        <label>
            Department
            <select name="department_id" required>
                <option value="">Select department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e((string) $department['id']) ?>" <?= ((string) ($department['id']) === (string) ($_POST['department_id'] ?? '')) ? 'selected' : '' ?>>
                        <?= e($department['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Program
            <select name="program_id" required>
                <option value="">Select program</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= e((string) $program['id']) ?>" <?= ((string) ($program['id']) === (string) ($_POST['program_id'] ?? '')) ? 'selected' : '' ?>>
                        <?= e($program['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Document Upload
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Save Student</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
