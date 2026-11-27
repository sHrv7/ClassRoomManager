
<?php

function validate_notification_schema(array $data): array
{
    $errors = [];

    $allowedFields = [
        'title',
        'message',
        'category_id',
        'subject_id',
        'image',
        'is_draft'
    ];

    $isDraft = ($data['is_draft'] ?? false) === true;

    foreach ($data as $field => $value) {
        if (!in_array($field, $allowedFields, true)) {
            $errors[] = "Unknown property: $field";
        }
    }

    // Required fields
    foreach (['title', 'message', 'category_id'] as $field) {
        if (!array_key_exists($field, $data)) {
            $errors[] = "Missing required property: $field";
        }
    }


    // Title
    if (isset($data['title'])) {
        if (!is_string($data['title'])) {
            $errors[] = 'Title must be a string.';
        } elseif (
            (!$isDraft && trim($data['title']) === '')
            || mb_strlen($data['title']) > 255
        ) {
            $errors[] = 'Title must contain between 1 and 255 characters.';
        }
    }

    // Message
    if (isset($data['message'])) {
        if (!is_string($data['message'])) {
            $errors[] = 'Message must be a string.';
        } elseif (!$isDraft && trim($data['message']) === '') {
            $errors[] = 'Message cannot be empty when publishing.';
        }
    }

    // Category ID
    if (isset($data['category_id'])) {
        if (!is_int($data['category_id']) || $data['category_id'] < 1) {
            $errors[] = 'Category ID must be a positive integer.';
        }
    }

    // Subject ID
    if (array_key_exists('subject_id', $data) && $data['subject_id'] !== null) {
        if (!is_int($data['subject_id']) || $data['subject_id'] < 1) {
            $errors[] = 'Subject ID must be a positive integer or null.';
        }
    }

    // Image
    if (array_key_exists('image', $data) && $data['image'] !== null) {
        if (!is_string($data['image']) || strlen($data['image']) < 1 || strlen($data['image']) > 255) {
            $errors[] = 'Image must be a string between 1 and 255 characters, or null.';
        }
    }

    // Draft status
    if (array_key_exists('is_draft', $data) && !is_bool($data['is_draft'])) {
        $errors[] = 'is_draft must be a boolean.';
    }

    return $errors;
}
