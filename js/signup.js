document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 0;
    const steps = document.querySelectorAll(".step");
    const dots = document.querySelectorAll(".dot");
    const subHeader = document.getElementById("subHeader");
    const cancelBtn = document.querySelector(".cancel-btn");
    const nextBtn = document.querySelector(".next-btn");

    // Validation function for active step
    function validateActiveStep() {
        let valid = true;
        let errorMessages = [];

        // Step 1: Personal Information
        if (currentStep === 0) {
            const firstName = document.getElementById("firstname").value.trim();
            const middleName = document.getElementById("middlename").value.trim();
            const lastName = document.getElementById("lastname").value.trim();
            const suffix = document.getElementById("suffix").value.trim();

            const nameRegex = /^[A-Za-z\s.]+$/;

            if (!nameRegex.test(firstName)) {
                valid = false;
                errorMessages.push("First Name can only contain letters, spaces, and periods.");
                document.getElementById("firstname").classList.add("is-invalid");
            } else {
                document.getElementById("firstname").classList.remove("is-invalid");
            }

            if (middleName !== "" && !nameRegex.test(middleName)) {
                valid = false;
                errorMessages.push("Middle Name can only contain letters, spaces, and periods.");
                document.getElementById("middlename").classList.add("is-invalid");
            } else {
                document.getElementById("middlename").classList.remove("is-invalid");
            }

            if (!nameRegex.test(lastName)) {
                valid = false;
                errorMessages.push("Last Name can only contain letters, spaces, and periods.");
                document.getElementById("lastname").classList.add("is-invalid");
            } else {
                document.getElementById("lastname").classList.remove("is-invalid");
            }

            if (suffix !== "" && !nameRegex.test(suffix)) {
                valid = false;
                errorMessages.push("Suffix can only contain letters and periods.");
                document.getElementById("suffix").classList.add("is-invalid");
            } else {
                document.getElementById("suffix").classList.remove("is-invalid");
            }
        }

        // Step 2: Credentials
        if (currentStep === 1) {
            const password = document.getElementById("password").value;
            const rules = {
                length: /.{8,15}/,
                uppercase: /(?=.*[a-z])(?=.*[A-Z])/,
                number: /(?=.*\d)/,
                specialChar: /(?=.*[!@#$%^&*])/
            };

            let allRulesValid = true;
            for (const rule in rules) {
                if (!rules[rule].test(password)) {
                    allRulesValid = false;
                    break;
                }
            }

            if (!allRulesValid) {
                valid = false;
                errorMessages.push("Password does not meet all requirements.");
            }
        }

        if (!valid) {
            const modalBody = document.querySelector("#validationModal .modal-body");
            modalBody.textContent = errorMessages.join(" ");
        }

        return valid;
    }

    // Next button event
    nextBtn.addEventListener("click", function () {
        let isValid = true;

        // Check required fields
        document.querySelectorAll(".step.active-step input[required], .step.active-step select[required]").forEach(field => {
            if (!field.value.trim()) {
                isValid = false;
                field.classList.add("is-invalid");
            } else {
                field.classList.remove("is-invalid");
            }
        });

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // Custom validation
        if (!validateActiveStep()) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // If on final review step
        if (currentStep === 2) {
            let confirmModal = new bootstrap.Modal(document.getElementById("confirmationModal"));
            confirmModal.show();
        } else {
            // Go to next step
            steps[currentStep].classList.remove("active-step");
            dots[currentStep].classList.remove("active-dot");
            currentStep++;
            steps[currentStep].classList.add("active-step");
            dots[currentStep].classList.add("active-dot");

            if (currentStep === 2) {
                populateSummary();
            }
            updateNavigation();
        }
    });

    // Cancel/Back button event
    cancelBtn.addEventListener("click", function () {
        if (currentStep > 0) {
            steps[currentStep].classList.remove("active-step");
            dots[currentStep].classList.remove("active-dot");
            currentStep--;
            steps[currentStep].classList.add("active-step");
            dots[currentStep].classList.add("active-dot");
            updateNavigation();
        } else {
            window.location.href = "index.php";
        }
    });

    // Update navigation
    function updateNavigation() {
        if (currentStep === 0) {
            subHeader.textContent = "Fill out all needed information";
            cancelBtn.textContent = "CANCEL";
            nextBtn.textContent = "NEXT";
        } else if (currentStep === 1) {
            subHeader.textContent = "Provide your sign in credentials.";
            cancelBtn.textContent = "PREVIOUS";
            nextBtn.textContent = "NEXT";
        } else if (currentStep === 2) {
            subHeader.textContent = "Review all provided details in the previous pages.";
            cancelBtn.textContent = "CANCEL";
            nextBtn.textContent = "SUBMIT";
        }
    }

    // Confirm submission
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
    confirmSubmitBtn.addEventListener("click", function () {
        let confirmModalEl = document.getElementById("confirmationModal");
        let confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
        if (confirmModal) {
            confirmModal.hide();
        }
        document.getElementById("registrationForm").submit();
    });

    // Password validation
    const passwordInput = document.getElementById("password");
    const rules = {
        lengthRule: /.{8,15}/,
        uppercaseRule: /(?=.*[a-z])(?=.*[A-Z])/,
        numberRule: /(?=.*\d)/,
        specialCharRule: /(?=.*[!@#$%^&*])/
    };

    const ruleElements = {
        lengthRule: document.getElementById("uppercaseRule"),
        uppercaseRule: document.getElementById("numberRule"),
        numberRule: document.getElementById("specialCharRule"),
        specialCharRule: document.getElementById("specialCharRule2")
    };

    passwordInput.addEventListener("input", function () {
        const rulesArray = [
            { regex: /.{8,15}/, element: document.getElementById("uppercaseRule") },
            { regex: /(?=.*[a-z])(?=.*[A-Z])/, element: document.getElementById("numberRule") },
            { regex: /(?=.*\d)/, element: document.getElementById("specialCharRule") },
            { regex: /(?=.*[!@#$%^&*])/, element: document.getElementById("specialCharRule2") }
        ];

        rulesArray.forEach(rule => {
            if (rule.regex.test(passwordInput.value)) {
                rule.element.classList.add("valid");
            } else {
                rule.element.classList.remove("valid");
            }
        });
    });

    // Toggle password visibility
    window.togglePassword = function (id) {
        let input = document.getElementById(id);
        let icon = input.nextElementSibling.querySelector("i");

        if (input.type === "password") {
            input.type = "text";
            icon.classList.remove("fa-eye-slash");
            icon.classList.add("fa-eye");
        } else {
            input.type = "password";
            icon.classList.remove("fa-eye");
            icon.classList.add("fa-eye-slash");
        }
    };

    // Populate summary
    function populateSummary() {
        const fn = document.getElementById("firstname").value.trim();
        const mn = document.getElementById("middlename").value.trim();
        const ln = document.getElementById("lastname").value.trim();
        const sn = document.getElementById("suffix").value.trim();
        const bd = document.getElementById("birthdate").value;
        const pu = document.getElementById("purok").value;
        const em = document.getElementById("email").value.trim();

        const suffixPart = sn ? ` ${sn}` : "";
        const middlePart = mn ? ` ${mn}` : "";
        const fullName = `${ln}, ${fn}${middlePart}${suffixPart}`;

        document.getElementById("summaryFullName").value = fullName;
        document.getElementById("summaryBirthdate").value = bd;
        document.getElementById("summaryPurok").value = `Purok ${pu}`;
        document.getElementById("summaryEmail").value = em;
    }
});