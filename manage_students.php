<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login(['admin']);

$user = current_user();
$pageTitle = page_title('Manage Students');
$departments = get_departments($pdo);
$programs = get_programs($pdo);
$filters = [
    'name' => trim((string) ($_GET['name'] ?? '')),
    'department_id' => trim((string) ($_GET['department_id'] ?? '')),
    'program_id' => trim((string) ($_GET['program_id'] ?? '')),
    'gender' => trim((string) ($_GET['gender'] ?? '')),
    'year_level' => trim((string) ($_GET['year_level'] ?? '')),
];

if ($user['role'] === 'admin' && is_post()) {
    if (!verify_csrf_token($_POST['csrf_token'] ?? null)) {
        set_flash('error', 'Invalid request token.');
        redirect('/manage_students.php');
    }

    $action = $_POST['action'] ?? '';
    $studentId = (int) ($_POST['student_id'] ?? 0);

    if ($action === 'delete' && $studentId > 0) {
        $student = fetch_student_by_id($pdo, $studentId);

        if ($student === null) {
            set_flash('error', 'Student record not found.');
            redirect('/manage_students.php');
        }

        try {
            $pdo->beginTransaction();

            $deleteStudent = $pdo->prepare('DELETE FROM students WHERE id = :id');
            $deleteStudent->execute(['id' => $studentId]);

            $deleteUser = $pdo->prepare('DELETE FROM users WHERE id = :id');
            $deleteUser->execute(['id' => $student['user_id']]);

            $pdo->commit();

            delete_document_file($student['document_path'] ?? null);
            set_flash('success', 'Student record deleted successfully.');
        } catch (Throwable $throwable) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            set_flash('error', 'Unable to delete student: ' . $throwable->getMessage());
        }

        redirect('/manage_students.php');
    }
}

$students = [];
$loadError = null;

try {
    $students = fetch_students($pdo, $filters, $user);
} catch (Throwable $throwable) {
    $loadError = 'Unable to load filtered student records right now. ' . $throwable->getMessage();
}

if ($loadError === null && ($user['role'] === 'admin') && (($_GET['export'] ?? '') === 'csv')) {
    export_students_to_csv($students);
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-header">
    <div>
        <h2>Manage Students</h2>
        <p class="muted">Search, edit, export, and delete student records.</p>
    </div>
    <a class="btn btn-secondary" href="manage_students.php?<?= e(http_build_query(array_merge($filters, ['export' => 'csv']))) ?>">Export CSV</a>
</section>

<section class="card">
    <form method="get" class="filter-grid">
        <label>
            Search
            <input type="text" name="name" value="<?= e($filters['name']) ?>" placeholder="Name or student ID">
        </label>

        <label>
            Department
            <select name="department_id">
                <option value="">All departments</option>
                <?php foreach ($departments as $department): ?>
                    <option value="<?= e((string) $department['id']) ?>" <?= $filters['department_id'] === (string) $department['id'] ? 'selected' : '' ?>>
                        <?= e($department['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Program
            <select name="program_id">
                <option value="">All programs</option>
                <?php foreach ($programs as $program): ?>
                    <option value="<?= e((string) $program['id']) ?>" <?= $filters['program_id'] === (string) $program['id'] ? 'selected' : '' ?>>
                        <?= e($program['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Gender
            <select name="gender">
                <option value="">All genders</option>
                <?php foreach (['Male', 'Female', 'Other'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $filters['gender'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <label>
            Year Level
            <select name="year_level">
                <option value="">All year levels</option>
                <?php foreach (['1st Year', '2nd Year', '3rd Year', '4th Year', '5th Year'] as $option): ?>
                    <option value="<?= e($option) ?>" <?= $filters['year_level'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                <?php endforeach; ?>
            </select>
        </label>

        <div class="form-actions align-end">
            <button type="submit" class="btn btn-primary">Apply Filters</button>
        </div>
    </form>
</section>

<section class="card">
    <?php if ($loadError !== null): ?>
        <div class="alert alert-error">
            <p><?= e($loadError) ?></p>
        </div>
    <?php elseif ($students === []): ?>
        <p class="muted">No student records matched your current filters.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead>
                <tr>
                    <th>Student ID</th>
                    <th>Name</th>
                    <th>Gender</th>
                    <th>Year Level</th>
                    <th>Department</th>
                    <th>Program</th>
                    <th>Email</th>
                    <th>Contact Number</th>
                    <th>Document</th>
                    <th>Actions</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($students as $student): ?>
                    <tr>
                        <td><?= e($student['student_id']) ?></td>
                        <td><?= e(format_full_name($student, true)) ?></td>
                        <td><?= e($student['gender']) ?></td>
                        <td><?= e($student['year_level']) ?></td>
                        <td><?= e($student['department_name']) ?></td>
                        <td><?= e($student['program_name']) ?></td>
                        <td><?= e($student['email']) ?></td>
                        <td><?= e($student['contact_number']) ?></td>
                        <td>
                            <?php if (!empty($student['document_path'])): ?>
                                <a href="<?= e($student['document_path']) ?>" target="_blank" rel="noopener noreferrer">View</a>
                            <?php else: ?>
                                None
                            <?php endif; ?>
                        </td>
                        <td class="actions">
                            <a class="btn btn-small btn-secondary" href="edit_student.php?id=<?= e((string) $student['id']) ?>">Edit</a>
                            <form method="post" onsubmit="return confirm('Delete this student record?');">
                                <input type="hidden" name="csrf_token" value="<?= e(generate_csrf_token()) ?>">
                                <input type="hidden" name="student_id" value="<?= e((string) $student['id']) ?>">
                                <input type="hidden" name="action" value="delete">
                                <button type="submit" class="btn btn-small btn-danger">Delete</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
