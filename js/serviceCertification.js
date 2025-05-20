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
        { id: 'name',            label: 'Name',            type: 'text',     disabled: true },
        { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
        { id: 'civil_status',    label: 'Civil Status',    type: 'text',     disabled: true },
        { id: 'purok',           label: 'Purok',           type: 'text',     disabled: true },
        { id: 'years_residing',  label: 'Years Residing',  type: 'number' },
        { id: 'claim_date',      label: 'Claim Date',      type: 'date' },
        { id: 'purpose',         label: 'Purpose',         type: 'text' }
        ],
        indigency: [
        { id: 'name',         label: 'Name',         type: 'text',   disabled: true },
        { id: 'age',          label: 'Age',          type: 'number' },
        { id: 'civil_status', label: 'Civil Status', type: 'select', options: ['Single','Married','Separated','Widowed'] },
        { id: 'purok',        label: 'Purok',        type: 'text' },
        { id: 'claim_date',   label: 'Claim Date',   type: 'date' },
        { id: 'purpose',      label: 'Purpose',      type: 'text' }
        ],
        'good moral': [
        { id: 'name',        label: 'Name',         type: 'text',   disabled: true },
        { id: 'age',         label: 'Age',          type: 'number' },
        { id: 'civil_status',label: 'Civil Status', type: 'select', options: ['Single','Married','Separated','Widowed'] },
        { id: 'purok',       label: 'Purok',        type: 'text' },
        { id: 'purpose',     label: 'Purpose',      type: 'text' },
        { id: 'claim_date',  label: 'Claim Date',   type: 'date' }
        ],
        'solo parent': [
        { id: 'name',  label: 'Name', type: 'text', disabled: true },
        { id: 'age',   label: 'Age',  type: 'number' },
        { id: 'purok', label: 'Purok', type: 'text' },
        { id: 'claim_date', label: 'Claim Date', type: 'date' }
        ],
        guardianship: [
        { id: 'name',  label: 'Name', type: 'text', disabled: true },
        { id: 'age',   label: 'Age',  type: 'number' },
        { id: 'purok', label: 'Purok', type: 'text' },
        { id: 'claim_date', label: 'Claim Date', type: 'date' }
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

    
    function renderCertFields(value) {
        const key = value.trim().toLowerCase();
        const cfg = certConfigs[key] || [];
        certFieldsHolder.innerHTML = '';

        cfg.forEach(f => {
            // determine the value & disabled-state
            let val = '';
            let isDisabled = !!f.disabled;

            if (f.id === 'name') {
            const raw = currentUser.full_name || '';
            val = invertName(raw);
            }
            else if (f.id === 'age') {
            val = computeAge(currentUser.birthdate);
            }
            else if (f.id === 'civil_status') {
            val = currentUser.civil_status || '';
            }
            else if (f.id === 'purok') {
            val = currentUser.purok || '';
            }

            // build each row
            const row = document.createElement('div');
            row.className = 'row mb-3';

            let innerHtml;
            if (f.type === 'select') {
            // selects remain disabled initially
            innerHtml = `
                <label class="col-sm-2 col-form-label fw-bold">${f.label}:</label>
                <div class="col-sm-10">
                <select id="${f.id}" name="${f.id}" class="form-select" ${isDisabled ? 'disabled' : ''}>
                    ${f.options.map(o=>`<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('')}
                </select>
                </div>
            `;
            } else {
            // use readonly for inputs instead of disabled
            innerHtml = `
                <label class="col-sm-2 col-form-label fw-bold">${f.label}:</label>
                <div class="col-sm-10">
                <input
                    type="${f.type}"
                    id="${f.id}"
                    name="${f.id}"
                    class="form-control"
                    value="${val}"
                    ${isDisabled ? 'readonly' : ''}
                    required
                >
                </div>
            `;
            }

            row.innerHTML = innerHtml;
            certFieldsHolder.appendChild(row);
        });
    }

     // wire it up:
    certInput.addEventListener('input',  () => renderCertFields(certInput.value));
    certInput.addEventListener('change', () => renderCertFields(certInput.value));
    renderCertFields(certInput.value);
    certInput.addEventListener('blur', () => renderCertFields(certInput.value));

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
        const container = document.getElementById('summaryContainer');
        container.innerHTML = '';

        // wrap everything in a Bootstrap container
        let html = '<div class="container-fluid"><div class="row">';

        // helper to append a label/value pair
        const appendPair = (label, value) => {
            html += `
            <div class="col-sm-3 mb-2"><strong>${label}</strong></div>
            <div class="col-sm-9 mb-2">${value}</div>
            `;
        };

        // 1) Certification type
        appendPair('Type of Certification:', certInput.value.trim() || '—');

        // 2) All the fields you rendered under #certFields
        certFieldsHolder.querySelectorAll('.row').forEach(row => {
            const label   = row.querySelector('label').textContent;
            const control = row.querySelector('input, select');
            let value     = control ? control.value.trim() : '';
            if (!value) value = '—';
            appendPair(label, value);
        });

        // 3) Payment Method
        appendPair('Payment Method:', hiddenPaymentInput.value || '—');

        html += '</div></div>';
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
