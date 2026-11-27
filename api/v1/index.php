
<?php

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/schema_validator.php';

header('Content-Type: application/json; charset=utf-8');

// Allow the HTTP methods used by our API.
header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests.
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

function api_response(int $status, array $data = []): void
{
    http_response_code($status);

    if ($status !== 204) {
        echo json_encode(
            $data,
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
    }

    exit;
}

function api_error(int $status, string $code, string $message): void
{
    api_response($status, [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message
        ]
    ]);
}

// Only allow the HTTP methods supported by the API.
$method = $_SERVER['REQUEST_METHOD'];

$allowedMethods = ['GET', 'POST', 'PATCH', 'DELETE'];

if (!in_array($method, $allowedMethods, true)) {
    header('Allow: GET, POST, PATCH, DELETE, OPTIONS');
    api_error(405, 'METHOD_NOT_ALLOWED', 'HTTP method not allowed.');
}

// Require authentication.
$user = current_user();

if (!$user) {
    api_error(401, 'UNAUTHORIZED', 'Authentication is required.');
}

$userId = (int) ($user['id'] ?? $user['ID'] ?? 0);
$role = $user['Role'] ?? '';

if ($userId <= 0) {
    api_error(401, 'UNAUTHORIZED', 'Invalid user session.');
}

if (!in_array($role, ['Admin', 'Professor', 'Student'], true)) {
    api_error(403, 'FORBIDDEN', 'Your role cannot access this API.');
}

$pdo = db();

// Get the requested API path.
$basePath = '/ClassRoomManager/api/v1';
$requestPath = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

$path = substr($requestPath, strlen($basePath));
$path = trim($path, '/');

$segments = $path === '' ? [] : explode('/', $path);

$resource = $segments[0] ?? '';
$id = $segments[1] ?? null;

// Only support these resources.
if (!in_array($resource, ['notifications', 'categories'], true)) {
    api_error(404, 'NOT_FOUND', 'API endpoint not found.');
}

if (count($segments) > 2) {
    api_error(404, 'NOT_FOUND', 'API endpoint not found.');
}

