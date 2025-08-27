document.addEventListener("DOMContentLoaded", function () {
    // dynamic collections (we will refresh these if DOM changes)
    let steps = document.querySelectorAll(".step");
    let circleSteps = document.querySelectorAll('.circle');
    let stepLabels = document.querySelectorAll('.step-label');

    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const progressFill = document.getElementById('progressFill');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');

    // Payment UI
    const paymentButtons = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    const hiddenPaymentInput = document.getElementById('paymentMethod');
    const hiddenPaymentAmount = document.getElementById('paymentAmount');
    const hiddenPaymentStatus = document.getElementById('paymentStatus');

    // Modals
    const confirmationModalEl = document.getElementById("confirmationModal");
    const confirmationModal = new bootstrap.Modal(confirmationModalEl);
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    // initial step (may be overwritten by inline PHP script)
    let currentStep = window.initialStep || 1;

    // user / selectors
    const currentUser = window.currentUser || {};
    const forSelect = document.getElementById('forSelect');
    const certInput = document.getElementById('certType');
    const certFieldsHolder = document.getElementById('certFields');

    // convenience defaults
    const DEFAULT_AMOUNT = 130;

    // update dynamic collections helper
    function refreshStepCollections() {
        steps = document.querySelectorAll(".step");
        circleSteps = document.querySelectorAll('.circle');
        stepLabels = document.querySelectorAll('.step-label');
    }

    // get numeric indices (1-based) for special steps if present
    function getStepIndexByElementId(id) {
        refreshStepCollections();
        for (let i = 0; i < steps.length; i++) {
            if (steps[i].id === id) return i + 1;
        }
        return -1;
    }
    function isPaymentStepPresent() {
        return document.getElementById('paymentStep') !== null;
    }
    function getPaymentStepIndex() {
        return getStepIndexByElementId('paymentStep');
    }
    function getSummaryStepIndex() {
        return getStepIndexByElementId('summaryStep');
    }
    function getSubmissionStepIndex() {
        return getStepIndexByElementId('submissionStep');
    }

    function totalSteps() {
        refreshStepCollections();
        return circleSteps.length;
    }

    // — ADDED: Payment method controls
    const setupPaymentControls = function () {
        if (!paymentButtons || paymentButtons.length === 0) return;

        // If we have an existing payment method from server, mark the corresponding button active
        const serverMethod = (window.existingPaymentMethod || '').trim();
        const clientMethod = (hiddenPaymentInput?.value || '').trim();
        const initialMethod = clientMethod || serverMethod || 'Brgy Payment Device';

        paymentButtons.forEach(b => {
            if (b.dataset.method === initialMethod) b.classList.add('active');
            b.addEventListener('click', () => {
                // toggle active class
                paymentButtons.forEach(x => x.classList.remove('active'));
                b.classList.add('active');

                const method = b.dataset.method;
                // set hidden payment fields
                if (hiddenPaymentInput) hiddenPaymentInput.value = method || '';
                if (hiddenPaymentAmount) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
                if (hiddenPaymentStatus) hiddenPaymentStatus.value = 'Pending';

                // show matching panel
                instructionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.method !== method);
                });
            });
        });

        // If page loaded with server-side method, ensure hidden fields reflect server values
        if (serverMethod) {
            if (hiddenPaymentInput) hiddenPaymentInput.value = serverMethod;
        }
        if (window.existingPaymentAmount !== undefined && window.existingPaymentAmount !== null) {
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = String(window.existingPaymentAmount);
        }
        if (window.existingPaymentStatus !== undefined && window.existingPaymentStatus !== null) {
            if (hiddenPaymentStatus) hiddenPaymentStatus.value = String(window.existingPaymentStatus);
        }
    };

    // Auto-complete for certType (kept as in your original)
    (function(){
        const options = [
            "Residency",
            "Indigency",
            "Good Moral",
            "Solo Parent",
            "Guardianship"
        ];
        const input = certInput;
        const list  = document.getElementById('certTypeList');
        function rebuildList(filtered) {
            list.innerHTML = '';
            filtered.forEach(opt => {
                const li = document.createElement('li');
                li.textContent = opt;
                li.className   = 'list-group-item list-group-item-action py-1';
                li.style.cursor = 'pointer';
                li.addEventListener('mousedown', () => {
                    input.value = opt;
                    list.style.display = 'none';
                    // trigger change handler
                    input.dispatchEvent(new Event('change'));
                });
                list.appendChild(li);
            });
            list.style.display = filtered.length ? 'block' : 'none';
        }
        input.addEventListener('focus',  () => rebuildList(options));
        input.addEventListener('input', () => {
            const v = input.value.trim().toLowerCase();
            rebuildList(v ? options.filter(o => o.toLowerCase().includes(v)) : options);
        });
        input.addEventListener('blur', () => setTimeout(()=> list.style.display='none',150));
        document.addEventListener('click', e => {
            if (!input.contains(e.target) && !list.contains(e.target)) list.style.display='none';
        });
    })();

    // --- helper utils used by field rendering ---
    function invertName(name) {
        if (!name) return '';
        if (!name.includes(',')) return name.trim();
        const [last, first] = name.split(',',2).map(s=>s.trim());
        return `${first} ${last}`;
    }
    function computeAge(birthdate) {
        if (!birthdate) return '';
        const bd = new Date(birthdate);
        const diff = Date.now() - bd.getTime();
        return Math.floor(diff / (1000*60*60*24*365.25));
    }

    // config for fields (kept same as your original)
    const certConfigs = {
        residency: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'residing_years',  label: 'Years Residing',  type: 'number' },
            { id: 'purpose',         label: 'Purpose',         type: 'text'   },
            { id: 'claim_date',      label: 'Claim Date',      type: 'date'   }
        ],
        indigency: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'text' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'date' }
        ],
        'good moral': [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'text' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'date' }
        ],
        'solo parent': [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     }
        ],
        guardianship: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     }
        ]
    };

    // child sections renderer (kept as original)
    function renderChildSections(type) {
        const wrapper = document.createElement('div');
        wrapper.id = 'childSection';

        if (type === 'guardianship') {
            wrapper.innerHTML = `
            <div id="guardianChildren"></div>
            <button type="button" id="addGuardianChild" class="btn btn-sm btn-outline-primary mb-3"> + Add Child </button>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Claim Date:</label>
                <div class="col-sm-10">
                <input type="date" name="claim_date" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Purpose:</label>
                <div class="col-sm-10">
                <input type="text" name="purpose" class="form-control" required>
                </div>
            </div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#guardianChildren');
            const addBtn    = wrapper.querySelector('#addGuardianChild');

            function addChild() {
                const row = document.createElement('div');
                row.className = 'row mb-3';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name:</label>
                    <div class="col-sm-9">
                        <input type="text" name="child_name[]" class="form-control" required>
                    </div>
                    <div class="col-sm-1">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child">DELETE</button>
                    </div>`;
                container.appendChild(row);
                row.querySelector('.remove-child').onclick = () => row.remove();
            }

            addBtn.onclick = addChild;
            addChild();  // initial

        } else if (type === 'solo parent') {
            wrapper.innerHTML = `
            <div id="soloChildren"></div>
            <button type="button" id="addSoloChild" class="btn btn-sm btn-outline-primary mb-3"> + Add Child </button>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Years as Solo Parent:</label>
                <div class="col-sm-10">
                <input type="number" name="years_solo_parent" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Claim Date:</label>
                <div class="col-sm-10">
                <input type="date" name="claim_date" class="form-control" required>
                </div>
            </div>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Purpose:</label>
                <div class="col-sm-10">
                <input type="text" name="purpose" class="form-control" required>
                </div>
            </div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#soloChildren');
            const addBtn    = wrapper.querySelector('#addSoloChild');
            function addChild() {
                const row = document.createElement('div');
                row.className = 'row mb-3';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold"> Child's Name:</label>
                    <div class="col-sm-4">
                        <input type="text" name="child_name[]" class="form-control" required>
                    </div>

                    <label class="col-sm-1 col-form-label fw-bold">Age:</label>
                    <div class="col-sm-1">
                        <input type="number" name="child_age[]" class="form-control" required>
                    </div>

                    <label class="col-sm-1 col-form-label fw-bold">Sex:</label>
                    <div class="col-sm-2">
                        <select name="child_sex[]" class="form-select" required>
                            <option value="">–</option>
                            <option>Male</option>
                            <option>Female</option>
                        </select>
                    </div>

                    <div class="col-sm-1">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child">DELETE</button>
                    </div>`;
                container.appendChild(row);
                row.querySelector('.remove-child').onclick = () => row.remove();
            }
            addBtn.onclick = addChild;
            addChild();  // initial
        }
    }

    // Main renderer for certificate-specific fields
    function renderCertFields(value, mode = forSelect.value) {
        const key = (value || '').trim().toLowerCase();
        const cfg = certConfigs[key] || [];
        certFieldsHolder.innerHTML = '';

        cfg.forEach(f => {
            // Determine initial value
            let val = '';
            if (mode === 'myself') {
                if (f.id === 'full_name')      val = invertName(currentUser.full_name);
                else if (f.id === 'age')       val = computeAge(currentUser.birthdate);
                else if (f.id === 'civil_status') val = currentUser.civil_status;
                else if (f.id === 'purok')     val = currentUser.purok;
            }

            const isReadOnly = mode === 'myself' && ['full_name','age','civil_status','purok'].includes(f.id);

            const row = document.createElement('div');
            row.className = 'row mb-3';

            let inner = `
            <label class="col-sm-2 col-form-label fw-bold">${f.label}:</label>
            <div class="col-sm-10">
            `;

            if (f.type === 'select') {
                let attrs = isReadOnly ? 'class="form-select select-readonly" readonly' : 'class="form-select"';
                inner += `<select id="${f.id}" name="${f.id}" ${attrs} required>
                    ${f.options.map(o => `<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('')}
                    </select>`;
            } else {
                inner += `<input type="${f.type}" id="${f.id}" name="${f.id}" class="form-control${isReadOnly ? ' bg-e9ecef' : ''}" value="${val}" ${isReadOnly ? 'readonly' : ''} required>`;
            }

            inner += `</div>`;
            row.innerHTML = inner;
            certFieldsHolder.appendChild(row);
        });

        // children or extra sections
        if (key === 'guardianship' || key === 'solo parent') {
            renderChildSections(key);
        }

        if (mode === 'other') {
            const authRow = document.createElement('div');
            authRow.className = 'row mb-3';
            authRow.innerHTML = `
                <label class="col-sm-2 col-form-label fw-bold">Authorization Letter:</label>
                <div class="col-sm-10">
                <input 
                    type="file" 
                    name="authorization_letter" 
                    class="form-control" 
                    accept="image/*,.pdf"
                    required
                >
                </div>`;
            certFieldsHolder.appendChild(authRow);
        }

        // After rendering:
        // If Indigency, remove/hide payment method & amount but KEEP payment status (show Free of Charge if none)
        if (key === 'indigency') {
            // clear method & amount
            if (hiddenPaymentInput) hiddenPaymentInput.value = '';
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';

            // ensure paymentStatus has a meaningful value for display (prefer existing server value)
            if (hiddenPaymentStatus) {
                if (!hiddenPaymentStatus.value || !hiddenPaymentStatus.value.trim()) {
                    // Use server-provided status if present, otherwise default to Free of Charge
                    hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
                }
            }

            // hide payment UI elements (content + progress step)
            const paymentContent = document.getElementById('paymentStep');
            if (paymentContent && paymentContent.style) paymentContent.style.display = 'none';
            const progressPayment = document.querySelector('.payment-progress-step');
            if (progressPayment && progressPayment.style) progressPayment.style.display = 'none';
            // hide fee boxes etc.
            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = 'none'; });

        } else {
            // non-indigency: ensure payment UI visible and default hidden fields if blank
            const paymentContent = document.getElementById('paymentStep');
            if (paymentContent && paymentContent.style && paymentContent.style.display === 'none') {
                paymentContent.style.display = '';
            }
            const progressPayment = document.querySelector('.payment-progress-step');
            if (progressPayment && progressPayment.style && progressPayment.style.display === 'none') {
                progressPayment.style.display = '';
            }

            // If there's a server-provided payment method/amount/status, preserve them
            if (window.existingPaymentMethod && (!hiddenPaymentInput.value || !hiddenPaymentInput.value.trim())) {
                hiddenPaymentInput.value = window.existingPaymentMethod;
            }
            if (window.existingPaymentAmount && (!hiddenPaymentAmount.value || !hiddenPaymentAmount.value.trim())) {
                hiddenPaymentAmount.value = String(window.existingPaymentAmount);
            }
            if (window.existingPaymentStatus && (!hiddenPaymentStatus.value || !hiddenPaymentStatus.value.trim())) {
                hiddenPaymentStatus.value = String(window.existingPaymentStatus);
            }

            // if still blank, set sensible defaults
            if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = 'Brgy Payment Device';
            if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
            if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
        }

        // refresh step collections after possible DOM changes
        refreshStepCollections();
        // update navigation UI to reflect any step changes
        updateNavigation();

        // ensure payment controls reflect any hidden values (activate button)
        setupPaymentControls();
    }

    function refreshFields() {
        renderCertFields(certInput.value, forSelect.value);
    }

    // initial render
    refreshFields();

    // wire events to re-render fields
    certInput.addEventListener('input', refreshFields);
    certInput.addEventListener('change', refreshFields);
    certInput.addEventListener('blur', refreshFields);
    forSelect.addEventListener('change', refreshFields);

    // Setup payment controls (if present)
    setupPaymentControls();

    // Next button behavior (now dynamic-aware)
    nextBtn.addEventListener('click', () => {
        refreshStepCollections();
        const tSteps = totalSteps();

        // validation for required inputs on current 'active-step'
        let isValid = true;

        // find active step element index (1-based)
        let activeIndex = currentStep;

        // Validate required inputs on active step
        const activeStepEl = steps[activeIndex - 1];
        if (activeStepEl) {
            activeStepEl.querySelectorAll("input[required], select[required]").forEach(field => {
                if (!field.value || !String(field.value).trim()) {
                    isValid = false;
                    field.classList.add("is-invalid");
                } else {
                    field.classList.remove("is-invalid");
                }
            });
        }

        // If payment step exists and we're on it, ensure a payment method is chosen
        const paymentIndex = getPaymentStepIndex();
        if (paymentIndex > 0 && currentStep === paymentIndex) {
            if (!hiddenPaymentInput.value) isValid = false;
        }

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // Determine summary step index dynamically
        const summaryIndex = getSummaryStepIndex();
        const submissionIndex = getSubmissionStepIndex();

        // If pressing Next when currently on the Summary step => show confirmation modal
        if (summaryIndex > 0 && currentStep === summaryIndex) {
            // before showing confirm, make sure hidden payment inputs are correct for Indigency
            if ((certInput.value || '').trim().toLowerCase() === 'indigency') {
                if (hiddenPaymentInput) hiddenPaymentInput.value = '';
                if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
                // keep hiddenPaymentStatus (we want to show payment_status for indigency)
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) {
                    hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
                }
            } else {
                // non-indigency ensure amount/status defaults
                if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
            }
            confirmationModal.show();
            return;
        }

        // If currently on the final step (submission screen), use Next to navigate away
        if (submissionIndex > 0 && currentStep === submissionIndex) {
            // replace Next behavior with redirect
            window.location.href = 'userPanel.php?page=userDashboard';
            return;
        }

        // Move forward one step (normal)
        // Guard against going beyond bounds
        if (currentStep < tSteps) {
            // mark current completed and move
            const prevIdx = currentStep - 1;
            if (circleSteps[prevIdx]) circleSteps[prevIdx].classList.add('completed');
            if (stepLabels[prevIdx]) stepLabels[prevIdx].classList.add('completed');
            if (steps[prevIdx]) steps[prevIdx].classList.remove('active-step');

            currentStep++;
            const newIdx = currentStep - 1;
            if (steps[newIdx]) steps[newIdx].classList.add('active-step');
            if (circleSteps[newIdx]) {
                circleSteps[newIdx].classList.add('active');
            }
            if (stepLabels[newIdx]) stepLabels[newIdx].classList.add('active');

            // Update progress bar
            const progressPercent = ((currentStep - 1) / Math.max(1, (tSteps - 1))) * 100;
            if (progressFill) progressFill.style.width = `${progressPercent}%`;

            // If stepping into summary, populate it
            if (getSummaryStepIndex() > 0 && currentStep === getSummaryStepIndex()) {
                populateSummary();
            }

            updateNavigation();
        }
    });

    // Back button
    backBtn.addEventListener('click', () => {
        refreshStepCollections();
        if (currentStep > 1) {
            const prevIdx = currentStep - 1;
            if (steps[prevIdx]) steps[prevIdx].classList.remove("active-step");
            if (circleSteps[prevIdx]) circleSteps[prevIdx].classList.remove('active');
            if (stepLabels[prevIdx]) stepLabels[prevIdx].classList.remove('active');

            // Un-complete the prior step
            const priorIdx = currentStep - 2;
            if (circleSteps[priorIdx]) circleSteps[priorIdx].classList.remove('completed');
            if (stepLabels[priorIdx]) stepLabels[priorIdx].classList.remove('completed');

            currentStep--;
            const newIdx = currentStep - 1;
            if (steps[newIdx]) steps[newIdx].classList.add("active-step");
            if (circleSteps[newIdx]) circleSteps[newIdx].classList.add('active');
            if (stepLabels[newIdx]) stepLabels[newIdx].classList.add('active');

            // Update progress bar
            const newPercent = ((currentStep - 1) / Math.max(1, (totalSteps() - 1))) * 100;
            if (progressFill) progressFill.style.width = `${newPercent}%`;

            updateNavigation();
        }
    });

    // Confirmation modal submit
    confirmSubmitBtn.addEventListener('click', () => {
        // ensure payment fields are correct for Indigency before submit
        if ((certInput.value || '').trim().toLowerCase() === 'indigency') {
            if (hiddenPaymentInput) hiddenPaymentInput.value = '';
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
            // keep hiddenPaymentStatus (user should see Free of Charge or server status)
            if (hiddenPaymentStatus && !hiddenPaymentStatus.value) {
                hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
            }
        } else {
            if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
            if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
        }
        document.getElementById("certForm").submit();
    });

    // updateNavigation uses the actual step-label text for header/subheader
    function updateNavigation() {
        refreshStepCollections();

        // protect against out-of-range currentStep
        if (currentStep < 1) currentStep = 1;
        if (currentStep > totalSteps()) currentStep = totalSteps();

        // hide/show back button
        backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        // derive label text if available
        const labelText = (stepLabels[currentStep - 1] && stepLabels[currentStep - 1].textContent.trim()) || '';

        // set headers based on labelText (friendly mapping)
        if (/application/i.test(labelText)) {
            mainHeader.textContent = "APPLICATION FORM";
            subHeader.textContent = "Select a type of certification and provide the necessary details to apply.";
            nextBtn.textContent = "NEXT >";
        } else if (/payment/i.test(labelText)) {
            mainHeader.textContent = "PAYMENT";
            subHeader.textContent = "Settle your payment for your certification.";
            nextBtn.textContent = "NEXT >";
        } else if (/review/i.test(labelText)) {
            mainHeader.textContent = "REVIEW and CONFIRMATION";
            subHeader.textContent = "Please review all your information before submitting.";
            nextBtn.textContent = "SUBMIT";
        } else if (/submission/i.test(labelText) || getSubmissionStepIndex() === currentStep) {
            // final submission screen
            // remove headers and hr only once (protect with existence checks)
            if (mainHeader && mainHeader.parentNode) mainHeader.remove();
            if (subHeader && subHeader.parentNode) subHeader.remove();
            const hr = document.getElementById('mainHr');
            if (hr && hr.parentNode) hr.remove();

            backBtn.style.visibility = 'hidden';
            // replace next button behavior to go to dashboard
            nextBtn.textContent = "Back to Home";
            // replace with a fresh listener to avoid duplicate handlers
            const newNext = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newNext, nextBtn);
            newNext.addEventListener('click', () => {
                window.location.href = 'userPanel.php?page=userDashboard';
            });
        } else {
            // fallback
            mainHeader.textContent = labelText || "APPLICATION";
            subHeader.textContent = "";
            nextBtn.textContent = "NEXT >";
        }
    }

    // populateSummary - updated: Indigency -> payment_status only; others -> amount + payment_status
    function populateSummary() {
        const type = (certInput.value || '').trim().toLowerCase();
        const container = document.getElementById('summaryContainer');

        const rows = [
            ['Type of Certification:', certInput.value || '—'],
            ['Requesting For:', forSelect.value === 'myself' ? 'Myself' : 'Others'],
            ['Full Name:', (document.querySelector('[name="full_name"]')?.value) || '—'],
            ['Age:', (document.querySelector('[name="age"]')?.value) || '—'],
            ['Civil Status:', (document.querySelector('[name="civil_status"]')?.value) || '—'],
            ['Purok:', (document.querySelector('[name="purok"]')?.value) || '—']
        ];

        // Solo Parent: Child details + years
        if (type === 'solo parent') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childAges  = Array.from(document.querySelectorAll('[name="child_age[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childSexes = Array.from(document.querySelectorAll('[name="child_sex[]"]')).map(el => el.value.trim()).filter(Boolean);

            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
                rows.push([`Child ${i + 1} Age:`, childAges[i] || '—']);
                rows.push([`Child ${i + 1} Sex:`, childSexes[i] || '—']);
            });

            const years = document.querySelector('[name="years_solo_parent"]')?.value || '—';
            rows.push(['Years as Solo Parent:', years]);
        }

        // Guardianship: Child names only
        if (type === 'guardianship') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
            });
        }

        // Residency specific field
        if (type === 'residency') {
            rows.push([
                'Years Residing:',
                document.querySelector('[name="residing_years"]')?.value || '—'
            ]);
        }

        // Common fields for all types
        rows.push(
            ['Claim Date:', document.querySelector('[name="claim_date"]')?.value || '—'],
            ['Purpose:', document.querySelector('[name="purpose"]')?.value || '—']
        );

        // Payment details — different rules for Indigency vs others
        const clientAmount = (hiddenPaymentAmount?.value || '').toString().trim();
        const clientStatus = (hiddenPaymentStatus?.value || '').toString().trim();
        const serverAmount = (window.existingPaymentAmount || '').toString().trim();
        const serverStatus = (window.existingPaymentStatus || '').toString().trim();

        const amountVal = clientAmount || serverAmount || '';
        const statusVal = clientStatus || serverStatus || '';

        if (type === 'indigency') {
            // show payment_status for indigency (use server/client value or friendly default)
            rows.push(['Payment Status:', statusVal || 'Free of Charge']);
        } else {
            // show amount and payment status for non-indigency
            let amtDisplay = '—';
            if (amountVal !== null && String(amountVal).trim() !== '') {
                const numeric = Number(String(amountVal).replace(/[^0-9.-]+/g, ''));
                if (!isNaN(numeric)) {
                    amtDisplay = '₱' + numeric.toFixed(2);
                } else {
                    amtDisplay = String(amountVal);
                }
            } else {
                amtDisplay = '₱' + Number(DEFAULT_AMOUNT).toFixed(2);
            }
            rows.push(['Amount:', amtDisplay]);
            rows.push(['Payment Status:', statusVal || 'Pending']);
        }

        // Build HTML
        let html = `
            <div class="row justify-content-center">
                <div class="col-md-10 col-lg-8 col-xl-6">
                    <div class="summary-container p-4 rounded shadow-sm border">
                        <ul class="list-group list-group-flush">
        `;

        rows.forEach(([label, value]) => {
            html += `
                <li class="list-group-item d-flex justify-content-between">
                    <span class="fw-bold">${label}</span>
                    <span class="text-success">${value}</span>
                </li>
            `;
        });

        html += `
                        </ul>
                    </div>
                </div>
            </div>
        `;

        container.innerHTML = html;
    }

    // react when user changes cert type (extra safety so JS also enforces Indigency mode)
    function onCertTypeChange() {
        const val = (certInput.value || '').trim().toLowerCase();
        if (val === 'indigency') {
            // clear payment hidden fields except status (we want to display a status)
            if (hiddenPaymentInput) hiddenPaymentInput.value = '';
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
            // set status to existing or 'Free of Charge'
            if (hiddenPaymentStatus) {
                if (!hiddenPaymentStatus.value || !hiddenPaymentStatus.value.trim()) {
                    hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
                }
            }

            // try to remove or hide payment step if present
            const pStep = document.getElementById('paymentStep');
            if (pStep && pStep.parentNode) {
                pStep.parentNode.removeChild(pStep);
            }
            const pProgress = document.querySelector('.payment-progress-step');
            if (pProgress && pProgress.parentNode) {
                pProgress.parentNode.removeChild(pProgress);
            }

            // hide fee boxes etc.
            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = 'none'; });

            // refresh collections and update navigation
            refreshStepCollections();
            if (currentStep > totalSteps()) currentStep = totalSteps();
            updateNavigation();

            // update summary
            populateSummary();
        } else {
            // non-indigency: ensure payment UI exists; reload if it was removed by previous action (simple and reliable)
            if (!document.getElementById('paymentStep')) {
                location.reload();
            } else {
                // ensure sensible defaults
                if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = (window.existingPaymentMethod || 'Brgy Payment Device');
                if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = (window.existingPaymentAmount || String(DEFAULT_AMOUNT));
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = (window.existingPaymentStatus || 'Pending');
            }

            refreshStepCollections();
            updateNavigation();
        }
    }

    certInput.addEventListener('change', onCertTypeChange);
    certInput.addEventListener('input', onCertTypeChange);

    // Final initial navigation update
    refreshStepCollections();
    updateNavigation();

    // If the page was loaded with an existing transaction, ensure hidden inputs reflect server values and populate summary if necessary
    (function initFromServer() {
        // server variables exposed: window.existingPaymentMethod, existingPaymentAmount, existingPaymentStatus, existingCertType
        if (window.existingPaymentMethod && hiddenPaymentInput && !hiddenPaymentInput.value) {
            hiddenPaymentInput.value = window.existingPaymentMethod;
        }
        if (window.existingPaymentAmount !== undefined && window.existingPaymentAmount !== null && hiddenPaymentAmount && !hiddenPaymentAmount.value) {
            hiddenPaymentAmount.value = String(window.existingPaymentAmount);
        }
        if (window.existingPaymentStatus !== undefined && window.existingPaymentStatus !== null && hiddenPaymentStatus && !hiddenPaymentStatus.value) {
            hiddenPaymentStatus.value = String(window.existingPaymentStatus);
        }
        // pre-select payment button if applicable
        setupPaymentControls();

        // if existing cert is indigency, trigger the indigency UI adjustments
        if ((window.existingCertType || '').toString().toLowerCase() === 'indigency') {
            certInput.value = 'Indigency';
            // render fields once so client-side parts are consistent
            renderCertFields('Indigency', forSelect.value);
        }

        // If initialStep indicates summary should show, populate it after a tiny delay
        if (Number(window.initialStep || 1) >= 3) {
            setTimeout(() => {
                populateSummary();
            }, 120);
        }
    })();
});
