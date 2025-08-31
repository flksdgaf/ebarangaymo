// js/serviceBarangayClearance.js
document.addEventListener("DOMContentLoaded", function () {
    let currentStep = window.initialStep || 1;
    const steps = document.querySelectorAll(".step");
    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const circleSteps = document.querySelectorAll('.circle');
    const progressFill = document.getElementById('progressFill');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');
    const stepLabels = document.querySelectorAll('.step-label');
    const totalSteps = circleSteps.length;

    // Payment controls
    const paymentButtons    = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    const hiddenPaymentInput = document.getElementById('paymentMethod');

    // Confirmation modal
    const confirmationModalEl = document.getElementById("confirmationModal");
    const confirmationModal = new bootstrap.Modal(confirmationModalEl);
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    // Initial setup
    updateNavigation();
    setupPaymentControls();

    nextBtn.addEventListener('click', () => {
        // If we're on final submission screen, treat as redirect/back-to-home
        if (currentStep === 4) {
            window.location.href = 'userPanel.php?page=userDashboard';
            return;
        }

        let isValid = true;

        // If on payment step, ensure a payment method is chosen
        if (currentStep === 2 && (!hiddenPaymentInput || !hiddenPaymentInput.value)) {
            isValid = false;
        }

        // Validate required inputs on step 1
        if (currentStep === 1) {
            document.querySelectorAll(".step.active-step input[required], .step.active-step select[required], .step.active-step textarea[required]")
              .forEach(field => {
                // For file inputs, check files length
                if (field.type === 'file') {
                    if (!field.files || field.files.length === 0) {
                        // if file is required (none in default clearance form) mark invalid
                        if (field.hasAttribute('required')) {
                            isValid = false;
                            field.classList.add('is-invalid');
                        } else {
                            field.classList.remove('is-invalid');
                        }
                    } else {
                        field.classList.remove('is-invalid');
                    }
                    return;
                }

                // Normal value check (numbers, text, date)
                const val = (field.value || '').toString().trim();
                if (!val) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            });
        }

        if (!isValid) {
            const validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // If stepping into confirmation (summary) step
        if (currentStep === 3) {
            // Show confirmation modal before final submit
            confirmationModal.show();
            return;
        }

        // Move forward one step
        steps[currentStep - 1].classList.remove("active-step");
        circleSteps[currentStep - 1].classList.add('completed');
        stepLabels[currentStep - 1].classList.add('completed');

        currentStep++;
        steps[currentStep - 1].classList.add("active-step");
        circleSteps[currentStep - 1].classList.add('active');
        stepLabels[currentStep - 1].classList.add('active');

        // Update progress bar (percentage across steps)
        const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        progressFill.style.width = `${progressPercent}%`;

        // If stepping into summary, populate fields
        if (currentStep === 3) {
            populateSummary();
        }

        updateNavigation();
    });

    backBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            steps[currentStep - 1].classList.remove("active-step");
            circleSteps[currentStep - 1].classList.remove('active');
            stepLabels[currentStep - 1].classList.remove('active');

            // Un-complete the previous step
            circleSteps[currentStep - 2].classList.remove('completed');
            stepLabels[currentStep - 2].classList.remove('completed');

            const newPercent = ((currentStep - 2) / (totalSteps - 1)) * 100;
            progressFill.style.width = `${newPercent}%`;

            currentStep--;
            steps[currentStep - 1].classList.add("active-step");
            circleSteps[currentStep - 1].classList.add('active');
            stepLabels[currentStep - 1].classList.add('active');

            updateNavigation();
        }
    });

    confirmSubmitBtn.addEventListener('click', () => {
        // Submit the clearance form
        const form = document.getElementById("barangayClearanceForm");
        if (form) form.submit();
    });

    function updateNavigation() {
        backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === 1) {
            mainHeader.textContent = "APPLICATION FORM";
            subHeader.textContent = "Provide the necessary details to request a Barangay Clearance.";
            nextBtn.textContent = "NEXT >";
        } else if (currentStep === 2) {
            mainHeader.textContent = "PAYMENT";
            subHeader.textContent = "Settle your payment for the Barangay Clearance.";
            nextBtn.textContent = "NEXT >";
        } else if (currentStep === 3) {
            mainHeader.textContent = "REVIEW and CONFIRMATION";
            subHeader.textContent = "Please review all your information before submitting.";
            nextBtn.textContent = "SUBMIT";
        } else if (currentStep === 4) {
            // On submission screen we remove headers and change button behavior
            if (mainHeader) mainHeader.remove();
            if (subHeader) subHeader.remove();
            const mainHr = document.getElementById('mainHr');
            if (mainHr) mainHr.remove();

            backBtn.style.visibility = 'hidden';
            nextBtn.textContent = "Back to Home";

            // Replace nextBtn to remove existing listeners then add one simple redirect
            const newNext = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newNext, nextBtn);
            newNext.addEventListener('click', () => {
                window.location.href = 'userPanel.php?page=userDashboard';
            });
        }
    }

    function populateSummary() {
        // map form fields into the summary view (IDs from serviceBarangayClearance.php)
        const safeVal = id => (document.getElementById(id) ? document.getElementById(id).value : '');

        // Basic fields
        const last = safeVal('lastname');
        const first = safeVal('firstname');
        const middle = safeVal('middlename');
        const street = safeVal('street');
        const purok = safeVal('purok');
        const barangay = safeVal('barangay');
        const municipality = safeVal('municipality');
        const province = safeVal('province');
        const birthdate = safeVal('birthdate');
        const age = safeVal('age');
        const birthplace = safeVal('birthplace');
        const marital = safeVal('maritalstatus');
        const ctc = safeVal('ctcnumber');
        const claim = safeVal('claimdate');
        const payment = hiddenPaymentInput ? hiddenPaymentInput.value : '';

        // Fill summary elements
        if (document.getElementById('summaryLastName')) document.getElementById('summaryLastName').textContent = last;
        if (document.getElementById('summaryFirstName')) document.getElementById('summaryFirstName').textContent = first;
        if (document.getElementById('summaryMiddleName')) document.getElementById('summaryMiddleName').textContent = middle;
        if (document.getElementById('summaryStreet')) document.getElementById('summaryStreet').textContent = street;
        if (document.getElementById('summaryPurok')) document.getElementById('summaryPurok').textContent = purok;

        const fullAddress = `${barangay || ''}${(barangay && municipality) ? ' / ' : ''}${municipality || ''}${(province) ? ' / ' + province : ''}`.replace(/^ \/ | \/ $/g, '').trim();
        if (document.getElementById('summaryAddress')) document.getElementById('summaryAddress').textContent = fullAddress;

        const birthAge = `${birthdate || ''}${(birthdate && age) ? ' / ' : ''}${age || ''}`;
        if (document.getElementById('summaryBirthAge')) document.getElementById('summaryBirthAge').textContent = birthAge;

        if (document.getElementById('summaryBirthplace')) document.getElementById('summaryBirthplace').textContent = birthplace;
        if (document.getElementById('summaryMaritalStatus')) document.getElementById('summaryMaritalStatus').textContent = marital;
        if (document.getElementById('summaryCTC')) document.getElementById('summaryCTC').textContent = ctc;
        if (document.getElementById('summaryClaimDate')) document.getElementById('summaryClaimDate').textContent = claim;
        if (document.getElementById('summaryPaymentMethod')) document.getElementById('summaryPaymentMethod').textContent = payment;
    }

    function setupPaymentControls() {
        if (!paymentButtons || paymentButtons.length === 0) return;
        paymentButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                paymentButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const method = btn.dataset.method;
                if (hiddenPaymentInput) hiddenPaymentInput.value = method;

                instructionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.method !== method);
                });
            });
        });
    }
});
