document.addEventListener("DOMContentLoaded", function () {
    let currentStep = 0;
    const steps = document.querySelectorAll(".step");
    const dots = document.querySelectorAll(".dot");
    const subHeader = document.getElementById("subHeader");
    const cancelBtn = document.querySelector(".cancel-btn");
    const nextBtn = document.querySelector(".next-btn");

    // // OCR validation for birthdate on ID
    // async function validateIDBirthdate(frontIDFile, enteredBirthdate) {
    //     try {
    //         // Show progress in console
    //         const { data: { text } } = await Tesseract.recognize(
    //             frontIDFile,
    //             'eng',
    //             {
    //                 logger: m => {
    //                     if (m.status === 'recognizing text') {
    //                         console.log(`OCR Progress: ${Math.round(m.progress * 100)}%`);
    //                     }
    //                 }
    //             }
    //         );
            
    //         console.log('OCR Extracted Text:', text);
            
    //         // Month names mapping
    //         const monthNames = {
    //             'january': 0, 'jan': 0,
    //             'february': 1, 'feb': 1,
    //             'march': 2, 'mar': 2,
    //             'april': 3, 'apr': 3,
    //             'may': 4,
    //             'june': 5, 'jun': 5,
    //             'july': 6, 'jul': 6,
    //             'august': 7, 'aug': 7,
    //             'september': 8, 'sep': 8, 'sept': 8,
    //             'october': 9, 'oct': 9,
    //             'november': 10, 'nov': 10,
    //             'december': 11, 'dec': 11
    //         };
            
    //         // Extract dates from OCR text (various formats)
    //         const datePatterns = [
    //             // Month DD, YYYY (e.g., "July 09, 2003", "July 9, 2003")
    //             /\b(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*[\s,]+(\d{1,2})[\s,]+(\d{4})\b/gi,
                
    //             // DD Month YYYY (e.g., "09 July 2003", "9 July 2003")
    //             /\b(\d{1,2})\s+(January|February|March|April|May|June|July|August|September|October|November|December|Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*\s+(\d{4})\b/gi,
                
    //             // MM/DD/YYYY or DD/MM/YYYY
    //             /\b(\d{2})\/(\d{2})\/(\d{4})\b/g,
                
    //             // YYYY-MM-DD
    //             /\b(\d{4})-(\d{2})-(\d{2})\b/g,
                
    //             // DD-MM-YYYY or MM-DD-YYYY
    //             /\b(\d{2})-(\d{2})-(\d{4})\b/g
    //         ];
            
    //         let foundMatch = false;
    //         const entered = new Date(enteredBirthdate);
    //         const extractedDates = [];
            
    //         for (const pattern of datePatterns) {
    //             const matches = text.matchAll(pattern);
    //             for (const match of matches) {
    //                 let testDate;
                    
    //                 // Handle "Month DD, YYYY" format (e.g., "July 09, 2003")
    //                 if (match[1] && isNaN(match[1])) {
    //                     const monthStr = match[1].toLowerCase().replace(/[^a-z]/g, '');
    //                     const monthNum = monthNames[monthStr];
    //                     if (monthNum !== undefined) {
    //                         const day = parseInt(match[2]);
    //                         const year = parseInt(match[3]);
    //                         testDate = new Date(year, monthNum, day);
    //                         console.log(`Parsed Month-Day-Year: ${match[1]} ${match[2]}, ${match[3]} => ${testDate}`);
    //                     }
    //                 }
    //                 // Handle "DD Month YYYY" format (e.g., "09 July 2003")
    //                 else if (match[2] && isNaN(match[2])) {
    //                     const monthStr = match[2].toLowerCase().replace(/[^a-z]/g, '');
    //                     const monthNum = monthNames[monthStr];
    //                     if (monthNum !== undefined) {
    //                         const day = parseInt(match[1]);
    //                         const year = parseInt(match[3]);
    //                         testDate = new Date(year, monthNum, day);
    //                         console.log(`Parsed Day-Month-Year: ${match[1]} ${match[2]} ${match[3]} => ${testDate}`);
    //                     }
    //                 }
    //                 // YYYY-MM-DD format
    //                 else if (match[0].includes('-') && match[1].length === 4) {
    //                     testDate = new Date(match[1], parseInt(match[2]) - 1, match[3]);
    //                     console.log(`Parsed YYYY-MM-DD: ${match[0]} => ${testDate}`);
    //                 }
    //                 // MM/DD/YYYY or DD/MM/YYYY or MM-DD-YYYY or DD-MM-YYYY
    //                 else if (match[3]) {
    //                     const date1 = new Date(match[3], parseInt(match[1]) - 1, match[2]); // MM/DD/YYYY
    //                     const date2 = new Date(match[3], parseInt(match[2]) - 1, match[1]); // DD/MM/YYYY
                        
    //                     // Check which format matches the entered date better
    //                     if (Math.abs(date1 - entered) < Math.abs(date2 - entered)) {
    //                         testDate = date1;
    //                         console.log(`Parsed MM/DD/YYYY: ${match[0]} => ${testDate}`);
    //                     } else {
    //                         testDate = date2;
    //                         console.log(`Parsed DD/MM/YYYY: ${match[0]} => ${testDate}`);
    //                     }
    //                 }
                    
    //                 if (testDate && !isNaN(testDate.getTime())) {
    //                     extractedDates.push(testDate);
                        
    //                     // Check if dates match (within 1 day tolerance for parsing differences)
    //                     const daysDiff = Math.abs((testDate - entered) / (1000 * 60 * 60 * 24));
    //                     if (daysDiff <= 1) {
    //                         foundMatch = true;
    //                         console.log('✓ Match found!', testDate.toLocaleDateString());
    //                         break;
    //                     }
    //                 }
    //             }
    //             if (foundMatch) break;
    //         }
            
    //         console.log('All extracted dates from ID:', extractedDates.map(d => d.toLocaleDateString()));
    //         console.log('Entered birthdate:', entered.toLocaleDateString());
            
    //         return {
    //             success: foundMatch,
    //             message: foundMatch 
    //                 ? 'Birthdate verified with ID ✓' 
    //                 : 'Warning: Birthdate on ID does not match the entered birthdate. Please verify your information.',
    //             extractedDates: extractedDates
    //         };
            
    //     } catch (error) {
    //         console.error('OCR Error:', error);
    //         return {
    //             success: false,
    //             message: 'Could not verify ID automatically. Please ensure the image is clear and try again.',
    //             error: error.message
    //         };
    //     }
    // }

    // Username availability check
    const usernameInput = document.getElementById("username");
    let usernameTimeout;

    // Email availability check
    const emailInput = document.getElementById("email");
    let emailTimeout;

    emailInput.addEventListener("input", function() {
        clearTimeout(emailTimeout);
        const email = this.value.trim();
        
        // Remove any existing feedback
        const existingFeedback = this.parentElement.querySelector('.email-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        // Basic email format check
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(email)) return;
        
        emailTimeout = setTimeout(() => {
            fetch('functions/check_email.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'email=' + encodeURIComponent(email)
            })
            .then(response => response.json())
            .then(data => {
                const feedback = document.createElement('small');
                feedback.className = 'email-feedback';
                feedback.style.display = 'block';
                feedback.style.marginTop = '5px';
                feedback.style.fontSize = '0.75rem';
                
                if (data.exists) {
                    feedback.style.color = '#dc3545';
                    feedback.textContent = '✗ Email already taken';
                    emailInput.classList.add('is-invalid');
                } else {
                    feedback.style.color = '#28a745';
                    feedback.textContent = '✓ Email available';
                    emailInput.classList.remove('is-invalid');
                }
                
                emailInput.parentElement.appendChild(feedback);
            });
        }, 500);
    });

    usernameInput.addEventListener("input", function() {
        clearTimeout(usernameTimeout);
        const username = this.value.trim();
        
        // Remove any existing feedback
        const existingFeedback = this.parentElement.querySelector('.username-feedback');
        if (existingFeedback) {
            existingFeedback.remove();
        }
        
        if (username.length < 3) return;
        
        usernameTimeout = setTimeout(() => {
            fetch('functions/check_username.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'username=' + encodeURIComponent(username)
            })
            .then(response => response.json())
            .then(data => {
                const feedback = document.createElement('small');
                feedback.className = 'username-feedback';
                feedback.style.display = 'block';
                feedback.style.marginTop = '5px';
                feedback.style.fontSize = '0.75rem';
                
                if (data.exists) {
                    feedback.style.color = '#dc3545';
                    feedback.textContent = '✗ Username already taken';
                    usernameInput.classList.add('is-invalid');
                } else {
                    feedback.style.color = '#28a745';
                    feedback.textContent = '✓ Username available';
                    usernameInput.classList.remove('is-invalid');
                }
                
                usernameInput.parentElement.appendChild(feedback);
            });
        }, 500);
    });

    // Validation function for active step
    function validateActiveStep() {
        let valid = true;
        let errorMessages = [];

        // Step 1: Personal Information
        if (currentStep === 0) {
            const firstName = document.getElementById("firstname").value.trim();
            const middleName = document.getElementById("middlename").value.trim();
            const lastName = document.getElementById("lastname").value.trim();

            // const nameRegex = /^[A-Za-z\s.]+$/;
            const nameRegex = /^[\p{L}\s.\-']+$/u;

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

            // Age validation - must be 10 years or older
            const birthdate = document.getElementById("birthdate").value;
            if (birthdate) {
                const birthDate = new Date(birthdate);
                const today = new Date();
                let age = today.getFullYear() - birthDate.getFullYear();
                const monthDiff = today.getMonth() - birthDate.getMonth();
                
                // Adjust age if birthday hasn't occurred this year
                if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
                    age--;
                }
                
                if (age < 10) {
                    valid = false;
                    errorMessages.push("You must be at least 10 years old to register.");
                    document.getElementById("birthdate").classList.add("is-invalid");
                } else {
                    document.getElementById("birthdate").classList.remove("is-invalid");
                }
            }

            // if (suffix !== "" && !nameRegex.test(suffix)) {
            //     valid = false;
            //     errorMessages.push("Suffix can only contain letters and periods.");
            //     document.getElementById("suffix").classList.add("is-invalid");
            // } else {
            //     document.getElementById("suffix").classList.remove("is-invalid");
            // }
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

            // Check if username is taken
            const usernameFeedback = document.querySelector('.username-feedback');
            if (usernameFeedback && usernameFeedback.textContent.includes('taken')) {
                valid = false;
                errorMessages.push("Username is already taken. Please choose another one.");
            }

            // Check if email is taken
            const emailFeedback = document.querySelector('.email-feedback');
            if (emailFeedback && emailFeedback.textContent.includes('taken')) {
                valid = false;
                errorMessages.push("Email is already taken. Please use another email.");
            }
        }

         if (currentStep === 2) {
            const privacyConsent = document.getElementById("privacyConsent");
            if (!privacyConsent.checked) {
                valid = false;
                errorMessages.push("Please read and agree to the Data Privacy Agreement before proceeding.");
                privacyConsent.classList.add("is-invalid");
            } else {
                privacyConsent.classList.remove("is-invalid");
            }
        }

        if (!valid) {
            const modalMessage = document.querySelector("#validationModal .modal-message");
            modalMessage.textContent = errorMessages.join(" ");
        }

        return valid;
    }

    // Next button event
    nextBtn.addEventListener("click", async function () {
        let isValid = true;

        // Check required fields
        document.querySelectorAll(".step.active-step input[required], .step.active-step select[required]").forEach(field => {
            // Special validation for email field
            if (field.type === 'email') {
                const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                if (!field.value.trim() || !emailRegex.test(field.value.trim())) {
                    isValid = false;
                    field.classList.add("is-invalid");
                } else {
                    field.classList.remove("is-invalid");
                }
            } else if (!field.value.trim()) {
                isValid = false;
                field.classList.add("is-invalid");
            } else {
                field.classList.remove("is-invalid");
            }
        });

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            // Update modal message for better user feedback
            const modalMessage = document.querySelector("#validationModal .modal-message");
            modalMessage.textContent = "Please fill in all required fields correctly. Make sure the email address is valid.";
            validationModal.show();
            return;
        }

        // Custom validation
        if (!validateActiveStep()) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // // OCR Validation for Step 1 (Personal Information)
        // if (currentStep === 0) {
        //     const frontIDFile = document.getElementById('frontID').files[0];
        //     const enteredBirthdate = document.getElementById('birthdate').value;
            
        //     if (frontIDFile && enteredBirthdate) {
        //         // Disable button and show loading state
        //         nextBtn.disabled = true;
        //         const originalText = nextBtn.textContent;
        //         nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>Verifying ID...';
                
        //         try {
        //             const result = await validateIDBirthdate(frontIDFile, enteredBirthdate);
                    
        //             // Re-enable button
        //             nextBtn.disabled = false;
        //             nextBtn.textContent = originalText;
                    
        //             if (!result.success) {
        //                 // Show warning but allow user to proceed
        //                 const proceedAnyway = confirm(
        //                     result.message + '\n\n' +
        //                     'Extracted dates from ID: ' + (result.extractedDates?.map(d => d.toLocaleDateString()).join(', ') || 'None found') + '\n' +
        //                     'Your entered birthdate: ' + new Date(enteredBirthdate).toLocaleDateString() + '\n\n' +
        //                     'Do you want to proceed anyway? Click OK to continue or Cancel to fix your birthdate.'
        //                 );
                        
        //                 if (!proceedAnyway) {
        //                     return;
        //                 }
        //             } else {
        //                 // Show success message briefly
        //                 const successMsg = document.createElement('div');
        //                 successMsg.className = 'alert alert-success mt-2';
        //                 successMsg.textContent = '✓ Birthdate verified successfully!';
        //                 document.querySelector('.step.active-step').appendChild(successMsg);
        //                 setTimeout(() => successMsg.remove(), 2000);
        //             }
        //         } catch (error) {
        //             // Re-enable button
        //             nextBtn.disabled = false;
        //             nextBtn.textContent = originalText;
                    
        //             console.error('Validation error:', error);
        //             alert('An error occurred during ID verification. You may proceed, but please ensure your information is correct.');
        //         }
        //     }
        // }

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
        const bd = document.getElementById("birthdate").value;
        const pu = document.getElementById("purok").value;
        const em = document.getElementById("email").value.trim();

        const middlePart = mn ? ` ${mn}` : "";
        const fullName = `${ln}, ${fn}${middlePart}`;

        document.getElementById("summaryFullName").value = fullName;
        document.getElementById("summaryBirthdate").value = bd;
        document.getElementById("summaryPurok").value = `Purok ${pu}`;
        document.getElementById("summaryEmail").value = em;
    }
});