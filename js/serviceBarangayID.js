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

    // — ADDED: Payment method controls
    const paymentButtons    = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    const hiddenPaymentInput = document.getElementById('paymentMethod');

    const confirmationModalEl = document.getElementById("confirmationModal"); // ← ADDED
    const confirmationModal = new bootstrap.Modal(confirmationModalEl);     // ← ADDED
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");    

    // Initial state
    updateNavigation();
    setupPaymentControls(); // ADDED

    nextBtn.addEventListener('click', () => {
        let isValid = true;

        // If on step 2, ensure a payment method is chosen
        if (currentStep === 2 && !hiddenPaymentInput.value) {
            isValid = false;
        }

        // Validate required inputs on steps 1 &  if any
        if (currentStep === 1) {
            document.querySelectorAll(".step.active-step input[required], .step.active-step select[required]")
              .forEach(field => {
                if (!field.value.trim()) {
                    isValid = false;
                    field.classList.add("is-invalid");
                } else {
                    field.classList.remove("is-invalid");
                }
            });
        }

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        if (currentStep === 3) {
            confirmationModal.show();
        } else {
            // Move forward
            steps[currentStep - 1].classList.remove("active-step");
            circleSteps[currentStep - 1].classList.add('completed');
            stepLabels[currentStep - 1].classList.add('completed');
            currentStep++;
            steps[currentStep - 1].classList.add("active-step");
            circleSteps[currentStep - 1].classList.add('active');
            stepLabels[currentStep - 1].classList.add('active');

            // Update progress bar
            const progressPercent = ((currentStep - 1) / (totalSteps - 1)) * 100;
            progressFill.style.width = `${progressPercent}%`;
            
            // If stepping into summary, populate all fields
            if (currentStep === 3) {
                populateSummary();
            }
    
            updateNavigation();
        }
    });

    backBtn.addEventListener('click', () => {
        if (currentStep > 1) {
            steps[currentStep - 1].classList.remove("active-step");
            circleSteps[currentStep - 1].classList.remove('active');
            stepLabels[currentStep - 1].classList.remove('active');

            // Un-complete the prior step
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

    confirmSubmitBtn.addEventListener('click', () => {                        // ← ADDED
        document.getElementById("barangayIDForm").submit();                   // ← ADDED
    });

    function updateNavigation() {
        backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === 1) {
            mainHeader.textContent = "APPLICATION FORM";
            subHeader.textContent = "Provide the necessary details to apply for your Barangay ID.";
            nextBtn.textContent = "NEXT >";
        } else if (currentStep === 2) {
            mainHeader.textContent = "PAYMENT";
            subHeader.textContent = "Settle your payment for your Barangay ID.";
            nextBtn.textContent = "NEXT >";
        } else if (currentStep === 3) {
            mainHeader.textContent = "REVIEW and CONFIRMATION";
            subHeader.textContent = "Please review all your information before submitting.";
            nextBtn.textContent = "SUBMIT";
        } else if (currentStep === 4) {
            mainHeader.remove();
            subHeader.remove();
            document.getElementById('mainHr').remove();
            backBtn.style.visibility = 'hidden';
            nextBtn.textContent = "Back to Home";
            nextBtn.replaceWith(nextBtn.cloneNode(true));
            const newNext = document.getElementById('nextBtn') || document.querySelector('#nextBtn');
            newNext.addEventListener('click', () => {
                window.location.href = 'userpanel.php?page=userDashboard';
            });
        }
    }

    
    function populateSummary() {
        // existing fields...
        document.getElementById("summarytransactionType").textContent = document.getElementById("transactiontype").value;
        document.getElementById("summaryFullName").textContent        = document.getElementById("fullname").value;
        document.getElementById("summaryAddress").textContent         = document.getElementById("address").value;
        document.getElementById("summaryHeight").textContent          = document.getElementById("height").value;
        document.getElementById("summaryWeight").textContent          = document.getElementById("weight").value;
        document.getElementById("summaryBirthdate").textContent       = document.getElementById("birthday").value;
        document.getElementById("summaryBirthplace").textContent      = document.getElementById("birthplace").value;
        document.getElementById("summaryCivilStatus").textContent     = document.getElementById("civilstatus").value;
        document.getElementById("summaryReligion").textContent        = document.getElementById("religion").value;
        document.getElementById("summaryContactPerson").textContent   = document.getElementById("contactperson").value;
        document.getElementById("summaryClaimDate").textContent       = document.getElementById("claimdate").value;
        document.getElementById("summaryPaymentMethod").textContent   = hiddenPaymentInput.value;
    }

    // ADDED: handle payment method UI
    function setupPaymentControls() {
        paymentButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                // toggle active class
                paymentButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const method = btn.dataset.method;
                hiddenPaymentInput.value = method;

                // show matching panel
                instructionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.method !== method);
                });
            });
        });
    }
});
