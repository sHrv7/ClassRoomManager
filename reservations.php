
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();
$userId = $user['id'] ?? $user['ID'];

$role = $user['Role'] ?? '';
$isAdmin = $role === 'Admin';
$isProfessor = $role === 'Professor';

if (!$isAdmin && !$isProfessor) {
    http_response_code(403);
    exit('Access denied.');
}

$pdo = db();
$pageTitle = 'My reservations';

$error = '';
$success = (string) ($_SESSION['reservation_notice'] ?? '');
$successType = (string) ($_SESSION['reservation_notice_type'] ?? 'success');
unset($_SESSION['reservation_notice']);
unset($_SESSION['reservation_notice_type']);

function reservation_redirect(): void {
    redirect('reservations.php');
}

function create_reservation_notification(
    PDO $pdo,
    int $subjectId,
    int $professorId,
    string $title,
    string $message
): void {
    $stmt = $pdo->prepare('
        INSERT INTO notifications
            (subject_id, professor_id, category_id, title, message)
        VALUES
            (:subject_id, :professor_id, 2, :title, :message)
    ');

    $stmt->execute([
        'subject_id' => $subjectId,
        'professor_id' => $professorId,
        'title' => $title,
        'message' => $message
    ]);

    $delivery = send_notification_emails([
        'subject_id' => $subjectId,
        'professor_id' => $professorId,
        'title' => $title,
        'message' => $message,
    ]);
    $_SESSION['reservation_notice'] = match ($delivery['status']) {
        'sent' => 'Rezervacija je spremljena, a obavijest e-poštom poslana na '
            . $delivery['count'] . ' studenata.',
        'no_recipients' => 'Rezervacija je spremljena; nema aktivnih studenata za e-mail obavijest.',
        'not_configured' => 'Rezervacija je spremljena, ali EmailJS nije konfiguriran.',
        default => 'Rezervacija je spremljena, ali slanje e-mail obavijesti nije uspjelo: '
            . ($delivery['reason'] ?? $delivery['status']),
    };
    $_SESSION['reservation_notice_type'] = in_array($delivery['status'], ['sent', 'no_recipients'], true)
        ? 'success'
        : 'error';
}

// Handle create, update and delete.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        // DELETE RESERVATION
        if ($action === 'delete') {
            $id = (int)($_POST['reservation_id'] ?? 0);

            // Get reservation details before deleting.
            $sql = '
                SELECT
                    r.subject_id,
                    r.starts_at,
                    r.ends_at,
                    r.user_id,
                    c.room_code
                FROM reservations r
                JOIN classrooms c ON c.id = r.classroom_id
                WHERE r.id = :id
            ';

            $params = ['id' => $id];

            if (!$isAdmin) {
                $sql .= ' AND r.user_id = :user_id';
                $params['user_id'] = $userId;
            }

            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);

            $reservation = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($reservation) {
                $sql = 'DELETE FROM reservations WHERE id = :id';
                $params = ['id' => $id];

                if (!$isAdmin) {
                    $sql .= ' AND user_id = :user_id';
                    $params['user_id'] = $userId;
                }

                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);

                $stmt = $pdo->prepare('
                    SELECT name
                    FROM subjects
                    WHERE id = :subject_id
                ');

                $stmt->execute([
                    'subject_id' => $reservation['subject_id']
                ]);

                $subjectName = $stmt->fetchColumn();

                // Notify subscribers of the subject.
                create_reservation_notification(
                    $pdo,
                    (int)$reservation['subject_id'],
                    (int)$reservation['user_id'],
                    'Predavanje otkazano',
                    'Predavanje iz predmeta ' . $subjectName . ' u učionici ' . $reservation['room_code'] .
                    ' na datum ' .
                    date('d.m.Y H:i', strtotime($reservation['starts_at'])) .
                    ' je otkazano.'
                );
            }

            reservation_redirect();
        }

        // CREATE OR UPDATE RESERVATION
        if ($action === 'save') {
            $id = (int)($_POST['reservation_id'] ?? 0);
            $classroomId = (int)($_POST['classroom_id'] ?? 0);
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $expectedStudents = filter_var(
                $_POST['expected_students'] ?? null,
                FILTER_VALIDATE_INT,
                ['options' => ['min_range' => 1]]
            );
            $startsAt = str_replace('T', ' ', $_POST['starts_at'] ?? '');
            $endsAt = str_replace('T', ' ', $_POST['ends_at'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (!$classroomId || !$subjectId || !$startsAt || !$endsAt || $expectedStudents === false) {
                $error = 'Please complete all required fields.';
            } elseif (
                strtotime($startsAt) === false ||
                strtotime($endsAt) === false ||
                strtotime($endsAt) <= strtotime($startsAt)
            ) {
                $error = 'The end time must be later than the start time.';
            } else {
                // Check that the selected classroom exists.
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM classrooms WHERE id = ?'
                );
                $check->execute([$classroomId]);
                $classroomExists = $check->fetchColumn();

                // Check that the selected subject exists.
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM subjects WHERE id = ?'
                );
                $check->execute([$subjectId]);
                $subjectExists = $check->fetchColumn();

                if (!$classroomExists || !$subjectExists) {
                    $error = 'Please select a valid classroom and subject.';
                } else {
                    // Get the selected subject name.
                    $stmt = $pdo->prepare('
                        SELECT name
                        FROM subjects
                        WHERE id = :subject_id
                    ');
                    $stmt->execute(['subject_id' => $subjectId]);
                    $subjectName = $stmt->fetchColumn();

                    // Get the selected classroom name.
                    $stmt = $pdo->prepare('
                        SELECT room_code
                        FROM classrooms
                        WHERE id = :classroom_id
                    ');
                    $stmt->execute(['classroom_id' => $classroomId]);
                    $classroomName = $stmt->fetchColumn();

                    $stmt = $pdo->prepare('SELECT capacity FROM classrooms WHERE id = :classroom_id');
                    $stmt->execute(['classroom_id' => $classroomId]);
                    $classroomCapacity = (int) $stmt->fetchColumn();

                    if ($expectedStudents > $classroomCapacity) {
                        $error = 'Expected student count cannot exceed the classroom capacity of '
                            . $classroomCapacity . '.';
                    }

                    // Load the old reservation when editing.
                    $oldReservation = null;

                    if ($id > 0) {
                        $sql = '
                            SELECT
                                r.subject_id,
                                s.name AS subject_name,
                                r.classroom_id,
                                c.room_code,
                                r.starts_at,
                                r.ends_at,
                                r.notes,
                                r.expected_students,
                                r.user_id
                            FROM reservations r
                            JOIN subjects s ON s.id = r.subject_id
                            JOIN classrooms c ON c.id = r.classroom_id
                            WHERE r.id = :id
                        ';

                        $params = ['id' => $id];

                        if (!$isAdmin) {
                            $sql .= ' AND r.user_id = :user_id';
                            $params['user_id'] = $userId;
                        }

                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);

                        $oldReservation = $stmt->fetch(PDO::FETCH_ASSOC);

                        if (!$oldReservation) {
                            $error = 'Reservation not found or access denied.';
                        }
                    }

                    if (!$error) {
                        // Prevent overlapping reservations in the same classroom.
                        $sql = '
                            SELECT COUNT(*)
                            FROM reservations
                            WHERE classroom_id = :classroom_id
                              AND starts_at < :ends_at
                              AND ends_at > :starts_at
                        ';

                        $params = [
                            'classroom_id' => $classroomId,
                            'starts_at' => $startsAt,
                            'ends_at' => $endsAt
                        ];

                        if ($id > 0) {
                            $sql .= ' AND id != :id';
                            $params['id'] = $id;
                        }

                        $check = $pdo->prepare($sql);
                        $check->execute($params);

                        if ($check->fetchColumn() > 0) {
                            $error = 'This classroom is already reserved during that time.';
                        } else {
                            // UPDATE EXISTING RESERVATION
                            if ($id > 0) {
                                $sql = '
                                    UPDATE reservations
                                    SET classroom_id = :classroom_id,
                                        subject_id = :subject_id,
                                        starts_at = :starts_at,
                                        ends_at = :ends_at,
                                        expected_students = :expected_students,
                                        notes = :notes
                                    WHERE id = :id
                                ';

                                $params = [
                                    'classroom_id' => $classroomId,
                                    'subject_id' => $subjectId,
                                    'starts_at' => $startsAt,
                                    'ends_at' => $endsAt,
                                    'expected_students' => $expectedStudents,
                                    'notes' => $notes,
                                    'id' => $id
                                ];

                                if (!$isAdmin) {
                                    $sql .= ' AND user_id = :user_id';
                                    $params['user_id'] = $userId;
                                }

                                $stmt = $pdo->prepare($sql);
                                $stmt->execute($params);

                                // Collect all changes into one notification.
                                $changes = [];

                                // Record only fields that actually changed.
                                if ((int)$oldReservation['subject_id'] !== $subjectId) {
                                    $changes[] = 'Predmet: ' .
                                        $oldReservation['subject_name'] .
                                        ' → ' . $subjectName;
                                }

                                if ((int)$oldReservation['classroom_id'] !== $classroomId) {
                                    $changes[] = 'Učionica: ' .
                                        $oldReservation['room_code'] .
                                        ' → ' . $classroomName;
                                }

                                if (strtotime($oldReservation['starts_at']) !== strtotime($startsAt)) {
                                    $changes[] = 'Početak: ' .
                                        date('d.m.Y H:i', strtotime($oldReservation['starts_at'])) .
                                        ' → ' .
                                        date('d.m.Y H:i', strtotime($startsAt));
                                }

                                if (strtotime($oldReservation['ends_at']) !== strtotime($endsAt)) {
                                    $changes[] = 'Završetak: ' .
                                        date('d.m.Y H:i', strtotime($oldReservation['ends_at'])) .
                                        ' → ' .
                                        date('d.m.Y H:i', strtotime($endsAt));
                                }

                                // Notes changed?
                                if ($oldReservation['notes'] !== $notes) {
                                    $oldNotes = $oldReservation['notes'] ?: 'Bez napomena';
                                    $newNotes = $notes ?: 'Bez napomena';

                                    $changes[] = 'Napomene: ' .
                                        $oldNotes . ' → ' . $newNotes;
                                }

                                if ($oldReservation['expected_students'] === null
                                    || (int) $oldReservation['expected_students'] !== $expectedStudents) {
                                    $changes[] = 'Očekivani broj studenata: '
                                        . ($oldReservation['expected_students'] === null
                                            ? 'nije unesen'
                                            : (int) $oldReservation['expected_students'])
                                        . ' → ' . $expectedStudents;
                                }

                                // Create notifications only if something changed.
                                if (!empty($changes)) {
                                    $notificationMessage =
                                        "Došlo je do promjena u rezervaciji:\n- " .
                                        implode("\n- ", $changes);

                                    // Notify subscribers of the new subject.
                                    create_reservation_notification(
                                        $pdo,
                                        $subjectId,
                                        (int)$oldReservation['user_id'],
                                        'Promjena rezervacije',
                                        $notificationMessage
                                    );
                                } else {
                                    $_SESSION['reservation_notice'] = 'Nema promjena; e-mail obavijest nije poslana.';
                                    $_SESSION['reservation_notice_type'] = 'success';
                                }
                            } else {
                                // CREATE NEW RESERVATION.
                                $stmt = $pdo->prepare('
                                    INSERT INTO reservations
                                        (user_id, classroom_id, subject_id,
                                         starts_at, ends_at, expected_students, notes)
                                    VALUES
                                        (:user_id, :classroom_id, :subject_id,
                                         :starts_at, :ends_at, :expected_students, :notes)
                                ');

                                $stmt->execute([
                                    'user_id' => $userId,
                                    'classroom_id' => $classroomId,
                                    'subject_id' => $subjectId,
                                    'starts_at' => $startsAt,
                                    'ends_at' => $endsAt,
                                    'expected_students' => $expectedStudents,
                                    'notes' => $notes
                                ]);

                                // Notify subscribers of the new reservation.
                                create_reservation_notification(
                                    $pdo,
                                    $subjectId,
                                    (int)$userId,
                                    'Nova rezervacija',
                                    'Kreirana je nova rezervacija za predmet "' .
                                    $subjectName . '", učionica: ' .
                                    $classroomName . ', početak: ' .
                                    date('d.m.Y H:i', strtotime($startsAt)) . ".\nOčekivani broj studenata: "
                                    . $expectedStudents . ".\nNapomene: " . $notes
                                );
                            }

                            reservation_redirect();
                        }
                    }
                }
            }
        }
    }
}

