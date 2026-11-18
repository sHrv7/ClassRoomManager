
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

require_login();

$user = current_user();

if (($user['Role'] ?? '') !== 'Profesor') {
    http_response_code(403);
    exit('Access denied. This page is for professors only.');
}

$pdo = db();
$pageTitle = 'My reservations';

$error = '';
$success = '';

function reservation_redirect(): void {
    redirect('reservations.php');
}

// Handle create, update and delete
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? '')) {
        $error = 'Your session expired. Please try again.';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'delete') {
            $id = (int)($_POST['reservation_id'] ?? 0);

            $stmt = $pdo->prepare(
                'DELETE FROM reservations WHERE id = :id AND user_id = :user_id'
            );
            $stmt->execute([
                'id' => $id,
                'user_id' => $user['id']
            ]);

            reservation_redirect();
        }

        if ($action === 'save') {
            $id = (int)($_POST['reservation_id'] ?? 0);
            $classroomId = (int)($_POST['classroom_id'] ?? 0);
            $subjectId = (int)($_POST['subject_id'] ?? 0);
            $startsAt = str_replace('T', ' ', $_POST['starts_at'] ?? '');
            $endsAt = str_replace('T', ' ', $_POST['ends_at'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (!$classroomId || !$subjectId || !$startsAt || !$endsAt) {
                $error = 'Please complete all required fields.';
            } elseif (strtotime($endsAt) <= strtotime($startsAt)) {
                $error = 'The end time must be later than the start time.';
            } else {
                // Check that the selected classroom and subject exist.
                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM classrooms WHERE id = ?'
                );
                $check->execute([$classroomId]);
                $classroomExists = $check->fetchColumn();

                $check = $pdo->prepare(
                    'SELECT COUNT(*) FROM subjects WHERE id = ?'
                );
                $check->execute([$subjectId]);
                $subjectExists = $check->fetchColumn();

                if (!$classroomExists || !$subjectExists) {
                    $error = 'Please select a valid classroom and subject.';
                } else {
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
                        if ($id > 0) {
                            // Update only a reservation belonging to this professor.
                            $stmt = $pdo->prepare('
                                UPDATE reservations
                                SET classroom_id = :classroom_id,
                                    subject_id = :subject_id,
                                    starts_at = :starts_at,
                                    ends_at = :ends_at,
                                    notes = :notes
                                WHERE id = :id AND user_id = :user_id
                            ');

                            $stmt->execute([
                                'classroom_id' => $classroomId,
                                'subject_id' => $subjectId,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'notes' => $notes,
                                'id' => $id,
                                'user_id' => $user['id']
                            ]);
                        } else {
                            // Create a new reservation for this professor.
                            $stmt = $pdo->prepare('
                                INSERT INTO reservations
                                    (user_id, classroom_id, subject_id,
                                     starts_at, ends_at, notes)
                                VALUES
                                    (:user_id, :classroom_id, :subject_id,
                                     :starts_at, :ends_at, :notes)
                            ');

                            $stmt->execute([
                                'user_id' => $user['id'],
                                'classroom_id' => $classroomId,
                                'subject_id' => $subjectId,
                                'starts_at' => $startsAt,
                                'ends_at' => $endsAt,
                                'notes' => $notes
                            ]);
                        }

                        reservation_redirect();
                    }
                }
            }
        }
    }
}

// Load classrooms and subjects for the form.
$classrooms = $pdo->query(
    'SELECT id, room_code, capacity FROM classrooms ORDER BY room_code'
)->fetchAll(PDO::FETCH_ASSOC);

$subjects = $pdo->query(
    'SELECT id, name FROM subjects ORDER BY name'
)->fetchAll(PDO::FETCH_ASSOC);

// Load a reservation for editing, but only if it belongs to this professor.
$editing = null;

if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare('
        SELECT *
        FROM reservations
        WHERE id = :id AND user_id = :user_id
    ');
    $stmt->execute([
        'id' => (int)$_GET['edit'],
        'user_id' => $user['id']
    ]);
    $editing = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

// Load this professor's reservations.
$stmt = $pdo->prepare('
    SELECT
        r.id,
        r.starts_at,
        r.ends_at,
        r.notes,
        s.name AS subject_name,
        c.room_code
    FROM reservations r
    JOIN subjects s ON s.id = r.subject_id
    JOIN classrooms c ON c.id = r.classroom_id
    WHERE r.user_id = :user_id
    ORDER BY r.starts_at DESC
');
$stmt->execute(['user_id' => $user['id']]);
$reservations = $stmt->fetchAll(PDO::FETCH_ASSOC);

require __DIR__ . '/header.php';
?>

<section class="page-shell reservations-page">

    <div class="reservations-heading">
        <div>
            <p class="eyebrow">PROFESSOR AREA</p>
            <h1>My reservations</h1>
            <p class="lead">Create and manage your classroom reservations.</p>
        </div>
    </div>

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
                            <?= (int)($editing['classroom_id'] ?? 0) === (int)$classroom['id'] ? 'selected' : '' ?>>
                            <?= e($classroom['room_code']) ?>
                            (Capacity: <?= (int)$classroom['capacity'] ?>)
                        </option>
                    <?php endforeach; ?>
                </select>

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
                <h2>Your reservations</h2>
                <span class="reservation-count">
                    <?= count($reservations) ?>
                </span>
            </div>

            <?php if (!$reservations): ?>
                <p class="empty-reservations">
                    You haven't created any reservations yet.
                </p>
            <?php else: ?>
                <div class="reservation-list">
                    <?php foreach ($reservations as $reservation): ?>
                        <article class="reservation-item">
                            <div class="reservation-item-heading">
                                <div>
                                    <h3><?= e($reservation['subject_name']) ?></h3>
                                    <p class="reservation-room">
                                        Classroom <?= e($reservation['room_code']) ?>
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

<?php require __DIR__ . '/footer.php'; ?>
