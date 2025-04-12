document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 1;
    const steps = document.querySelectorAll(".step");
    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const circleSteps = document.querySelectorAll('.circle');
    const progressFill = document.getElementById('progressFill');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    const stepLabels = document.querySelectorAll('.step-label');
    const totalSteps = circleSteps.length;

    // Initial state: step 1 is active. (HTML already preset first circle and label with "active")
    updateNavigation();

    // Next Button Event Listener
    nextBtn.addEventListener('click', () => {
        let isValid = true;

        // First, check that all required fields in the active step are filled.
        document.querySelectorAll(".step.active-step input[required], .step.active-step select[required]").forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add("is-invalid");
            } else {
                field.classList.remove("is-invalid");
            }
        });

        // If required fields are missing, show the modal and stop.
        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        if (currentStep === 1) {
            // Switch from Step 1 (data entry) to Step 2 (summary)
            steps[currentStep - 1].classList.remove("active-step");
            currentStep++;
            steps[currentStep - 1].classList.add("active-step");

            // Update progress circles and labels.
            circleSteps[currentStep - 2].classList.add('completed');
            stepLabels[currentStep - 2].classList.add('completed');
            circleSteps[currentStep - 1].classList.add('active');
            stepLabels[currentStep - 1].classList.add('active');

            // Update progress bar (fills from 0% to progress for step 2)
            const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = `${progressPercent}%`;

            // Populate summary fields using values from Step 1.
            populateSummary();
            updateNavigation();

        } else if (currentStep === 2) {
            // When on the summary screen, open the confirmation modal.
            let confirmModal = new bootstrap.Modal(document.getElementById("confirmationModal"));
            confirmModal.show();
        }
    });

    // Back Button Event Listener
    backBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            // Hide current active step and update progress circles/labels.
            steps[currentStep - 1].classList.remove("active-step");
            circleSteps[currentStep - 1].classList.remove('active');
            stepLabels[currentStep - 1].classList.remove('active');

            if (currentStep === 2) {
                // Removing completed state on returning from step 2.
                circleSteps[currentStep - 2].classList.remove('completed');
                stepLabels[currentStep - 2].classList.remove('completed');
                progressFill.style.width = `0%`;
            }

            currentStep--;
            steps[currentStep - 1].classList.add("active-step");
            circleSteps[currentStep - 1].classList.add('active');
            stepLabels[currentStep - 1].classList.add('active');

            updateNavigation();
        }
    });

    // Update navigation texts and button visibility.
    function updateNavigation() {
        if (currentStep === 1) {
            mainHeader.textContent = "APPLICATION FORM";
            subHeader.textContent = "Provide the necessary details to apply for your Barangay ID.";
            nextBtn.textContent = "NEXT >";
            backBtn.style.visibility = "hidden";
        } else if (currentStep === 2) {
            mainHeader.textContent = "REVIEW and CONFIRMATION";
            subHeader.textContent = "Please review the information you have provided to ensure all details are correct before proceeding.";
            nextBtn.textContent = "SUBMIT";
            backBtn.style.visibility = "visible";
            backBtn.textContent = "< GO BACK";
        }
    };

    // Populate summary fields with values from step 1.
    function populateSummary() {
        const transactionType = document.getElementById("transactiontype").value;
        const fullName = document.getElementById("fullname").value;
        const fullAddress = document.getElementById("address").value;
        const height = document.getElementById("height").value;
        const weight = document.getElementById("weight").value;
        const birthday = document.getElementById("birthday").value;
        const birthplace = document.getElementById("birthplace").value;
        const civilStatus = document.getElementById("civilstatus").value;
        const religion = document.getElementById("religion").value;
        const contactPerson = document.getElementById("contactperson").value;
        const claimDate = document.getElementById("claimdate").value;
        
        // Use the same element IDs as in the HTML summary.
        document.getElementById("summarytransactionType").textContent = transactionType;
        document.getElementById("summaryFullName").textContent = fullName;
        document.getElementById("summaryAddress").textContent = fullAddress;
        document.getElementById("summaryHeight").textContent = height;
        document.getElementById("summaryWeight").textContent = weight;
        document.getElementById("summaryBirthdate").textContent = birthday;
        document.getElementById("summaryBirthplace").textContent = birthplace;
        document.getElementById("summaryCivilStatus").textContent = civilStatus;
        document.getElementById("summaryReligion").textContent = religion;
        document.getElementById("summaryContactPerson").textContent = contactPerson;
        document.getElementById("summaryClaimDate").textContent = claimDate;
    }
});