// Categories: GET only.
if ($resource === 'categories') {

    if ($method !== 'GET' || $id !== null) {
        api_error(405, 'METHOD_NOT_ALLOWED', 'This endpoint only supports GET.');
    }

    $stmt = $pdo->query(
        'SELECT id, name FROM categories ORDER BY name ASC'
    );

    api_response(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}


// Notifications.

if ($resource === 'notifications') {

    // PATCH: Update an existing notification.
    if ($method === 'PATCH') {

        if (!in_array($role, ['Admin', 'Professor'], true)) {
            api_error(403, 'FORBIDDEN',
                'You do not have permission to update notifications.');
        }

        // Validate notification ID.
        if ($id === null || !ctype_digit($id) || (int) $id <= 0) {
            api_error(400, 'INVALID_ID',
                'A valid notification ID is required.');
        }

        $notificationId = (int) $id;

        // Find the notification.
        $stmt = $pdo->prepare(
            'SELECT * FROM notifications WHERE id = :id'
        );
        $stmt->execute(['id' => $notificationId]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            api_error(404, 'NOT_FOUND', 'Notification not found.');
        }

        // Professors can only edit their own notifications.
        if ($role === 'Professor' &&
            (int) $notification['professor_id'] !== $userId) {
            api_error(403, 'FORBIDDEN',
                'You can only edit your own notifications.');
        }

        // Read JSON body.
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input) || empty($input)) {
            api_error(400, 'INVALID_JSON',
                'A non-empty JSON object is required.');
        }

        // Only these fields can be updated.
        $allowedFields = [
            'title',
            'message',
            'subject_id',
            'category_id',
            'image',
            'is_draft'
        ];

        foreach ($input as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                api_error(400, 'INVALID_FIELD',
                    "The field '$field' cannot be updated.");
            }
        }

        // Start with existing values.
        $updated = [
            'title' => $notification['title'],
            'message' => $notification['message'],
            'subject_id' => $notification['subject_id'] === null
                ? null : (int) $notification['subject_id'],
            'category_id' => (int) $notification['category_id'],
            'image' => $notification['image'],
            'is_draft' => (int) $notification['is_draft']
        ];

        // Validate title. Empty titles are allowed only while the result is a draft.
        if (array_key_exists('title', $input)) {
            if (!is_string($input['title'])) {
                api_error(400, 'INVALID_TITLE', 'Title must be a string.');
            }

            $title = trim($input['title']);

            if (mb_strlen($title) > 255) {
                api_error(400, 'INVALID_TITLE',
                    'Title must not exceed 255 characters.');
            }

            $updated['title'] = $title;
        }

        // Validate message. Empty messages are allowed only while the result is a draft.
        if (array_key_exists('message', $input)) {
            if (!is_string($input['message'])) {
                api_error(400, 'INVALID_MESSAGE', 'Message must be a string.');
            }

            $updated['message'] = trim($input['message']);
        }

        // Validate category.
        if (array_key_exists('category_id', $input)) {
            $categoryId = filter_var(
                $input['category_id'],
                FILTER_VALIDATE_INT
            );

            if ($categoryId === false || $categoryId <= 0) {
                api_error(400, 'INVALID_CATEGORY',
                    'Category ID must be a positive integer.');
            }

            $stmt = $pdo->prepare(
                'SELECT id FROM categories WHERE id = :id'
            );
            $stmt->execute(['id' => $categoryId]);

            if (!$stmt->fetch()) {
                api_error(400, 'CATEGORY_NOT_FOUND',
                    'The selected category does not exist.');
            }

            $updated['category_id'] = $categoryId;
        }

        // Validate subject.
        if (array_key_exists('subject_id', $input)) {
            $subjectId = null;

            if ($input['subject_id'] !== null) {
                $subjectId = filter_var(
                    $input['subject_id'],
                    FILTER_VALIDATE_INT
                );

                if ($subjectId === false || $subjectId <= 0) {
                    api_error(400, 'INVALID_SUBJECT',
                        'Subject ID must be a positive integer or null.');
                }

                $stmt = $pdo->prepare(
                    'SELECT id FROM subjects WHERE id = :id'
                );
                $stmt->execute(['id' => $subjectId]);

                if (!$stmt->fetch()) {
                    api_error(400, 'SUBJECT_NOT_FOUND',
                        'The selected subject does not exist.');
                }

                // Professors can only use their assigned subjects.
                if ($role === 'Professor') {
                    $stmt = $pdo->prepare(
                        'SELECT subject_id
                         FROM user_subjects
                         WHERE user_id = :user_id
                         AND subject_id = :subject_id'
                    );

                    $stmt->execute([
                        'user_id' => $userId,
                        'subject_id' => $subjectId
                    ]);

                    if (!$stmt->fetch()) {
                        api_error(403, 'FORBIDDEN',
                            'You are not assigned to this subject.');
                    }
                }
            }

            $updated['subject_id'] = $subjectId;
        }

        // Validate image.
        if (array_key_exists('image', $input)) {
            $image = $input['image'];

            if ($image !== null &&
                (!is_string($image) || trim($image) === '' || mb_strlen($image) > 255)) {
                api_error(400, 'INVALID_IMAGE',
                    'Image must be a non-empty string of maximum 255 characters or null.');
            }

            $updated['image'] = $image === null ? null : trim($image);
        }

        // Validate draft status.
        if (array_key_exists('is_draft', $input)) {
            if (!is_bool($input['is_draft'])) {
                api_error(400, 'INVALID_DRAFT',
                    'is_draft must be true or false.');
            }

            $updated['is_draft'] = $input['is_draft'] ? 1 : 0;
        }

        // The final notification must have a title and message if it is published.
        if ((int) $updated['is_draft'] === 0 &&
            (trim((string) $updated['title']) === '' ||
             trim((string) $updated['message']) === '')) {
            api_error(400, 'VALIDATION_ERROR',
                'Title and message are required when publishing.');
        }

        // Update notification.
        $stmt = $pdo->prepare(
            'UPDATE notifications
             SET title = :title,
                 message = :message,
                 subject_id = :subject_id,
                 category_id = :category_id,
                 image = :image,
                 is_draft = :is_draft
             WHERE id = :id'
        );

        $stmt->execute([
            'title' => $updated['title'],
            'message' => $updated['message'],
            'subject_id' => $updated['subject_id'],
            'category_id' => $updated['category_id'],
            'image' => $updated['image'],
            'is_draft' => $updated['is_draft'],
            'id' => $notificationId
        ]);

        api_response(200, [
            'success' => true,
            'message' => 'Notification updated successfully.',
            'data' => [
                'id' => $notificationId
            ]
        ]);
    }


    // POST: Create a notification.
    if ($method === 'POST') {

        if (!in_array($role, ['Admin', 'Professor'], true)) {
            api_error(403, 'FORBIDDEN',
                'You do not have permission to create notifications.');
        }

        // Read JSON request body.
        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            api_error(400, 'INVALID_JSON',
                'Request body must contain valid JSON.');
        }

        $isDraft = $input['is_draft'] ?? false;

        if (!is_bool($isDraft)) {
            api_error(400, 'INVALID_DRAFT', 'is_draft must be true or false.');
        }

        $allowedFields = [
            'title', 'message', 'category_id', 'subject_id', 'image', 'is_draft'
        ];
        foreach ($input as $field => $value) {
            if (!in_array($field, $allowedFields, true)) {
                api_error(400, 'INVALID_FIELD', "Unknown property: $field");
            }
        }

        // Validate the full schema for published notifications. Drafts are
        // checked field-by-field below so their title/message may be empty.
        if (!$isDraft) {
            $schemaErrors = validate_notification_schema($input);

            if (!empty($schemaErrors)) {
                api_response(400, [
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'Request data does not match the notification schema.',
                        'details' => $schemaErrors
                    ]
                ]);
            }
        }

        // Validate title.
        if (!is_string($input['title'] ?? null)) {
            api_error(400, 'INVALID_TITLE', 'Title must be a string.');
        }
        $title = trim($input['title']);

        if ((!$isDraft && $title === '') || mb_strlen($title) > 255) {
            api_error(400, 'INVALID_TITLE',
                'Title is required when publishing and must not exceed 255 characters.');
        }

        // Validate message.
        if (!is_string($input['message'] ?? null)) {
            api_error(400, 'INVALID_MESSAGE', 'Message must be a string.');
        }
        $message = trim($input['message']);

        if (!$isDraft && $message === '') {
            api_error(400, 'INVALID_MESSAGE',
                'Message is required when publishing.');
        }

        // Validate category ID.
        $categoryId = filter_var(
            $input['category_id'] ?? null,
            FILTER_VALIDATE_INT
        );

        if ($categoryId === false || $categoryId === null || $categoryId <= 0) {
            api_error(400, 'INVALID_CATEGORY',
                'A valid category ID is required.');
        }

        // Validate optional subject ID.
        $subjectId = null;

        if (array_key_exists('subject_id', $input) && $input['subject_id'] !== null) {

            $subjectId = filter_var(
                $input['subject_id'],
                FILTER_VALIDATE_INT
            );

            if ($subjectId === false || $subjectId <= 0) {
                api_error(400, 'INVALID_SUBJECT',
                    'Subject ID must be a positive integer or null.');
            }
        }

        // Validate optional image.
        $image = $input['image'] ?? null;

        if ($image !== null && (!is_string($image) || trim($image) === '' || mb_strlen($image) > 255)) {
            api_error(400, 'INVALID_IMAGE',
                'Image must be a non-empty string of maximum 255 characters or null.');
        }

        // Check whether category exists.
        $stmt = $pdo->prepare(
            'SELECT id FROM categories WHERE id = :id'
        );
        $stmt->execute(['id' => $categoryId]);

        if (!$stmt->fetch()) {
            api_error(400, 'CATEGORY_NOT_FOUND',
                'The selected category does not exist.');
        }

        // Check whether subject exists.
        if ($subjectId !== null) {

            $stmt = $pdo->prepare(
                'SELECT id FROM subjects WHERE id = :id'
            );
            $stmt->execute(['id' => $subjectId]);

            if (!$stmt->fetch()) {
                api_error(400, 'SUBJECT_NOT_FOUND',
                    'The selected subject does not exist.');
            }

            // Professors can only post to their assigned subjects.
            if ($role === 'Professor') {

                $stmt = $pdo->prepare(
                    'SELECT subject_id
                     FROM user_subjects
                     WHERE user_id = :user_id
                     AND subject_id = :subject_id'
                );

                $stmt->execute([
                    'user_id' => $userId,
                    'subject_id' => $subjectId
                ]);

                if (!$stmt->fetch()) {
                    api_error(403, 'FORBIDDEN',
                        'You are not assigned to this subject.');
                }
            }
        }

        // Insert notification.
        $stmt = $pdo->prepare(
            'INSERT INTO notifications
                (subject_id, professor_id, title, message,
                 image, category_id, is_draft)
             VALUES
                (:subject_id, :professor_id, :title, :message,
                 :image, :category_id, :is_draft)'
        );

        $stmt->execute([
            'subject_id' => $subjectId,
            'professor_id' => $userId,
            'title' => $title,
            'message' => $message,
            'image' => $image,
            'category_id' => $categoryId,
            'is_draft' => $isDraft ? 1 : 0
        ]);

        $newId = (int) $pdo->lastInsertId();

        api_response(201, [
            'success' => true,
            'message' => 'Notification created successfully.',
            'data' => [
                'id' => $newId
            ]
        ]);
    }

    // DELETE: Delete a notification.
    if ($method === 'DELETE') {

        if (!in_array($role, ['Admin', 'Professor'], true)) {
            api_error(403, 'FORBIDDEN',
                'You do not have permission to delete notifications.');
        }

        // Validate notification ID.
        if ($id === null || !ctype_digit($id) || (int) $id <= 0) {
            api_error(400, 'INVALID_ID',
                'A valid notification ID is required.');
        }

        $notificationId = (int) $id;

        // Find the notification.
        $stmt = $pdo->prepare(
            'SELECT id, professor_id
             FROM notifications
             WHERE id = :id'
        );

        $stmt->execute(['id' => $notificationId]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            api_error(404, 'NOT_FOUND',
                'Notification not found.');
        }

        // Professors can only delete their own notifications.
        if ($role === 'Professor' &&
            (int) $notification['professor_id'] !== $userId) {
            api_error(403, 'FORBIDDEN',
                'You can only delete your own notifications.');
        }

        // Delete notification.
        $stmt = $pdo->prepare(
            'DELETE FROM notifications WHERE id = :id'
        );

        $stmt->execute(['id' => $notificationId]);

        // HTTP 204: successful deletion, no response body.
        api_response(204);
    }


    // GET requests continue below.
    if ($method !== 'GET') {
        api_error(405, 'METHOD_NOT_ALLOWED',
            'This notification endpoint does not support this method yet.');
    }

    // GET one notification.
    if ($id !== null) {

        if (!ctype_digit($id) || (int) $id <= 0) {
            api_error(400, 'INVALID_ID', 'Notification ID must be a positive integer.');
        }

        $sql = '
            SELECT DISTINCT
                n.id,
                n.subject_id,
                n.professor_id,
                n.title,
                n.message,
                n.created_at,
                n.image,
                n.category_id,
                n.is_draft,
                c.name AS category_name,
                s.name AS subject_name,
                u.Username AS professor_name
            FROM notifications n
            JOIN users u ON u.id = n.professor_id
            LEFT JOIN categories c ON c.id = n.category_id
            LEFT JOIN subjects s ON s.id = n.subject_id
            LEFT JOIN user_subjects us
                ON us.subject_id = n.subject_id
                AND us.user_id = :user_id
            WHERE n.id = :notification_id
            AND (
                n.is_draft = 0
                OR n.professor_id = :author_id
                OR :is_admin = 1
            )
            AND (
                n.subject_id IS NULL
                OR us.user_id IS NOT NULL
                OR :is_admin_access = 1
                OR n.professor_id = :author_id_access
            )
            LIMIT 1
        ';

        $stmt = $pdo->prepare($sql);

        $stmt->execute([
            'user_id' => $userId,
            'notification_id' => (int) $id,
            'author_id' => $userId,
            'is_admin' => $role === 'Admin' ? 1 : 0,
            'is_admin_access' => $role === 'Admin' ? 1 : 0,
            'author_id_access' => $userId
        ]);

        $notification = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$notification) {
            api_error(404, 'NOT_FOUND', 'Notification not found.');
        }

        api_response(200, [
            'success' => true,
            'data' => $notification
        ]);
    }

    // GET notification list.
    
    // GET notification list with pagination and filters.

    $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT);
    $limit = filter_input(INPUT_GET, 'limit', FILTER_VALIDATE_INT);

    $page = $page === false || $page === null ? 1 : $page;
    $limit = $limit === false || $limit === null ? 10 : $limit;

    if ($page < 1 || $limit < 1 || $limit > 100) {
        api_error(400, 'INVALID_PAGINATION',
            'Page must be positive and limit must be between 1 and 100.');
    }

    $categoryId = null;
    $subjectId = null;

    if (isset($_GET['category_id'])) {
        $categoryId = filter_var(
            $_GET['category_id'],
            FILTER_VALIDATE_INT
        );

        if ($categoryId === false || $categoryId <= 0) {
            api_error(400, 'INVALID_CATEGORY',
                'Category ID must be a positive integer.');
        }
    }

    if (isset($_GET['subject_id'])) {
        $subjectId = filter_var(
            $_GET['subject_id'],
            FILTER_VALIDATE_INT
        );

        if ($subjectId === false || $subjectId <= 0) {
            api_error(400, 'INVALID_SUBJECT',
                'Subject ID must be a positive integer.');
        }
    }

    $search = trim($_GET['search'] ?? '');

    if (strlen($search) > 100) {
        api_error(400, 'INVALID_SEARCH',
            'Search must not exceed 100 characters.');
    }

    $where = [
        '(
            n.is_draft = 0
            OR n.professor_id = :author_id
            OR :is_admin = 1
        )',
        '(
            n.subject_id IS NULL
            OR us.user_id IS NOT NULL
            OR :is_admin_access = 1
            OR n.professor_id = :author_id_access
        )'
    ];

    $params = [
        'user_id' => $userId,
        'author_id' => $userId,
        'is_admin' => $role === 'Admin' ? 1 : 0,
        'is_admin_access' => $role === 'Admin' ? 1 : 0,
        'author_id_access' => $userId
    ];

    // Filter by category.
    if ($categoryId !== null) {
        $where[] = 'n.category_id = :category_id';
        $params['category_id'] = $categoryId;
    }

    // Filter by subject.
    if ($subjectId !== null) {
        $where[] = 'n.subject_id = :subject_id';
        $params['subject_id'] = $subjectId;
    }

    // Search notification titles and messages.
    if ($search !== '') {
        $where[] = '(n.title LIKE :search_title OR n.message LIKE :search_message)';
        $params['search_title'] = '%' . $search . '%';
        $params['search_message'] = '%' . $search . '%';
    }

    $whereSql = implode(' AND ', $where);

    // Count matching notifications for pagination.
    $countSql = "
        SELECT COUNT(DISTINCT n.id)
        FROM notifications n
        LEFT JOIN user_subjects us
            ON us.subject_id = n.subject_id
            AND us.user_id = :user_id
        WHERE $whereSql
    ";

    $countStmt = $pdo->prepare($countSql);
    $countStmt->execute($params);

    $total = (int) $countStmt->fetchColumn();

    $totalPages = (int) ceil($total / $limit);
    $offset = ($page - 1) * $limit;

    // Retrieve notifications.
    $sql = "
        SELECT DISTINCT
            n.id,
            n.subject_id,
            n.professor_id,
            n.title,
            n.message,
            n.created_at,
            n.image,
            n.category_id,
            n.is_draft,
            c.name AS category_name,
            s.name AS subject_name,
            u.Username AS professor_name
        FROM notifications n
        JOIN users u ON u.id = n.professor_id
        LEFT JOIN categories c ON c.id = n.category_id
        LEFT JOIN subjects s ON s.id = n.subject_id
        LEFT JOIN user_subjects us
            ON us.subject_id = n.subject_id
            AND us.user_id = :user_id
        WHERE $whereSql
        ORDER BY n.created_at DESC
        LIMIT $limit OFFSET $offset
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    api_response(200, [
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        'pagination' => [
            'page' => $page,
            'limit' => $limit,
            'total' => $total,
            'total_pages' => $totalPages
        ],
        'filters' => [
            'category_id' => $categoryId,
            'subject_id' => $subjectId,
            'search' => $search
        ]
    ]);
}
