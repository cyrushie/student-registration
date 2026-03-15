<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login(['admin']);

$pageTitle = page_title('Edit Student');
$studentRecordId = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$student = fetch_student_by_id($pdo, $studentRecordId);

if ($student === null) {
    set_flash('error', 'Student record not found.');
    redirect('/manage_students.php');
}

$errors = [];
$departments = get_departments($pdo);
$programs = get_programs($pdo);

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
    $removeDocument = isset($_POST['remove_document']) && $_POST['remove_document'] === '1';

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

    if ($errors === []) {
        $newDocumentPath = null;
        $finalDocumentPath = $student['document_path'];

        try {
            if (!empty($_FILES['document']['name'] ?? '')) {
                $newDocumentPath = handle_document_upload($_FILES['document']);
                $finalDocumentPath = $newDocumentPath;
            } elseif ($removeDocument) {
                $finalDocumentPath = null;
            }

            $statement = $pdo->prepare("
                UPDATE students SET
                    first_name = :first_name,
                    middle_name = :middle_name,
                    last_name = :last_name,
                    gender = :gender,
                    year_level = :year_level,
                    dob = :dob,
                    email = :email,
                    contact_number = :contact_number,
                    department_id = :department_id,
                    program_id = :program_id,
                    document_path = :document_path
                WHERE id = :id
            ");

            $statement->execute([
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
                'document_path' => $finalDocumentPath,
                'id' => $studentRecordId,
            ]);

            if (($removeDocument || $newDocumentPath !== null) && !empty($student['document_path'])) {
                delete_document_file($student['document_path']);
            }

            set_flash('success', 'Student record updated successfully.');
            redirect('/manage_students.php');
        } catch (Throwable $throwable) {
            if ($newDocumentPath !== null) {
                delete_document_file($newDocumentPath);
            }

            $errors[] = 'Unable to update student: ' . $throwable->getMessage();
        }
    }
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-header">
    <div>
        <h2>Edit Student</h2>
        <p class="muted">Update the student profile, adviser assignment, and document.</p>
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
        <input type="hidden" name="id" value="<?= e((string) $studentRecordId) ?>">

        <label>
            Student ID
            <input type="text" value="<?= e($student['student_id']) ?>" disabled>
        </label>

        <label>
            Student Username
            <input type="text" value="<?= e($student['student_id']) ?>" disabled>
        </label>

        <label>
            First Name
            <input type="text" name="first_name" value="<?= e($_POST['first_name'] ?? $student['first_name']) ?>" required>
        </label>

        <label>
            Middle Name
            <input type="text" name="middle_name" value="<?= e($_POST['middle_name'] ?? $student['middle_name']) ?>" required>
        </label>

        <label>
            Last Name
            <input type="text" name="last_name" value="<?= e($_POST['last_name'] ?? $student['last_name']) ?>" required>
        </label>

        <label>
            Gender
            <?php $selectedGender = $_POST['gender'] ?? $student['gender']; ?>
            <select name="gender" required>
                <option value="">Select gender</option>
                <?php foreach (['Male', 'Female', 'Other'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $selectedGender === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Year Level
            <?php $selectedYearLevel = $_POST['year_level'] ?? $student['year_level']; ?>
            <select name="year_level" required>
                <option value="">Select year level</option>
                <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $selectedYearLevel === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Date of Birth
            <input type="date" name="dob" value="<?= e($_POST['dob'] ?? $student['dob']) ?>" required>
        </label>

        <label>
            Email
            <input type="email" name="email" value="<?= e($_POST['email'] ?? $student['email']) ?>" required>
        </label>

        <label>
            Contact Number
            <input type="text" name="contact_number" value="<?= e($_POST['contact_number'] ?? $student['contact_number']) ?>" placeholder="09XXXXXXXXX or +63..." required>
        </label>

        <label>
            Department
            <?php $selectedDepartment = (string) ($_POST['department_id'] ?? $student['department_id']); ?>
            <select name="department_id" required>
                <option value="">Select department</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e((string) $department['id']) ?>" <?= $selectedDepartment === (string) $department['id'] ? 'selected' : '' ?>>
                        <?= e($department['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Program
            <?php $selectedProgram = (string) ($_POST['program_id'] ?? $student['program_id']); ?>
            <select name="program_id" required>
                <option value="">Select program</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= e((string) $program['id']) ?>" <?= $selectedProgram === (string) $program['id'] ? 'selected' : '' ?>>
                        <?= e($program['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Replace Document
            <input type="file" name="document" accept=".pdf,.jpg,.jpeg,.png,.gif,.webp">
        </label>

        <label class="checkbox">
            <input type="checkbox" name="remove_document" value="1">
            Remove current document
        </label>

        <?php if (!empty($student['document_path'])): ?>
            <p class="muted full-width">Current file: <a href="<?= e($student['document_path']) ?>" target="_blank" rel="noopener noreferrer"><?= e(basename($student['document_path'])) ?></a></p>
        <?php endif; ?>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Update Student</button>
        </div>
    </form>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
