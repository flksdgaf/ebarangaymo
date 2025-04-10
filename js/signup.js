document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 0;
    const steps = document.querySelectorAll(".step");
    const dots = document.querySelectorAll(".dot");
    const subHeader = document.getElementById("subHeader");
    const cancelBtn = document.querySelector(".cancel-btn");
    const nextBtn = document.querySelector(".next-btn");

    // Dropdown elements
    const provinceSelect = document.getElementById("province");
    const municipalitySelect = document.getElementById("municipality");
    const barangaySelect = document.getElementById("barangay");

    // Data for municipalities and barangays
    const locationData = {
        "Camarines Norte": {
            "Daet": ["Barangay I", "Barangay II", "Barangay III", "Barangay IV", "Barangay V", "Barangay VI", "Barangay VII", "Barangay VIII", "Alawihao", "Awitan", "Bagasbas", "Bibirao", "Borabod", "Calasgasan", "Camambugan", "Cobangbang", "Dogongan", "Gahonon", "Gubat", "Lag-on", "Magang", "Mambalite", "Mancruz", "Pamorangon", "San Isidro"],
        },
    };

    // Province selection event
    provinceSelect.addEventListener("change", function () {
        const selectedProvince = this.value;
        municipalitySelect.innerHTML = '<option value="">Select Municipality</option>';
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';
        barangaySelect.disabled = true;

        if (selectedProvince) {
            municipalitySelect.disabled = false;
            for (const municipality in locationData[selectedProvince]) {
                let option = document.createElement("option");
                option.value = municipality;
                option.textContent = municipality;
                municipalitySelect.appendChild(option);
            }
        } else {
            municipalitySelect.disabled = true;
        }
    });

    // Municipality selection event
    municipalitySelect.addEventListener("change", function () {
        const selectedProvince = provinceSelect.value;
        const selectedMunicipality = this.value;
        barangaySelect.innerHTML = '<option value="">Select Barangay</option>';

        if (selectedMunicipality) {
            barangaySelect.disabled = false;
            locationData[selectedProvince][selectedMunicipality].forEach(barangay => {
                let option = document.createElement("option");
                option.value = barangay;
                option.textContent = barangay;
                barangaySelect.appendChild(option);
            });
        } else {
            barangaySelect.disabled = true;
        }
    });

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
            const contact = document.getElementById("contact").value.trim();
            const email = document.getElementById("email").value.trim();

            // Regex rules
            const nameRegex = /^[A-Za-z ]+$/; // letters and space
            const suffixRegex = /^[A-Za-z\.]+$/; // letters and period
            const contactRegex = /^09\d{9}$/; // must start with "09" and followed by 9 digits, total 11 characters
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/; // basic email validation

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

            // Validate Contact Number
            if (!contactRegex.test(contact)) {
                valid = false;
                errorMessages.push("Contact Number must be exactly 11 digits, numeric, and start with '09'.");
                document.getElementById("contact").classList.add("is-invalid");
            } else {
                document.getElementById("contact").classList.remove("is-invalid");
            }

            // Validate Email
            if (!emailRegex.test(email)) {
                valid = false;
                errorMessages.push("Please enter a valid email address.");
                document.getElementById("email").classList.add("is-invalid");
            } else {
                document.getElementById("email").classList.remove("is-invalid");
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
            subHeader.textContent = "Fill out personal information";
            cancelBtn.textContent = "Cancel";
            nextBtn.textContent = "Next";
        } else if (currentStep === 1) {
            subHeader.textContent = "Provide your exact address";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Next";
        } else if (currentStep === 2) {
            subHeader.textContent = "Upload any of your available valid ID";
            cancelBtn.textContent = "Back";
            nextBtn.textContent = "Next";
        } else if (currentStep === 3) {
            subHeader.textContent = "Create a unique username and a strong password to secure your account.";
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
    // --- Step 1: Personal Information ---
    // Get all inputs from the first step (personal details)
    const firstName   = document.getElementById("firstname").value;
    const middleName  = document.getElementById("middlename").value;
    const lastName    = document.getElementById("lastname").value;
    const suffix      = document.getElementById("suffix").value;
    const birthdate   = document.getElementById("birthdate").value;
    const contact     = document.getElementById("contact").value;
    const email       = document.getElementById("email").value;
    
    // Build full name (include middle name and suffix only if provided)
    let fullName = firstName;
    if (middleName.trim() !== "") {
        fullName += " " + middleName;
    }
    fullName += " " + lastName;
    if (suffix.trim() !== "") {
        fullName += " " + suffix;
    }
    
    // --- Step 2: Address Information ---
    // Get all inputs from the second step (address)
    const province     = document.getElementById("province").value;
    const municipality = document.getElementById("municipality").value;
    const barangay     = document.getElementById("barangay").value;
    const purok        = document.getElementById("purok").value;
    const block        = document.getElementById("block").value;
    const zip          = document.getElementById("zip").value; 
    
    // Concatenate the address details
    const address = `${block}, ${purok}, ${barangay}, ${municipality}, ${province} ${zip}`;
    
    // --- Populate Summary Fields in Step 5 ---
    document.getElementById("summaryFullName").textContent = fullName;
    document.getElementById("summaryBirthdate").textContent = birthdate;
    document.getElementById("summaryContact").textContent = contact;
    document.getElementById("summaryEmail").textContent = email;
    document.getElementById("summaryAddress").textContent = address;
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


