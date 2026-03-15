<?php
declare(strict_types=1);

require_once __DIR__ . '/functions.php';
require_login(['admin', 'student']);

$user = current_user();
$pageTitle = page_title('Dashboard');
$students = [];
$counts = [];

if ($user['role'] === 'admin') {
    $counts = fetch_student_counts($pdo);
    $students = array_slice(fetch_students($pdo), 0, 5);
} else {
    $students = fetch_students($pdo, [], $user);
}

require __DIR__ . '/partials/header.php';
?>
<section class="page-header">
    <div>
        <span class="eyebrow">Overview</span>
        <h2>Dashboard</h2>
        <p class="muted">
            <?php if ($user['role'] === 'admin'): ?>
                Manage student records, add new students, and monitor the system.
            <?php else: ?>
                View your student profile and account details.
            <?php endif; ?>
        </p>
    </div>
</section>

<?php if ($user['role'] === 'admin'): ?>
    <section class="card hero-card">
        <div>
            <span class="eyebrow">Administrator</span>
            <h3>Keep student records accurate and easy to manage.</h3>
            <p class="muted">Use this dashboard to monitor enrollment data, review recent additions, and jump into student management tasks quickly.</p>
        </div>
        <a class="btn btn-primary" href="add_student.php">Register New Student</a>
    </section>

    <section class="quick-links">
        <a class="card quick-link-card" href="add_student.php">
            <span class="eyebrow">Create</span>
            <h3>Add Student</h3>
            <p>Register a new student account and save their academic details.</p>
        </a>
        <a class="card quick-link-card" href="manage_students.php">
            <span class="eyebrow">Manage</span>
            <h3>Student Records</h3>
            <p>Search, edit, export, and review all registered student information.</p>
        </a>
    </section>

    <section class="stats-grid">
        <article class="card stat-card">
            <h3>Total Students</h3>
            <p><?= e((string) $counts['total_students']) ?></p>
        </article>
        <article class="card stat-card">
            <h3>Male Students</h3>
            <p><?= e((string) $counts['male_students']) ?></p>
        </article>
        <article class="card stat-card">
            <h3>Female Students</h3>
            <p><?= e((string) $counts['female_students']) ?></p>
        </article>
    </section>

    <section class="card">
        <div class="section-heading">
            <h3>Recent Students</h3>
            <a class="btn btn-secondary" href="manage_students.php">View All</a>
        </div>
        <?php if ($students === []): ?>
            <p class="muted">No students have been added yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead>
                    <tr>
                        <th>Student ID</th>
                        <th>Name</th>
                        <th>Department</th>
                        <th>Program</th>
                        <th>Year Level</th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($students as $student): ?>
                        <tr>
                            <td><?= e($student['student_id']) ?></td>
                            <td><?= e(format_full_name($student, true)) ?></td>
                            <td><?= e($student['department_name']) ?></td>
                            <td><?= e($student['program_name']) ?></td>
                            <td><?= e($student['year_level']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
<?php else: ?>
    <section class="card hero-card">
        <div>
            <span class="eyebrow">Student Account</span>
            <h3>Your profile and academic details are available below.</h3>
            <p class="muted">Review your registered information and confirm that your program and uploaded documents are correct.</p>
        </div>
    </section>

    <section class="card profile-card">
        <h3>My Profile</h3>
        <?php if ($students === []): ?>
            <p class="muted">Your student profile is not linked yet. Ask the administrator to connect your account.</p>
        <?php else: ?>
            <?php $student = $students[0]; ?>
            <div class="profile-grid">
                <div><strong>Student ID:</strong> <?= e($student['student_id']) ?></div>
                <div><strong>Name:</strong> <?= e(format_full_name($student)) ?></div>
                <div><strong>Gender:</strong> <?= e($student['gender']) ?></div>
                <div><strong>Year Level:</strong> <?= e($student['year_level']) ?></div>
                <div><strong>Date of Birth:</strong> <?= e($student['dob']) ?></div>
                <div><strong>Email:</strong> <?= e($student['email']) ?></div>
                <div><strong>Contact Number:</strong> <?= e($student['contact_number']) ?></div>
                <div><strong>Department:</strong> <?= e($student['department_name']) ?></div>
                <div><strong>Program:</strong> <?= e($student['program_name']) ?></div>
                <div><strong>Document:</strong>
                    <?php if (!empty($student['document_path'])): ?>
                        <a href="<?= e($student['document_path']) ?>" target="_blank" rel="noopener noreferrer">View File</a>
                    <?php else: ?>
                        None uploaded
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
    </section>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
