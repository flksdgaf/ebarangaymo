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

    const currentUser = window.currentUser;
    const forSelect   = document.getElementById('forSelect');

    (function(){
        const options = [
        "Residency",
        "Indigency",
        "Good Moral",
        "Solo Parent",
        "Guardianship"
        ];
        const input = document.getElementById('certType');
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

    // ─── Dynamic fields depending on certification type ─────────────────────
    const certInput        = document.getElementById('certType');
    const certFieldsHolder = document.getElementById('certFields');

    // configuration for each cert type
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
        { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
        // { id: 'claim_date',      label: 'Claim Date',      type: 'date' }
        ],
        guardianship: [
        { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
        { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
        { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
        { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
        // { id: 'claim_date',      label: 'Claim Date',      type: 'date'   },
        // { id: 'purpose',         label: 'Purpose',         type: 'text'   }
        ]
    };

    function invertName(name) {
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

    // after your existing renderCertFields definition:
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
            //let idx = 0;

            function addChild() {
                //idx++;
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

    function renderCertFields(value, mode = forSelect.value) {
        const key = value.trim().toLowerCase();
        const cfg = certConfigs[key] || [];
        certFieldsHolder.innerHTML = '';

        cfg.forEach(f => {
            // Determine initial value
            let val = '';
            if (mode === 'myself') {
            if (f.id === 'full_name')              val = invertName(currentUser.full_name);
            else if (f.id === 'age')          val = computeAge(currentUser.birthdate);
            else if (f.id === 'civil_status') val = currentUser.civil_status;
            else if (f.id === 'purok')        val = currentUser.purok;
            }

            // Determine readonly only for those four in 'myself'
            const isReadOnly = mode === 'myself' && ['full_name','age','civil_status','purok'].includes(f.id);

            // Build the row
            const row = document.createElement('div');
            row.className = 'row mb-3';

            // Shared label
            let inner = `
            <label class="col-sm-2 col-form-label fw-bold">${f.label}:</label>
            <div class="col-sm-10">
            `;

            if (f.type === 'select') {
                let attrs = '';
                if (isReadOnly) {
                    attrs = 'class="form-select select-readonly"';  // no disabled
                } else {
                    attrs = 'class="form-select"';
                }
                inner += `<select
                    id="${f.id}"
                    name="${f.id}"
                    ${attrs}
                    required>
                    ${f.options.map(o => `
                        <option value="${o}" ${val===o?'selected':''}>${o}</option>`
                    ).join('')}
                    </select>`;
            } else {
            // Render an <input>
                inner += `<input
                    type="${f.type}"
                    id="${f.id}"
                    name="${f.id}"
                    class="form-control${isReadOnly ? ' bg-e9ecef' : ''}"
                    value="${val}"
                    ${isReadOnly ? 'readonly' : ''}
                    required
                    >`;
            }
            inner += `</div>`;

            row.innerHTML = inner;
            certFieldsHolder.appendChild(row);
        });

        // then at the end:
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
    }

    function refreshFields() {
        renderCertFields(certInput.value, forSelect.value);
    }

    // initial
    refreshFields();

     // wire it up:
    certInput.addEventListener('input', refreshFields);
    certInput.addEventListener('change', refreshFields);
    certInput.addEventListener('blur', refreshFields);
    forSelect.addEventListener('change', refreshFields);

    // certInput.addEventListener('input',  () => renderCertFields(certInput.value));
    // certInput.addEventListener('change', () => renderCertFields(certInput.value));
    // renderCertFields(certInput.value);
    // certInput.addEventListener('blur', () => renderCertFields(certInput.value));

    nextBtn.addEventListener('click', () => {
        let isValid = true;

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

        // If on step 2, ensure a payment method is chosen
        if (currentStep === 2 && !hiddenPaymentInput.value) {
            isValid = false;
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
        document.getElementById("certForm").submit();                   // ← ADDED
    });

    function updateNavigation() {
        backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === 1) {
            mainHeader.textContent = "APPLICATION FORM";
            subHeader.textContent = "Select a type of certification and provide the necessary details to apply.";
            nextBtn.textContent = "NEXT >";
        } else if (currentStep === 2) {
            mainHeader.textContent = "PAYMENT";
            subHeader.textContent = "Settle your payment for your certification.";
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
                window.location.href = 'userPanel.php?page=userDashboard';
            });
        }
    }

    function populateSummary() {
        const type = certInput.value.trim().toLowerCase();
        const container = document.getElementById('summaryContainer');

        const rows = [
            ['Type of Certification:', certInput.value || '—'],
            ['Requesting For:', forSelect.value === 'myself' ? 'Myself' : 'Others'],
            ['Full Name:', document.querySelector('[name="full_name"]').value || '—'],
            ['Age:', document.querySelector('[name="age"]').value || '—'],
            ['Civil Status:', document.querySelector('[name="civil_status"]').value || '—'],
            ['Purok:', document.querySelector('[name="purok"]').value || '—']
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
                document.querySelector('[name="residing_years"]').value || '—'
            ]);
        }

        // Common fields for all types
        rows.push(
            ['Claim Date:', document.querySelector('[name="claim_date"]').value || '—'],
            ['Purpose:', document.querySelector('[name="purpose"]').value || '—'],
            ['Payment Method:', hiddenPaymentInput.value || '—']
        );

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
