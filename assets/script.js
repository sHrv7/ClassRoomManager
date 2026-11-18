// FILTERS
const subjectFilter = document.getElementById("subjectFilter");
const professorFilter = document.getElementById("professorFilter");

const lectures = document.querySelectorAll(".timetable .lecture");
const mobileLectures = document.querySelectorAll(".mobile-list .mobile-lecture");

const countElement = document.querySelector(".schedule-footer .muted");
const noLecturesMessage = document.getElementById("noLecturesMessage");

function applyFilters() {
    const selectedSubject = subjectFilter.value;
    const selectedProfessor = professorFilter.value;

    let visibleCount = 0;

    lectures.forEach(lecture => {
        const matchesSubject =
            !selectedSubject || lecture.dataset.subject === selectedSubject;

        const matchesProfessor =
            !selectedProfessor || lecture.dataset.professor === selectedProfessor;

        const visible = matchesSubject && matchesProfessor;

        lecture.style.display = "none";

        if (visible) {
            lecture.style.display = "flex";
            visibleCount++;
        }
    });

    mobileLectures.forEach(lecture => {
        const matchesSubject =
            !selectedSubject || lecture.dataset.subject === selectedSubject;

        const matchesProfessor =
            !selectedProfessor || lecture.dataset.professor === selectedProfessor;

        lecture.toggleAttribute(
            "hidden",
            !(matchesSubject && matchesProfessor)
        );
    });

    if (noLecturesMessage) {
        noLecturesMessage.style.display =
            visibleCount === 0 ? "block" : "none";
    }

    if (countElement) {
        countElement.textContent =
            visibleCount + " prikazanih predavanja";
    }
}

if (subjectFilter && professorFilter) {
    subjectFilter.addEventListener("change", applyFilters);
    professorFilter.addEventListener("change", applyFilters);

    applyFilters();
}


// LECTURE DETAILS POPUP
const dialog = document.getElementById("lectureDialog");

if (dialog) {
    lectures.forEach(lecture => {
        lecture.addEventListener("click", () => {
            const fields = {
                dialogTitle: lecture.dataset.title,
                dialogDate: lecture.dataset.date,
                dialogTime: lecture.dataset.start + " – " + lecture.dataset.end,
                dialogRoom: lecture.dataset.room,
                dialogProfessor: lecture.dataset.professorName,
                dialogNotes: lecture.dataset.notes || "Nema dodatnih napomena"
            };

            for (const [id, value] of Object.entries(fields)) {
                const element = document.getElementById(id);

                if (element) {
                    element.textContent = value || "";
                }
            }

            dialog.showModal();
        });
    });
}