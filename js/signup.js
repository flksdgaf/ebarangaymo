document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 0;
    const steps = document.querySelectorAll(".step");
    const dots = document.querySelectorAll(".dot");
    const subHeader = document.getElementById("subHeader");
    const cancelBtn = document.querySelector(".cancel-btn");
    const nextBtn = document.querySelector(".next-btn");

    // New function to validate the active step
    function validateActiveStep() {
        let valid = true;
        let errorMessages = [];

        // Perform validations for Step 1 (Personal Information)
        if (currentStep === 0) {
            // Retrieve values from inputs
            const firstName = document.getElementById("firstname").value.trim();
            const middleName = document.getElementById("middlename").value.trim();
            const lastName = document.getElementById("lastname").value.trim();
            const suffix = document.getElementById("suffix").value.trim();


            // Regex rules
            const nameRegex = /^[A-Za-z ]+$/; // letters and space
            const suffixRegex = /^[A-Za-z\.]+$/; // letters and period

            // Validate First Name
            if (!nameRegex.test(firstName)) {
                valid = false;
                errorMessages.push("First Name can only contain alphabet characters.");
                document.getElementById("firstname").classList.add("is-invalid");
            } else {
                document.getElementById("firstname").classList.remove("is-invalid");
            }

            // Validate Middle Name if filled
            if (middleName !== "" && !nameRegex.test(middleName)) {
                valid = false;
                errorMessages.push("Middle Name can only contain alphabet characters.");
                document.getElementById("middlename").classList.add("is-invalid");
            } else {
                document.getElementById("middlename").classList.remove("is-invalid");
            }

            // Validate Last Name
            if (!nameRegex.test(lastName)) {
                valid = false;
                errorMessages.push("Last Name can only contain alphabet characters.");
                document.getElementById("lastname").classList.add("is-invalid");
            } else {
                document.getElementById("lastname").classList.remove("is-invalid");
            }

            // Validate Suffix if filled
            if (suffix !== "" && !suffixRegex.test(suffix)) {
                valid = false;
                errorMessages.push("Suffix can only contain alphabet characters and a period.");
                document.getElementById("suffix").classList.add("is-invalid");
            } else {
                document.getElementById("suffix").classList.remove("is-invalid");
            }
        }
        // Additional validations for other steps can be added here if necessary

        // If validation fails, update the modal message with the errors
        if (!valid) {
            const modalBody = document.querySelector("#validationModal .modal-body");
            modalBody.textContent = errorMessages.join(" ");
        }

        return valid;
    }

    // Next button event for multi-step form
    nextBtn.addEventListener("click", function () {
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

        // Now, perform the custom validation based on the step.
        if (!validateActiveStep()) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // if (currentStep === 0) {
        //     // auto-fill Unknown for step-2 birth reg
        //     const br = document.getElementById("birthreg");
        //     if (!br.value.trim()) {
        //     br.value = "Unknown";
        //     }
        // }

        // If on the final review step (Step 5)
        if (currentStep === 4) {
            // Show the confirmation modal
            let confirmModal = new bootstrap.Modal(document.getElementById("confirmationModal"));
            confirmModal.show();
        } else {
            // Navigate to the next step normally
            steps[currentStep].classList.remove("active-step");
            dots[currentStep].classList.remove("active-dot");
            currentStep++;
            steps[currentStep].classList.add("active-step");
            dots[currentStep].classList.add("active-dot");

            if (currentStep === 4) {
                populateSummary();
            }
            updateNavigation();
        }   
    });

    // Back/Cancel button event
    cancelBtn.addEventListener("click", function () {
        if (currentStep > 0) {
            steps[currentStep].classList.remove("active-step");
            dots[currentStep].classList.remove("active-dot");
            currentStep--;
            steps[currentStep].classList.add("active-step");
            dots[currentStep].classList.add("active-dot");

            // Update sub-header and button text
            updateNavigation();
        } else {
            window.location.href = "signinup.php"; // Redirect to home
        }
    });

    // Function to update navigation labels
    function updateNavigation() {
        if (currentStep === 0) {
            subHeader.textContent = "Fill out personal information.";
            cancelBtn.textContent = "Cancel";
            nextBtn.textContent = "Next";
        } else if (currentStep === 1) {
            subHeader.textContent = "Fill out personal information.";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Next";
        } else if (currentStep === 2) {
            subHeader.textContent = "Upload any of your available valid ID.";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Next";
        } else if (currentStep === 3) {
            subHeader.textContent = "Upload a profile picture and set up unique credentials to secure your account.";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Next";
        } else if (currentStep === 4) {
            subHeader.textContent = "Review your filled-out information.";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Finish";
        }
        // Future steps can be handled here
    }

    // When Confirm is clicked in the confirmation modal, trigger form submission.
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");
    confirmSubmitBtn.addEventListener("click", function () {
        // Hide the confirmation modal
        let confirmModalEl = document.getElementById("confirmationModal");
        let confirmModal = bootstrap.Modal.getInstance(confirmModalEl);
        if (confirmModal) {
            confirmModal.hide();
        }

        document.getElementById("registrationForm").submit();
    });

});

