<?php
/** Zajednicki partial za izradu i uredivanje obavijesti s Quill editorom. */
?>
<main class="page-shell create-post-page">
    <div class="notifications-heading">
        <div>
            <p class="eyebrow">CLASSROOM MANAGER</p>
            <h1><?= e($formHeading) ?></h1>
            <?php if ($formHeading === 'Nova objava'): ?>
                <p class="muted">Objavite obavijest studentima.</p>
            <?php endif; ?>
        </div>
        <a href="notifications.php" class="button button-secondary">Natrag na obavijesti</a>
    </div>

    <?php if ($showCreated ?? false): ?>
        <p class="success-message" role="status">Objava je uspješno objavljena.</p>
    <?php endif; ?>
    <?php if ($formError !== ''): ?>
        <p class="error-message" role="alert"><?= e($formError) ?></p>
    <?php endif; ?>

    <section class="notification-card">
        <form method="post" action="<?= e($formAction) ?>" enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <?php if ($formNotificationId !== null): ?>
                <input type="hidden" name="notification_id" value="<?= (int) $formNotificationId ?>">
            <?php endif; ?>

            <div class="form-group">
                <label for="category_id">Kategorija</label>
                <select name="category_id" id="category_id" required>
                    <option value="">Odaberite kategoriju...</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?= (int) $category['id'] ?>" <?= (int) $formCategoryId === (int) $category['id'] ? 'selected' : '' ?>>
                            <?= e($category['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group" id="subjectGroup">
                <label for="subject_id">Predmet</label>
                <select name="subject_id" id="subject_id">
                    <option value="">Odaberite predmet...</option>
                    <?php foreach ($subjects as $subject): ?>
                        <option value="<?= (int) $subject['id'] ?>" <?= (int) $formSubjectId === (int) $subject['id'] ? 'selected' : '' ?>>
                            <?= e($subject['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label for="title">Naslov obavijesti</label>
                <input type="text" name="title" id="title" maxlength="255" value="<?= e($formTitle) ?>" required>
            </div>

            <div class="form-group">
                <label id="message-label" for="message-editor">Tekst obavijesti</label>
                <textarea name="message" id="message" rows="7" required><?= e($formMessage) ?></textarea>
                <div id="message-editor" class="rich-text-editor" aria-labelledby="message-label"></div>
                <small id="message-error" class="muted" aria-live="polite"></small>
            </div>

            <?php if (!empty($formImage)): ?>
                <div class="form-group">
                    <p>Trenutna slika</p>
                    <img src="<?= e($formImage) ?>" alt="Trenutna slika obavijesti" class="notification-image" width="200">
                    <label><input type="checkbox" name="remove_image" value="1"> Ukloni sliku</label>
                </div>
            <?php endif; ?>

            <div class="form-group">
                <label for="image"><?= $formImage ? 'Zamijeni sliku' : 'Slika' ?> (nije obavezna)</label>
                <input type="file" name="image" id="image" accept="image/jpeg,image/png,image/webp,image/gif">
                <small class="muted">JPG, PNG, WEBP ili GIF. Maksimalna veličina: 5 MB.</small>
            </div>

            <button type="submit" class="button button-primary"><?= e($formSubmitLabel) ?></button>
        </form>

        <?php if ($formNotificationId !== null): ?>
            <form
                class="post-delete-form"
                method="post"
                action="<?= e($formAction) ?>"
                onsubmit="return confirm('Jeste li sigurni da želite obrisati ovu obavijest?');"
            >
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="notification_id" value="<?= (int) $formNotificationId ?>">
                <button class="button button-danger" type="submit" name="action" value="delete">
                    Obriši obavijest
                </button>
            </form>
        <?php endif; ?>
    </section>
</main>

<script src="https://cdn.jsdelivr.net/npm/quill@1.3.7/dist/quill.min.js"></script>
<script>
const categorySelect = document.getElementById('category_id');
const subjectGroup = document.getElementById('subjectGroup');
const subjectSelect = document.getElementById('subject_id');
const messageInput = document.getElementById('message');
const messageError = document.getElementById('message-error');

function updateSubjectField() {
    const selectedCategory = categorySelect.options[categorySelect.selectedIndex];
    const isGeneralNews = selectedCategory && selectedCategory.text.trim() === 'Opće vijesti';
    subjectGroup.style.display = isGeneralNews ? 'none' : '';
    subjectSelect.required = !isGeneralNews;
    if (isGeneralNews) subjectSelect.value = '';
}

const quill = new Quill('#message-editor', {
    theme: 'snow',
    modules: {
        toolbar: [
            [{ header: [1, 2, false] }],
            ['bold', 'italic', 'underline', 'strike'],
            [{ list: 'ordered' }, { list: 'bullet' }],
            ['blockquote', 'link'],
            ['clean']
        ]
    }
});

if (messageInput.value.trim() !== '') {
    quill.clipboard.dangerouslyPasteHTML(messageInput.value, 'silent');
}

messageInput.hidden = true;
messageInput.required = false;
messageInput.form.addEventListener('submit', (event) => {
    if (quill.getText().trim() === '') {
        event.preventDefault();
        messageError.textContent = 'Tekst obavijesti je obavezan.';
        quill.focus();
        return;
    }
    messageError.textContent = '';
    messageInput.value = quill.root.innerHTML;
});

categorySelect.addEventListener('change', updateSubjectField);
updateSubjectField();
</script>