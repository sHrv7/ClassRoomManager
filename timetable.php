
<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/functions.php';

// Existing timetable logic
$user = current_user();
$pageTitle = 'Raspored';

// Get selected week
$week = $_GET['week'] ?? date('Y-m-d');

$weekDate = DateTime::createFromFormat('Y-m-d', $week);

if (!$weekDate) {
    $weekDate = new DateTime();
}

$weekDate->modify('monday this week');

$weekStart = $weekDate->format('Y-m-d');
$weekEnd = (clone $weekDate)->modify('+7 days')->format('Y-m-d');

$previousWeek = (clone $weekDate)
    ->modify('-7 days')->format('Y-m-d');

$nextWeek = (clone $weekDate)
    ->modify('+7 days')->format('Y-m-d');

// Get subjects
$subjects = db()->query(
    "SELECT id, name FROM subjects ORDER BY name"
)->fetchAll();

// Get professors
$users = db()->query("
    SELECT DISTINCT u.id, u.username
    FROM reservations r
    JOIN users u ON u.id = r.user_id
    ORDER BY u.username
")->fetchAll();

// Get reservations for selected week
$sql = "
    SELECT
        r.id,
        r.starts_at,
        r.ends_at,
        r.notes,
        s.name AS subject_name,
        c.room_code,
        c.capacity,
        u.username AS professor_name

    FROM reservations r

    JOIN subjects s ON r.subject_id = s.id
    JOIN classrooms c ON r.classroom_id = c.id
    JOIN users u ON r.user_id = u.id

    WHERE r.starts_at < :week_end
    AND r.ends_at > :week_start

    ORDER BY r.starts_at
";

$stmt = db()->prepare($sql);

$stmt->execute([
    ':week_start' => $weekStart . ' 00:00:00',
    ':week_end' => $weekEnd . ' 00:00:00'
]);

$reservations = $stmt->fetchAll();

// Prepare weekdays
$days = [];

for ($i = 0; $i < 5; $i++) {

    $day = (clone $weekDate)->modify("+$i days");

    $days[] = [
        'date' => $day->format('Y-m-d'),
        'name' => $day->format('D'),
        'number' => $day->format('j')
    ];
}

$weekLabel =
    $weekDate->format('d M') .
    ' – ' .
    (clone $weekDate)->modify('+4 days')->format('d M Y');

$hours = range(8, 18);

// Shared website header
require __DIR__ . '/header.php';
?>

<section class="page">

    <!-- PAGE INTRODUCTION -->
    <section class="page-heading">

        <div>
            <p class="eyebrow">FAKULTETSKE UČIONICE, JEDNOSTAVNIJE</p>

            <h1>Raspored<br><span>predavanja.</span></h1>

            <p class="subtitle">
                Pregled predavanja, učionica i termina na jednom mjestu.
            </p>
        </div>

        <div class="heading-date">
            <?= e(date('l, d F Y')) ?>
        </div>

    </section>


    <!-- WEEK NAVIGATION AND FILTERS -->
    <section class="toolbar">

        <div class="week-navigation">

            <a href="?week=<?= e($previousWeek) ?>"
               class="arrow-button"
               aria-label="Prethodni tjedan">
                &#8249;
            </a>

            <div class="week-label">
                <strong><?= e($weekLabel) ?></strong>
                <span>Tjedni pregled</span>
            </div>

            <a href="?week=<?= e($nextWeek) ?>"
               class="arrow-button"
               aria-label="Sljedeći tjedan">
                &#8250;
            </a>

            <a href="timetable.php" class="today-button">
                Danas
            </a>

        </div>


        <div class="filters">

            <select id="subjectFilter">
                <option value="">Svi predmeti</option>

                <?php foreach ($subjects as $subject): ?>
                    <option value="<?= e($subject['name']) ?>">
                        <?= e($subject['name']) ?>
                    </option>
                <?php endforeach; ?>

            </select>


            <select id="professorFilter">
                <option value="">Svi profesori</option>

                <?php foreach ($users as $professor): ?>
                    <option value="<?= e($professor['username']) ?>">
                        <?= e($professor['username']) ?>
                    </option>
                <?php endforeach; ?>

            </select>

        </div>

    </section>


    <!-- WEEKLY TIMETABLE -->
    <section class="schedule-card">

        <div class="schedule-scroll">

            <div class="timetable">

                <div class="corner-cell">
                    VRIJEME
                </div>


                <!-- DAYS -->
                <?php foreach ($days as $day): ?>

                    <div class="day-heading
                        <?= $day['date'] == date('Y-m-d') ? 'is-today' : '' ?>">

                        <span><?= strtoupper(e($day['name'])) ?></span>

                        <strong><?= e($day['number']) ?></strong>

                    </div>

                <?php endforeach; ?>


                <!-- TIME SLOTS -->
                <?php foreach ($hours as $hour): ?>

                    <div class="time-cell">
                        <?= sprintf('%02d:00', $hour) ?>
                    </div>

                    <?php foreach ($days as $day): ?>
                        <div class="slot-cell"></div>
                    <?php endforeach; ?>

                <?php endforeach; ?>


                <!-- LECTURES -->
                <?php foreach ($reservations as $reservation):

                    $start = new DateTime($reservation['starts_at']);
                    $end = new DateTime($reservation['ends_at']);

                    $dayIndex = (int)$start->format('N') - 1;

                    if ($dayIndex < 0 || $dayIndex > 4) {
                        continue;
                    }

                    $startMinutes =
                        ((int)$start->format('H') - 8) * 60
                        + (int)$start->format('i');

                    $duration =
                        ($end->getTimestamp() - $start->getTimestamp()) / 60;

                    if ($startMinutes < 0 || $startMinutes >= 600) {
                        continue;
                    }

                    $top = ($startMinutes / 60) * 76;

                    $height = max(38, ($duration / 60) * 76 - 5);

                    $colors = ['blue', 'green', 'purple', 'orange', 'pink'];

                    $color = $colors[$reservation['id'] % count($colors)];

                ?>

                    <button
                        type="button"
                        class="lecture lecture-<?= e($color) ?>"

                        style="
                            --day-index: <?= $dayIndex ?>;
                            --event-top: <?= $top ?>px;
                            --event-height: <?= $height ?>px;
                        "

                        data-subject="<?= e($reservation['subject_name']) ?>"
                        data-professor="<?= e($reservation['professor_name']) ?>"

                        data-title="<?= e($reservation['subject_name']) ?>"
                        data-room="<?= e($reservation['room_code']) ?>"
                        data-professor-name="<?= e($reservation['professor_name']) ?>"

                        data-start="<?= e($start->format('H:i')) ?>"
                        data-end="<?= e($end->format('H:i')) ?>"

                        data-date="<?= e($start->format('l, d F Y')) ?>"

                        data-capacity="<?= (int)$reservation['capacity'] ?>"

                        data-notes="<?= e($reservation['notes']) ?>"
                    >

                        <strong>
                            <?= e($reservation['subject_name']) ?>
                        </strong>

                        <span>
                            <?= e($start->format('H:i')) ?>
                            –
                            <?= e($end->format('H:i')) ?>
                        </span>

                        <span>
                            <?= e($reservation['room_code']) ?>
                        </span>

                    </button>

                <?php endforeach; ?>

            </div>

        </div>


        <div class="schedule-footer">

            <span>
                <i class="legend-dot blue"></i>
                Predavanje
            </span>

            <span class="muted">
                <?= count($reservations) ?> zakazanih predavanja
            </span>

        </div>

    </section>


    <!-- MOBILE LECTURE LIST -->
    <section class="mobile-list">

        <h2>Predavanja ovog tjedna</h2>

        <?php if (empty($reservations)): ?>

            <p>Nema zakazanih predavanja ovog tjedna.</p>

        <?php else: ?>

            <?php foreach ($reservations as $reservation): ?>

                <article class="mobile-lecture">

                    <div class="mobile-lecture-date">

                        <?= e(date('D, d M', strtotime($reservation['starts_at']))) ?>

                        <strong>
                            <?= e(date('H:i', strtotime($reservation['starts_at']))) ?>
                        </strong>

                    </div>

                    <div>

                        <strong>
                            <?= e($reservation['subject_name']) ?>
                        </strong>

                        <span>
                            <?= e($reservation['room_code']) ?>
                            ·
                            <?= e($reservation['professor_name']) ?>
                        </span>

                    </div>

                </article>

            <?php endforeach; ?>

        <?php endif; ?>

    </section>


    <!-- INFORMATION SECTION -->
    <section class="info-strip">

        <div class="info-icon">i</div>

        <div>

            <strong>Potrebna je promjena rezervacije?</strong>

            <p>
                Profesori mogu upravljati vlastitim rezervacijama nakon prijave.
                Za ispravke rasporeda obratite se administratoru.
            </p>

        </div>

    </section>

</section>


<!-- LECTURE DETAILS POPUP -->
<dialog id="lectureDialog" class="lecture-dialog">

    <form method="dialog">
        <button class="dialog-close" aria-label="Zatvori">&times;</button>
    </form>

    <p class="eyebrow">DETALJI PREDAVANJA</p>

    <h2 id="dialogTitle"></h2>

    <div class="detail-list">

        <div>
            <span>Datum</span>
            <strong id="dialogDate"></strong>
        </div>

        <div>
            <span>Vrijeme</span>
            <strong id="dialogTime"></strong>
        </div>

        <div>
            <span>Učionica</span>
            <strong id="dialogRoom"></strong>
        </div>

        <div>
            <span>Profesor</span>
            <strong id="dialogProfessor"></strong>
        </div>

        <div>
            <span>Napomene</span>
            <strong id="dialogNotes"></strong>
        </div>

    </div>

    <form method="dialog">
        <button class="primary-button">Zatvori</button>
    </form>

</dialog>


<script src="assets/script.js"></script>

<?php require __DIR__ . '/footer.php'; ?>
