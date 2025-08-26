// js/serviceCertification.js (updated: renumber markers, server-side prefill)
document.addEventListener("DOMContentLoaded", function () {
    // --- preserved pieces from your original file ---
    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const progressFill = document.getElementById('progressFill');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');

    // payment controls
    const paymentButtons    = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    const hiddenPaymentInput = document.getElementById('paymentMethod');

    const confirmationModalEl = document.getElementById("confirmationModal");
    const confirmationModal = confirmationModalEl ? new bootstrap.Modal(confirmationModalEl) : null;
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    const certInput        = document.getElementById('certType');
    const certFieldsHolder = document.getElementById('certFields');
    const forSelect        = document.getElementById('forSelect');

    const currentUser = window.currentUser || {};
    const serverCertType = (window.serverCertType ?? '') || '';
    const serverChosenPayment = (window.serverChosenPayment ?? '') || '';

    // kept your certConfigs, helper functions and field rendering logic (unchanged)
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
        ],
        guardianship: [
        { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
        { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
        { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
        { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
        ]
    };

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

    // Child / solo parent render (kept exactly as before)
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

    function renderCertFields(value, mode = forSelect.value) {
        const key = (value || '').trim().toLowerCase();
        const cfg = certConfigs[key] || [];
        certFieldsHolder.innerHTML = '';

        cfg.forEach(f => {
            // Determine initial value
            let val = '';
            if (mode === 'myself') {
                if (f.id === 'full_name')              val = invertName(currentUser.full_name || '');
                else if (f.id === 'age')               val = computeAge(currentUser.birthdate || '');
                else if (f.id === 'civil_status')     val = currentUser.civil_status || '';
                else if (f.id === 'purok')            val = currentUser.purok || '';
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
                    attrs = 'class="form-select select-readonly"';
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

        // attach special child sections if needed
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

    // --- dropdown suggestion UI (kept from your original) ---
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
            if (!list) return;
            list.innerHTML = '';
            filtered.forEach(opt => {
                const li = document.createElement('li');
                li.textContent = opt;
                li.className   = 'list-group-item list-group-item-action py-1';
                li.style.cursor = 'pointer';
                li.addEventListener('mousedown', () => {
                    input.value = opt;
                    list.style.display = 'none';
                    // trigger update immediately
                    onCertTypeChanged();
                });
                list.appendChild(li);
            });
            list.style.display = filtered.length ? 'block' : 'none';
        }
        if (input) {
            input.addEventListener('focus',  () => rebuildList(options));
            input.addEventListener('input', () => {
                const v = input.value.trim().toLowerCase();
                rebuildList(v ? options.filter(o => o.toLowerCase().includes(v)) : options);
            });
            input.addEventListener('blur', () => setTimeout(()=> list && (list.style.display='none'),150));
            document.addEventListener('click', e => {
                if (!input.contains(e.target) && list && !list.contains(e.target)) list.style.display='none';
            });
        }
    })();

    // --- Step/Progress management (NEW) ---
    // All step content blocks: they have data-step attributes
    const allStepContents = Array.from(document.querySelectorAll('.step[data-step]'))
        .sort((a,b) => Number(a.dataset.step) - Number(b.dataset.step));

    // Markers: wrappers .steps[data-step]
    const stepMarkers = Array.from(document.querySelectorAll('.stepss .steps'));

    // helper to get a content element by original step number
    function contentByStep(n) {
        return allStepContents.find(s => Number(s.dataset.step) === Number(n));
    }

    function rebuildVisibleSteps() {
        // visible = not d-none
        const visible = allStepContents.filter(el => !el.classList.contains('d-none'));
        // sort by original order
        visible.sort((a,b) => Number(a.dataset.step) - Number(b.dataset.step));
        return visible;
    }

    // Renumber the visible markers' displayed numbers (the <span class="circle-num">)
    function renumberVisibleMarkers(visible) {
        // visible: array of content elements in order
        if (!visible) visible = rebuildVisibleSteps();
        // For each visible content get its original step and find the corresponding marker wrapper
        visible.forEach((contentEl, idx) => {
            const original = Number(contentEl.dataset.step);
            const marker = stepMarkers.find(m => Number(m.dataset.step) === original);
            if (!marker) return;
            // update displayed number inside marker
            const span = marker.querySelector('.circle .circle-num') || marker.querySelector('.circle span') || marker.querySelector('.circle');
            if (span) {
                span.textContent = String(idx + 1);
            }
        });
    }

    let visibleSteps = rebuildVisibleSteps();
    // index inside visibleSteps
    let visibleIndex = 0;

    // initialStep provided by PHP: 4 or 1
    const initialStepOriginal = Number(window.initialStep || 1);

    function showVisibleStep(index) {
        if (!visibleSteps.length) return;
        visibleIndex = Math.max(0, Math.min(index, visibleSteps.length - 1));

        // update content containers
        visibleSteps.forEach((el, i) => {
            el.classList.remove('active-step');
            el.classList.remove('completed');
            if (i < visibleIndex) el.classList.add('completed');
            if (i === visibleIndex) el.classList.add('active-step');
        });

        // update top markers: hide markers that are not present, and set classes for present ones
        stepMarkers.forEach(wrapper => {
            const stepNum = Number(wrapper.dataset.step);
            const existsIndex = visibleSteps.findIndex(s => Number(s.dataset.step) === stepNum);
            const circle = wrapper.querySelector('.circle');
            const label = wrapper.querySelector('.step-label');

            if (existsIndex === -1) {
                wrapper.classList.add('d-none');
            } else {
                wrapper.classList.remove('d-none');
                circle.classList.remove('active','completed');
                label.classList.remove('active','completed');

                if (existsIndex < visibleIndex) {
                    circle.classList.add('completed');
                    label.classList.add('completed');
                } else if (existsIndex === visibleIndex) {
                    circle.classList.add('active');
                    label.classList.add('active');
                }
            }
        });

        // RENumber the visible markers to be sequential (1..n) so Indigency shows 1-2-3
        renumberVisibleMarkers(visibleSteps);

        // progress fill
        const total = visibleSteps.length - 1;
        const percent = total <= 0 ? 100 : Math.round((visibleIndex / total) * 100);
        if (progressFill) progressFill.style.width = percent + '%';

        // navigation text & visibility
        backBtn.style.visibility = visibleIndex === 0 ? 'hidden' : 'visible';

        // determine current original step (1..4)
        const currentOriginal = Number(visibleSteps[visibleIndex].dataset.step);

        if (currentOriginal === 1) {
            if (mainHeader) mainHeader.textContent = "APPLICATION FORM";
            if (subHeader) subHeader.textContent = "Select a type of certification and provide the necessary details to apply.";
            if (nextBtn) nextBtn.textContent = "NEXT >";
        } else if (currentOriginal === 2) {
            if (mainHeader) mainHeader.textContent = "PAYMENT";
            if (subHeader) subHeader.textContent = "Settle your payment for your certification.";
            if (nextBtn) nextBtn.textContent = "NEXT >";
        } else if (currentOriginal === 3) {
            if (mainHeader) mainHeader.textContent = "REVIEW and CONFIRMATION";
            if (subHeader) subHeader.textContent = "Please review all your information before submitting.";
            if (nextBtn) nextBtn.textContent = "SUBMIT";
            // populate summary when we land on review
            populateSummary();
        } else if (currentOriginal === 4) {
            // submission screen
            if (mainHeader) mainHeader.remove();
            if (subHeader) subHeader.remove();
            const hr = document.getElementById('mainHr');
            if (hr) hr.remove();
            backBtn.style.visibility = 'hidden';
            if (nextBtn) nextBtn.textContent = "Back to Home";

            // make the Next button redirect to dashboard (replace handler once)
            const newNext = nextBtn.cloneNode(true);
            nextBtn.parentNode.replaceChild(newNext, nextBtn);
            newNext.addEventListener('click', () => {
                window.location.href = 'userPanel.php?page=userDashboard';
            });
        }
    }

    // call this when cert type changes
    function updateFlowBasedOnCertificate() {
        const cert = (certInput.value || '').trim().toLowerCase();
        const isIndigency = cert === 'indigency';

        const paymentContent = contentByStep(2);
        const paymentMarkerWrapper = document.querySelector('.stepss .steps[data-step="2"]');

        if (isIndigency) {
            if (paymentContent) paymentContent.classList.add('d-none');
            if (paymentMarkerWrapper) paymentMarkerWrapper.classList.add('d-none');
            if (hiddenPaymentInput) hiddenPaymentInput.value = 'FREE';
        } else {
            if (paymentContent) paymentContent.classList.remove('d-none');
            if (paymentMarkerWrapper) paymentMarkerWrapper.classList.remove('d-none');
            if (hiddenPaymentInput && (!hiddenPaymentInput.value || hiddenPaymentInput.value === 'FREE')) {
                hiddenPaymentInput.value = 'Brgy Payment Device';
            }
        }

        visibleSteps = rebuildVisibleSteps();

        // If server wants to show submission (initialStepOriginal), prefer that visible step index
        let startIdx = 0;
        const serverIdx = visibleSteps.findIndex(s => Number(s.dataset.step) === initialStepOriginal);
        startIdx = serverIdx !== -1 ? serverIdx : 0;

        // ensure visibleIndex not out of bounds
        if (visibleIndex >= visibleSteps.length) visibleIndex = visibleSteps.length - 1;

        // show the correct visible step (prefer server override)
        showVisibleStep(startIdx);
    }

    // --- navigation handlers ---
    // Next button
    nextBtn.addEventListener('click', () => {
        // current visible content element
        const currentEl = visibleSteps[visibleIndex];
        const currentOriginal = Number(currentEl.dataset.step);

        // validation for required fields within the current visible step
        let isValid = true;
        const requiredFields = currentEl.querySelectorAll('[required]');
        requiredFields.forEach(field => {
            if ((field.type === 'checkbox' || field.type === 'radio')) {
                if (!field.checked) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            } else {
                if (!field.value || (typeof field.value === 'string' && field.value.trim() === '')) {
                    isValid = false;
                    field.classList.add('is-invalid');
                } else {
                    field.classList.remove('is-invalid');
                }
            }
        });

        // Additional: if currentOriginal === 2 (payment) ensure a method chosen (unless it's hidden)
        if (currentOriginal === 2 && (!hiddenPaymentInput.value || hiddenPaymentInput.value === '')) {
            isValid = false;
        }

        if (!isValid) {
            const validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // If this is the last visible step
        if (visibleIndex === visibleSteps.length - 1) {
            // If currentOriginal is 3 (review) -> show confirmation modal (submit on confirm)
            if (currentOriginal === 3) {
                if (confirmationModal) confirmationModal.show();
                return;
            }
            // If currentOriginal is 4 (submission screen), redirection already wired in showVisibleStep
            return;
        }

        // Move forward to the next visible step
        // mark current as completed and advance
        currentEl.classList.remove('active-step');
        currentEl.classList.add('completed');

        visibleIndex++;
        showVisibleStep(visibleIndex);
    });

    // Back button
    backBtn.addEventListener('click', () => {
        if (visibleIndex === 0) return;
        // Un-complete the previous marker and go back
        const currentEl = visibleSteps[visibleIndex];
        currentEl.classList.remove('active-step');

        // reduce visibleIndex then set classes
        visibleIndex--;
        showVisibleStep(visibleIndex);
    });

    // confirm submit
    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', () => {
            // When submitting, if cert is indigency ensure hiddenPaymentInput is 'FREE'
            const cert = (certInput.value || '').trim().toLowerCase();
            if (cert === 'indigency' && hiddenPaymentInput) hiddenPaymentInput.value = 'FREE';
            document.getElementById("certForm").submit();
        });
    }

    // --- Setup payment control UI (kept your original logic) ---
    function setupPaymentControls() {
        paymentButtons.forEach(btn => {
            btn.addEventListener('click', () => {
                if (btn.classList.contains('disabled')) return;
                paymentButtons.forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                const method = btn.dataset.method;
                hiddenPaymentInput.value = method;

                instructionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.method !== method);
                });
            });
        });
    }
    setupPaymentControls();

    // --- Summary population (adapted: omit payment for Indigency) ---
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

        // Common fields for all types (Claim Date & Purpose)
        rows.push(
            ['Claim Date:', document.querySelector('[name="claim_date"]')?.value || '—'],
            ['Purpose:', document.querySelector('[name="purpose"]')?.value || '—']
        );

        // Payment Method: include only if not indigency AND payment step is visible
        const isIndigency = type === 'indigency';
        const paymentContentVisible = !contentByStep(2)?.classList.contains('d-none');
        if (!isIndigency && paymentContentVisible) {
            rows.push(['Payment Method:', hiddenPaymentInput.value || '—']);
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

    // --- initial rendering & hooks ---
    // initial form fields
    refreshFields();

    // Update flow whenever certType changes, and when forSelect changes (myself/other)
    function onCertTypeChanged() {
        refreshFields();
        updateFlowBasedOnCertificate();
    }
    certInput.addEventListener('input', onCertTypeChanged);
    certInput.addEventListener('change', onCertTypeChanged);
    certInput.addEventListener('blur', onCertTypeChanged);
    forSelect.addEventListener('change', refreshFields);

    // run initial flow update (and set starting visible step considering server initial)
    visibleSteps = rebuildVisibleSteps();

    // If server provided cert type (page reloaded after submit), prefill it so flow updates correctly
    if (serverCertType) {
        certInput.value = serverCertType;
    }
    if (serverChosenPayment && hiddenPaymentInput) {
        hiddenPaymentInput.value = serverChosenPayment;
        // also set corresponding payment button active and instruction
        paymentButtons.forEach(b => b.classList.toggle('active', b.dataset.method === serverChosenPayment));
        instructionPanels.forEach(panel => panel.classList.toggle('d-none', panel.dataset.method !== serverChosenPayment));
    }

    // find index of original initialStep (php-provided)
    let startIdx = visibleSteps.findIndex(s => Number(s.dataset.step) === initialStepOriginal);
    if (startIdx === -1) startIdx = 0;

    // make sure flow respects certificate type (this will also renumber markers)
    updateFlowBasedOnCertificate();

    // Show correct visible step (server override handled in updateFlowBasedOnCertificate())
    visibleSteps = rebuildVisibleSteps();
    // if server wanted submission screen, we already applied it; show appropriate index
    startIdx = visibleSteps.findIndex(s => Number(s.dataset.step) === initialStepOriginal);
    if (startIdx === -1) startIdx = 0;
    showVisibleStep(startIdx);

}); // DOMContentLoaded end