document.addEventListener("DOMContentLoaded", function () {
    const passwordInput = document.getElementById("password");
    const confirmPasswordInput = document.getElementById("confirmPassword");
    const confirmMessage = document.getElementById("confirmMessage");

    const rules = {
        lengthRule: /.{8,15}/,
        uppercaseRule: /(?=.*[a-z])(?=.*[A-Z])/,
        numberRule: /(?=.*\d)/,
        specialCharRule: /(?=.*[!@#$%^&*])/
    };

    const ruleElements = {
        lengthRule: document.getElementById("lengthRule"),
        uppercaseRule: document.getElementById("uppercaseRule"),
        numberRule: document.getElementById("numberRule"),
        specialCharRule: document.getElementById("specialCharRule")
    };

    passwordInput.addEventListener("input", function () {
        let valid = true;

        for (const rule in rules) {
            if (rules[rule].test(passwordInput.value)) {
                ruleElements[rule].classList.add("valid");
            } else {
                ruleElements[rule].classList.remove("valid");
                valid = false;
            }
        }

        confirmPasswordInput.disabled = !valid;
    });

    confirmPasswordInput.addEventListener("input", function () {
        if (confirmPasswordInput.value === passwordInput.value) {
            confirmMessage.textContent = "Passwords match!";
            confirmMessage.classList.remove("text-danger");
            confirmMessage.classList.add("text-success");
        } else {
            confirmMessage.textContent = "Passwords do not match!";
            confirmMessage.classList.remove("text-success");
            confirmMessage.classList.add("text-danger");
        }
    });

    // Toggle Password Visibility
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
});

function populateSummary() {
  // Step 1
  const fn = document.getElementById("firstname").value.trim();
  const mn = document.getElementById("middlename").value.trim();
  const ln = document.getElementById("lastname").value.trim();
  const sn = document.getElementById("suffix").value.trim();
  const sx = document.getElementById("sex").value;
  const bd = document.getElementById("birthdate").value;
  let fullName = `${ln} ${sn}, ${fn}${mn ? ' ' + mn : ''}`;

  // Step 2
  const cs = document.getElementById("civilstatus").value;
  const bt = document.getElementById("bloodtype").value;
  let br = document.getElementById("birthreg").value.trim();
  if (!br) br = "Unknown";
  const ed = document.getElementById("educationalattainment").value;
  const oc = document.getElementById("occupation").value;
  const pu = document.getElementById("purok").value;

  // Inject into the review
  document.getElementById("summaryFullName").value       = fullName;
  document.getElementById("summaryBirthdate").value      = bd;
  document.getElementById("summarySex").value            = sx;
  document.getElementById("summaryCivilStatus").value    = cs;
  document.getElementById("summaryBloodType").value      = bt;
  document.getElementById("summaryBirthReg").value       = br;
  document.getElementById("summaryEducation").value      = ed;
  document.getElementById("summaryOccupation").value     = oc;
  document.getElementById("summaryPurok").value          = pu;

}


function submitRegistration() {
    const form = document.getElementById("registrationForm");
    const formData = new FormData(form);

    fetch("new_acc_signup.php", {
        method: "POST",
        body: formData
    })
    .then(response => response.text())
    .then(text => {
        console.log("Response text:", text);
        return JSON.parse(text);
    })
    .then(data => {
        if (data.success) {
            alert("Registration successful!");
            window.location.href = "signinup.php";
        } else {
            alert("Registration failed: " + data.error);
        }
    })
    .catch(error => {
        console.error("Error:", error);
    });
}