// LOAD CLASSROOMS AND SUBJECTS FOR THE FORM.
$classrooms = $pdo->query('
    SELECT id, room_code, capacity
    FROM classrooms
    ORDER BY room_code
')->fetchAll(PDO::FETCH_ASSOC);

if ($isAdmin) {
    $subjects = $pdo->query('
        SELECT id, name
        FROM subjects
        ORDER BY name
    ')->fetchAll(PDO::FETCH_ASSOC);
} else {
    $stmt = $pdo->prepare('
        SELECT s.id, s.name
        FROM subjects s
        INNER JOIN user_subjects us ON us.subject_id = s.id
        WHERE us.user_id = :user_id
        ORDER BY s.name
    ');

    $stmt->execute(['user_id' => $userId]);
    $subjects = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// LOAD RESERVATION FOR EDITING.
$editing = null;

if (isset($_GET['edit'])) {
    $sql = '
        SELECT *
        FROM reservations
        WHERE id = :id
    ';

    $params = ['id' => (int) $_GET['edit']];

    if (!$isAdmin) {
        $sql .= ' AND user_id = :user_id';
        $params['user_id'] = $userId;
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// LOAD THIS PROFESSOR'S FUTURE AND ONGOING RESERVATIONS.
if ($isAdmin) {
    $stmt = $pdo->query('
        SELECT
            r.id,
            r.starts_at,
            r.ends_at,
            r.notes,
            r.expected_students,
            s.name AS subject_name,
            c.room_code,
            c.capacity,
            u.Username AS professor_name
        FROM reservations r
        JOIN subjects s ON s.id = r.subject_id
        JOIN classrooms c ON c.id = r.classroom_id
        JOIN users u ON u.id = r.user_id
        WHERE r.ends_at > NOW()
        ORDER BY r.starts_at ASC
    ');
} else {
    $stmt = $pdo->prepare('
        SELECT
            r.id,
            r.starts_at,
            r.ends_at,
            r.notes,
            r.expected_students,
            s.name AS subject_name,
            c.room_code,
            c.capacity,
            u.Username AS professor_name
        FROM reservations r
        JOIN subjects s ON s.id = r.subject_id
        JOIN classrooms c ON c.id = r.classroom_id
        JOIN users u ON u.id = r.user_id
        WHERE r.user_id = :user_id
          AND r.ends_at > NOW()
        ORDER BY r.starts_at ASC
    ');

    $stmt->execute(['user_id' => $userId]);
}

$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/header.php';
?>

<section class="page-shell reservations-page">

    <div class="reservations-heading">
        <div>
            <p class="eyebrow"><?= $isAdmin ? 'ADMINISTRATION' : 'PROFESSOR AREA' ?></p>
            <h1><?= $isAdmin ? 'All reservations' : 'My reservations' ?></h1>
            <p class="lead">
                <?= $isAdmin
                    ? 'View and manage reservations from all professors.'
                    : 'Create and manage your classroom reservations.' ?>
            </p>
        </div>
    </div>

    <?php if ($success): ?>
        <div class="notice <?= $successType === 'error' ? 'notice-error' : 'notice-success' ?>" role="status">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div class="notice notice-error">
            <?= e($error) ?>
        </div>
    <?php endif; ?>

    <div class="reservations-layout">

        <section class="reservation-form-panel">
            <h2><?= $editing ? 'Edit reservation' : 'New reservation' ?></h2>

            <form method="post" class="reservation-form">
                <input type="hidden" name="csrf_token"
                       value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="save">
                <input type="hidden" name="reservation_id"
                       value="<?= (int)($editing['id'] ?? 0) ?>">

                <label for="subject_id">Subject</label>
                <select name="subject_id" id="subject_id" required>
                    <option value="">Choose a subject</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int)$subject['id'] ?>"
                            <?= (int)($editing['subject_id'] ?? 0) === (int)$subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>

                <label for="classroom_id">Classroom</label>
                <select name="classroom_id" id="classroom_id" required>
                    <option value="">Choose a classroom</option>
                    <?php foreach ($classrooms as $classroom): ?>
                        <option value="<?= (int)$classroom['id'] ?>"
                            data-capacity="<?= (int)$classroom['capacity'] ?>"
                            <?= (int)($editing['classroom_id'] ?? 0) === (int)$classroom['id'] ? 'selected' : '' ?>>
                            <?= e($classroom['room_code']) ?>
                            (Capacity: <?= (int)$classroom['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

                  <label for="expected_students">Očekivani broj studenata</label>
                  <input type="number"
                      name="expected_students"
                      id="expected_students"
                      min="1"
                      value="<?= e($_POST['expected_students'] ?? ($editing['expected_students'] ?? '')) ?>"
                      required>
                  <small id="capacity-hint" class="muted" aria-live="polite"></small>

                <label for="starts_at">Start</label>
                <input type="datetime-local"
                       name="starts_at"
                       id="starts_at"
                       value="<?= $editing ? e(date('Y-m-d\TH:i', strtotime($editing['starts_at']))) : '' ?>"
                       required>

                <label for="ends_at">End</label>
                <input type="datetime-local"
                       name="ends_at"
                       id="ends_at"
                       value="<?= $editing ? e(date('Y-m-d\TH:i', strtotime($editing['ends_at']))) : '' ?>"
                       required>

                <label for="notes">Notes (optional)</label>
                <textarea name="notes" id="notes" rows="4"><?= e($editing['notes'] ?? '') ?></textarea>

                <button type="submit" class="button button-primary button-full">
                    <?= $editing ? 'Save changes' : 'Create reservation' ?>
                </button>

                <?php if ($editing): ?>
                    <a href="reservations.php" class="button button-secondary button-full">
                        Cancel editing
                    </a>
                <?php endif; ?>
            </form>
        </section>

        <section class="reservations-list-panel">
            <div class="panel-heading">
                <h2><?= $isAdmin ? 'All reservations' : 'Your reservations' ?></h2>
                <span class="reservation-count">
                    <?= count($reservations) ?>
                </span>
            </div>

            <?php if (!$reservations): ?>
                <p class="empty-reservations">
                    <?= $isAdmin
                        ? 'There are no upcoming reservations.'
                        : "You haven't created any reservations yet." ?>
                </p>
            <?php else: ?>
                <div class="reservation-list">
                    <?php foreach ($reservations as $reservation): ?>
                        <article class="reservation-item">
                            <div class="reservation-item-heading">
                                <div>
                                    <h3><?= e($reservation['subject_name']) ?></h3>
                                    <?php if ($isAdmin): ?>
                                        <p class="reservation-professor">
                                            Professor: <?= e($reservation['professor_name']) ?>
                                        </p>
                                    <?php endif; ?>
                                    <p class="reservation-room">
                                        Classroom <?= e($reservation['room_code']) ?>
                                    </p>
                                    <p class="reservation-capacity">
                                        <?= $reservation['expected_students'] === null
                                            ? 'Expected student count not recorded'
                                            : 'Expected students: ' . (int) $reservation['expected_students']
                                                . ' / capacity: ' . (int) $reservation['capacity'] ?>
                                    </p>
                                </div>
                            </div>

                            <div class="reservation-meta">
                                <div>
                                    <span>Date</span>
                                    <strong>
                                        <?= e(date('d.m.Y', strtotime($reservation['starts_at']))) ?>
                                    </strong>
                                </div>
                                <div>
                                    <span>Time</span>
                                    <strong>
                                        <?= e(date('H:i', strtotime($reservation['starts_at']))) ?>
                                        –
                                        <?= e(date('H:i', strtotime($reservation['ends_at']))) ?>
                                    </strong>
                                </div>
                            </div>

                            <?php if (!empty($reservation['notes'])): ?>
                                <p class="reservation-notes">
                                    <?= e($reservation['notes']) ?>
                                </p>
                            <?php endif; ?>

                            <div class="reservation-actions">
                                <a class="button button-secondary"
                                   href="reservations.php?edit=<?= (int)$reservation['id'] ?>">
                                    Edit
                                </a>

                                <form method="post"
                                      onsubmit="return confirm('Are you sure you want to delete this reservation?');">
                                    <input type="hidden" name="csrf_token"
                                           value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="reservation_id"
                                           value="<?= (int)$reservation['id'] ?>">
                                    <button type="submit" class="button reservation-delete">
                                        Delete
                                    </button>
                                </form>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

    </div>
</section>

<script>
const classroomSelect = document.getElementById('classroom_id');
const expectedStudentsInput = document.getElementById('expected_students');
const capacityHint = document.getElementById('capacity-hint');

function updateExpectedStudentLimit() {
    const selectedOption = classroomSelect.options[classroomSelect.selectedIndex];
    if (!selectedOption || !selectedOption.dataset.capacity) {
        expectedStudentsInput.removeAttribute('max');
        capacityHint.textContent = '';
        return;
    }

    const capacity = selectedOption.dataset.capacity;
    expectedStudentsInput.max = capacity;
    capacityHint.textContent = `Kapacitet odabrane učionice: ${capacity}.`;
}

classroomSelect.addEventListener('change', updateExpectedStudentLimit);
updateExpectedStudentLimit();
</script>

<?php require __DIR__ . '/footer.php'; ?>
