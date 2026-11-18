
const subjectFilter = document.getElementById("subjectFilter");
const professorFilter = document.getElementById("professorFilter");

const lectures = document.querySelectorAll(".lecture");

// FILTER LECTURES

function applyFilters() {

    const subject = subjectFilter.value.toLowerCase();
    const professor = professorFilter.value.toLowerCase();

    lectures.forEach(lecture => {

        const matchesSubject =
            !subject ||
            lecture.dataset.subject.toLowerCase() === subject;

        const matchesProfessor =
            !professor ||
            lecture.dataset.professor.toLowerCase() === professor;

        lecture.hidden = !(matchesSubject && matchesProfessor);

    });

}

subjectFilter.addEventListener("change", applyFilters);
professorFilter.addEventListener("change", applyFilters);


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
                dialogNotes: lecture.dataset.notes || "No additional notes"
            };

            for (const [id, value] of Object.entries(fields)) {
                const element = document.getElementById(id);

                if (element) {
                    element.textContent = value || "";
                } else {
                    console.error(`Missing HTML element: #${id}`);
                }
            }

            dialog.showModal();
        });
    });
}
