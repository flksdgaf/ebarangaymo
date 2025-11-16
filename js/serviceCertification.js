document.addEventListener("DOMContentLoaded", function () {
    const pendingCert = sessionStorage.getItem('pendingCertType');
    if (pendingCert) {
        sessionStorage.removeItem('pendingCertType');
        setTimeout(() => {
            const certInput = document.getElementById('certType');
            if (certInput) {
                certInput.value = pendingCert;
                certInput.dispatchEvent(new Event('change', { bubbles: true }));
            }
        }, 100);
    }
    let steps = document.querySelectorAll(".step");
    let circleSteps = document.querySelectorAll('.circle');
    let stepLabels = document.querySelectorAll('.step-label');

    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const progressFill = document.getElementById('progressFill');
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');

    const paymentButtons = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    let hiddenPaymentInput = document.getElementById('paymentMethod');
    let hiddenPaymentAmount = document.getElementById('paymentAmount');
    let hiddenPaymentStatus = document.getElementById('paymentStatus');

    let hiddenClaimDate = document.querySelector('input[name="claim_date"]');
    let hiddenClaimTime = document.querySelector('input[name="claim_time"]');

    const confirmationModalEl = document.getElementById("confirmationModal");
    const confirmationModal = confirmationModalEl ? new bootstrap.Modal(confirmationModalEl) : null;
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    let currentStep = window.initialStep || 1;

    const currentUser = window.currentUser || {};
    const forSelect = document.getElementById('forSelect');
    const certInput = document.getElementById('certType');
    const certFieldsHolder = document.getElementById('certFields');

    const DEFAULT_AMOUNT = 130;

    function refreshStepCollections() {
        steps = document.querySelectorAll(".step");
        circleSteps = document.querySelectorAll('.circle');
        stepLabels = document.querySelectorAll('.step-label');
    }

    function renumberSteps() {
        refreshStepCollections();
        circleSteps.forEach((circle, index) => {
            circle.textContent = index + 1;
        });
    }

    function ensureHiddenPaymentFields() {
        const form = document.getElementById('certForm');
        if (!form) return;
        if (!hiddenPaymentInput) {
            hiddenPaymentInput = document.createElement('input');
            hiddenPaymentInput.type = 'hidden';
            hiddenPaymentInput.name = 'paymentMethod';
            hiddenPaymentInput.id = 'paymentMethod';
            form.appendChild(hiddenPaymentInput);
        }
        if (!hiddenPaymentAmount) {
            hiddenPaymentAmount = document.createElement('input');
            hiddenPaymentAmount.type = 'hidden';
            hiddenPaymentAmount.name = 'paymentAmount';
            hiddenPaymentAmount.id = 'paymentAmount';
            form.appendChild(hiddenPaymentAmount);
        }
        if (!hiddenPaymentStatus) {
            hiddenPaymentStatus = document.createElement('input');
            hiddenPaymentStatus.type = 'hidden';
            hiddenPaymentStatus.name = 'paymentStatus';
            hiddenPaymentStatus.id = 'paymentStatus';
            form.appendChild(hiddenPaymentStatus);
        }
    }

    function getStepIndexByElementId(id) {
        refreshStepCollections();
        for (let i = 0; i < steps.length; i++) {
            if (steps[i].id === id) return i + 1;
        }
        return -1;
    }
    function isPaymentStepPresent() { return document.getElementById('paymentStep') !== null; }
    function getPaymentStepIndex() { return getStepIndexByElementId('paymentStep'); }
    function getSummaryStepIndex() { return getStepIndexByElementId('summaryStep'); }
    function getSubmissionStepIndex() { return getStepIndexByElementId('submissionStep'); }

    function totalSteps() {
        refreshStepCollections();
        return circleSteps.length;
    }

    function computeProgressPercent(step, stepsCount) {
        stepsCount = Number(stepsCount) || 1;
        step = Number(step) || 1;
        const denom = Math.max(1, stepsCount - 1);
        return Math.round(((step - 1) / denom) * 100);
    }

    const setupPaymentControls = function () {
        ensureHiddenPaymentFields();
        if (!paymentButtons || paymentButtons.length === 0) return;

        const serverMethod = (window.existingPaymentMethod || '').trim();
        const clientMethod = (hiddenPaymentInput?.value || '').trim();
        const initialMethod = clientMethod || serverMethod || 'Brgy Payment Device';

        paymentButtons.forEach(b => {
            if (b.dataset.method === initialMethod) b.classList.add('active');
            b.addEventListener('click', () => {
                paymentButtons.forEach(x => x.classList.remove('active'));
                b.classList.add('active');
                const method = b.dataset.method;
                if (hiddenPaymentInput) hiddenPaymentInput.value = method || '';
                if (hiddenPaymentAmount) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
                if (hiddenPaymentStatus) hiddenPaymentStatus.value = 'Pending';
                instructionPanels.forEach(panel => {
                    panel.classList.toggle('d-none', panel.dataset.method !== method);
                });
            });
        });

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

    (function(){
        const options = [
            "Residency",
            "Indigency",
            "Good Moral",
            "Solo Parent",
            "Guardianship",
            "First Time Job Seeker"
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

    function getNextBusinessDays(count = 3) {
        const out = [];
        const now = new Date(); now.setHours(0,0,0,0);
        let cursor = new Date(now);
        const weekday = cursor.getDay();
        if (weekday === 6) cursor.setDate(cursor.getDate() + 2);
        else if (weekday === 0) cursor.setDate(cursor.getDate() + 1);
        else cursor.setDate(cursor.getDate() + 1);

        while (out.length < count) {
            const d = new Date(cursor);
            const dow = d.getDay();
            if (dow !== 0 && dow !== 6) {
                const y = d.getFullYear();
                const m = String(d.getMonth()+1).padStart(2,'0');
                const dd = String(d.getDate()).padStart(2,'0');
                out.push({ date: `${y}-${m}-${dd}`, label: d.toLocaleDateString(undefined, { year:'numeric', month:'long', day:'numeric' }) });
            }
            cursor.setDate(cursor.getDate() + 1);
        }
        return out;
    }

    function ensureHiddenClaimFields() {
        if (!hiddenClaimDate) {
            hiddenClaimDate = document.createElement('input');
            hiddenClaimDate.type = 'hidden';
            hiddenClaimDate.name = 'claim_date';
            hiddenClaimDate.id = 'hiddenClaimDate';
            document.getElementById('certForm')?.appendChild(hiddenClaimDate);
        }
        if (!hiddenClaimTime) {
            hiddenClaimTime = document.createElement('input');
            hiddenClaimTime.type = 'hidden';
            hiddenClaimTime.name = 'claim_time';
            hiddenClaimTime.id = 'hiddenClaimTime';
            document.getElementById('certForm')?.appendChild(hiddenClaimTime);
        }
    }

    function isValidClaimSeparate(date, part) {
        if (!date || !/^\d{4}-\d{2}-\d{2}$/.test(date)) return false;
        if (!['Morning','Afternoon'].includes(part)) return false;
        const allowed = getNextBusinessDays(3).map(x => x.date);
        return allowed.includes(date);
    }

    function buildClaimOptionsInto(container, existingDate=null, existingPart=null) {
        if (!container) return;
        ensureHiddenClaimFields();
        const existing = container.querySelector('.claim-list');
        if (existing) existing.remove();

        const claimList = document.createElement('div');
        claimList.className = 'claim-list claim-grid';
        const opts = getNextBusinessDays(3);

        opts.forEach((co, idx) => {
            const date = co.date;
            const label = co.label;

            const idMorning = `claim_${idx}_morning_${Math.random().toString(36).slice(2,8)}`;
            const valMorning = `${date}|Morning`;
            const checkedMorning = (existingDate === date && existingPart === 'Morning') || (idx === 0 && !existingDate && !existingPart) ? 'checked' : '';

            const colM = document.createElement('div');
            colM.className = 'col-sm-6 mb-2';
            colM.innerHTML = `
                <label class="list-group-item list-group-item-action p-2 claim-card ${checkedMorning ? 'active' : ''}" for="${idMorning}" role="option" aria-pressed="${checkedMorning ? 'true' : 'false'}">
                    <div class="form-check me-2">
                        <input class="form-check-input" type="radio" name="claim_slot" id="${idMorning}"
                               value="${valMorning}" data-date="${date}" data-part="Morning" ${checkedMorning} ${idx === 0 ? 'required' : ''}>
                    </div>
                    <div>
                        <div class="claim-date-label">${label}</div>
                        <div class="claim-time">Morning (8:00 AM to 12:00 NN)</div>
                    </div>
                </label>
            `;

            const idAfternoon = `claim_${idx}_afternoon_${Math.random().toString(36).slice(2,8)}`;
            const valAfternoon = `${date}|Afternoon`;
            const checkedAfternoon = (existingDate === date && existingPart === 'Afternoon') ? 'checked' : '';

            const colA = document.createElement('div');
            colA.className = 'col-sm-6 mb-2';
            colA.innerHTML = `
                <label class="list-group-item list-group-item-action p-2 claim-card ${checkedAfternoon ? 'active' : ''}" for="${idAfternoon}" role="option" aria-pressed="${checkedAfternoon ? 'true' : 'false'}">
                    <div class="form-check me-2">
                        <input class="form-check-input" type="radio" name="claim_slot" id="${idAfternoon}"
                               value="${valAfternoon}" data-date="${date}" data-part="Afternoon" ${checkedAfternoon} ${idx === 0 ? 'required' : ''}>
                    </div>
                    <div>
                        <div class="claim-date-label">${label}</div>
                        <div class="claim-time">Afternoon (1:00 PM to 5:00 PM)</div>
                    </div>
                </label>
            `;

            const rowWrap = document.createElement('div');
            rowWrap.className = 'row gx-2';
            rowWrap.appendChild(colM);
            rowWrap.appendChild(colA);
            claimList.appendChild(rowWrap);
        });

        if (window.showWeekendNote) {
            const noteDiv = document.createElement('div');
            noteDiv.className = 'alert alert-info mt-3';
            noteDiv.style.cssText = 'background-color: #d1ecf1; border: 1px solid #bee5eb; border-radius: 8px; padding: 1rem;';
            noteDiv.innerHTML = `
                <strong>Note:</strong> You are submitting this request on a weekend. Available claim slots start on Monday (${window.nextMondayLabel || 'next business day'}) during barangay hours (Mon–Fri, 8:00 AM – 5:00 PM).
            `;
            claimList.appendChild(noteDiv);
        }

        container.appendChild(claimList);

        claimList.querySelectorAll('label').forEach(lbl => {
            lbl.addEventListener('click', function () {
                const input = lbl.querySelector('input[type="radio"]');
                if (!input) return;
                if (!input.checked) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
            lbl.addEventListener('keydown', function (ev) {
                if (ev.key === ' ' || ev.key === 'Enter') {
                    ev.preventDefault();
                    const input = lbl.querySelector('input[type="radio"]');
                    if (input) {
                        input.checked = true;
                        input.dispatchEvent(new Event('change', { bubbles: true }));
                        input.focus();
                    }
                }
            });
        });

        claimList.addEventListener('change', function () {
            claimList.querySelectorAll('.claim-card').forEach(c => c.classList.remove('active'));
            const checked = claimList.querySelector('input[type="radio"]:checked');
            if (!checked) return;
            const parentLabel = checked.closest('label');
            if (parentLabel) parentLabel.classList.add('active');

            const d = checked.dataset.date || '';
            const p = checked.dataset.part || '';
            if (hiddenClaimDate) hiddenClaimDate.value = d;
            if (hiddenClaimTime) hiddenClaimTime.value = p;

            const summaryEl = document.getElementById('summaryClaimDate');
            if (summaryEl) {
                let friendly = d;
                const opt = getNextBusinessDays(3).find(x => x.date === d);
                if (opt && opt.label) friendly = opt.label;
                const partLabel = p === 'Morning' ? 'Morning (8:00 AM to 12:00 NN)' : 'Afternoon (1:00 PM to 5:00 PM)';
                summaryEl.textContent = friendly + ' - ' + partLabel;
            }
        });

        // Pre-select existing claim logic (prefer server-provided)
        if (window._existingClaimObj && window._existingClaimObj.date) {
            const d = window._existingClaimObj.date;
            const p = window._existingClaimObj.part;
            // Try to find new-style radio first
            const desiredNew = claimList.querySelector(`input[name="claim_slot"][data-date="${d}"][data-part="${p}"]`);
            if (desiredNew) {
                desiredNew.checked = true;
                desiredNew.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                // Fallback: match by date only (prefer morning)
                const dateMatchNew = claimList.querySelector(`input[name="claim_slot"][data-date="${d}"]`);
                if (dateMatchNew) {
                    dateMatchNew.checked = true;
                    dateMatchNew.dispatchEvent(new Event('change', { bubbles: true }));
                }
            }
        } else {
            // DEFAULT SELECTION: Always select first radio (Morning of first available date)
            const firstRadio = claimList.querySelector('input[name="claim_slot"]');
            if (firstRadio) {
                firstRadio.checked = true;
                // Trigger change event to update UI and hidden fields
                firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
                // Also ensure the card shows as active
                const parentLabel = firstRadio.closest('label');
                if (parentLabel) {
                    parentLabel.classList.add('active');
                    parentLabel.setAttribute('aria-pressed', 'true');
                }
            }
        }
    }

    const certConfigs = {
        residency: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'residing_years',  label: 'Years Residing',  type: 'number' },
            { id: 'purpose',         label: 'Purpose',         type: 'purpose_select' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim'   }
        ],
        indigency: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'purpose_select' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim' }
        ],
        'first time job seeker': [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',  type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'sex',             label: 'Sex',             type: 'select',   options: ['Male','Female']     },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim' }
        ],
        'good moral': [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'purpose_select' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim' }
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
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     }
        ]
    };

    function renderChildSections(type) {
        const wrapper = document.createElement('div');
        wrapper.id = 'childSection';

        if (type === 'guardianship') {
            wrapper.innerHTML += `
            <div id="guardianChildren"></div>

            <div id="guardianClaimContainer" class="row mb-3">
                <label class="col-sm-2 fw-bold">Claim Date:</label>
                <div class="col-sm-10" id="guardianClaimHolder"></div>
            </div>

            <div class="row mb-3" id="purposeContainer_guardianship"></div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#guardianChildren');

            function addChild(prefillData = null) {
                const row = document.createElement('div');
                row.className = 'row mb-3 child-row';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name</label>
                    <div class="col-sm-3">
                        <input type="text" name="child_name[]" class="form-control" 
                            value="${prefillData?.name || ''}" required>
                    </div>
                    
                    <label class="col-form-label fw-bold" style="width: 250px;">Relationship to Guardian</label>
                    <div class="col-sm-3">
                        <select name="child_relationship[]" class="form-select" required>
                            <option value="">Select Relationship</option>
                            <option value="Mother" ${prefillData?.relationship === 'Mother' ? 'selected' : ''}>Mother</option>
                            <option value="Father" ${prefillData?.relationship === 'Father' ? 'selected' : ''}>Father</option>
                            <option value="Uncle" ${prefillData?.relationship === 'Uncle' ? 'selected' : ''}>Uncle</option>
                            <option value="Aunt" ${prefillData?.relationship === 'Aunt' ? 'selected' : ''}>Aunt</option>
                            <option value="Legal Guardian" ${prefillData?.relationship === 'Legal Guardian' ? 'selected' : ''}>Legal Guardian</option>
                        </select>
                    </div>

                    <div class="col d-flex gap-2 justify-content-end align-items-center p-0" style="max-width: 100px;">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; padding: 0;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">delete</span>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm add-child d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; padding: 0;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">add</span>
                        </button>
                    </div>`;
                container.appendChild(row);
                
                // Update button visibility
                updateChildButtons();
                
                row.querySelector('.remove-child').onclick = () => {
                    row.remove();
                    updateChildButtons();
                };
                
                row.querySelector('.add-child').onclick = () => addChild();
            }
            
            function updateChildButtons() {
                const allRows = container.querySelectorAll('.child-row');
                allRows.forEach((row, index) => {
                    const deleteBtn = row.querySelector('.remove-child');
                    const addBtn = row.querySelector('.add-child');
                    
                    // Disable delete button if only one row exists, enable otherwise
                    if (allRows.length === 1) {
                        deleteBtn.disabled = true;
                    } else {
                        deleteBtn.disabled = false;
                    }
                    
                    // Only show add button on last row
                    if (index === allRows.length - 1) {
                        addBtn.style.display = '';
                    } else {
                        addBtn.style.display = 'none';
                    }
                });
            }

            // Parse and populate existing children data if available
            if (window.existingGuardianshipData && window.existingGuardianshipData.trim()) {
                try {
                    const childrenArray = JSON.parse(window.existingGuardianshipData);
                    if (Array.isArray(childrenArray) && childrenArray.length > 0) {
                        childrenArray.forEach(child => addChild(child));
                    } else {
                        addChild(); // Add one empty row if parsing succeeds but array is empty
                    }
                } catch (e) {
                    addChild(); // Add one empty row if parsing fails
                }
            } else {
                addChild(); // Add one empty row for new form
            }

            buildClaimOptionsInto(wrapper.querySelector('#guardianClaimHolder'));
        }

        // ========== INSERT SOLO PARENT CODE HERE (START) ==========
        if (type === 'solo parent') {
            wrapper.innerHTML += `
            <div class="row mb-3" id="soloParentInfoRow">
                <label class="col-sm-2 col-form-label fw-bold">Parent Sex:</label>
                <div class="col-sm-4">
                    <select name="parent_sex" class="form-select" required>
                        <option value="">Select Parent Sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
                
                <label class="col-sm-3 col-form-label fw-bold">Years as Solo Parent:</label>
                <div class="col-sm-3">
                    <input type="number" name="years_solo_parent" class="form-control" min="1" step="0.1" required>
                </div>
            </div>

            <div id="soloParentChildren"></div>

            <div id="soloClaimContainer" class="row mb-3">
                <label class="col-sm-2 fw-bold">Claim Date:</label>
                <div class="col-sm-10" id="soloClaimHolder"></div>
            </div>

            <div class="row mb-3" id="purposeContainer_solo_parent"></div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#soloParentChildren');

            function addSoloChild(prefillData = null) {
                const row = document.createElement('div');
                row.className = 'row mb-2 child-row';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name</label>
                    <div class="col-sm-3">
                        <input type="text" name="child_name[]" class="form-control" 
                            value="${prefillData?.name || ''}" required>
                    </div>
                    
                    <label class="col-form-label fw-bold text-end" style="width: 60px;">Sex</label>
                    <div class="col-sm-2">
                        <select name="child_sex[]" class="form-select" required>
                            <option value="">Select Sex</option>
                            <option value="Male" ${prefillData?.sex === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${prefillData?.sex === 'Female' ? 'selected' : ''}>Female</option>
                        </select>
                    </div>

                    <label class="col-form-label fw-bold text-end" style="width: 110px;">Birthdate</label>
                    <div class="col-sm-2">
                        <input type="date" name="child_birthdate[]" class="form-control" 
                            value="${prefillData?.birthdate || ''}" required>
                    </div>

                    <div class="col-auto d-flex gap-2 align-items-center">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; padding: 0;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">delete</span>
                        </button>
                        <button type="button" class="btn btn-outline-success btn-sm add-child d-flex align-items-center justify-content-center" style="width: 38px; height: 38px; padding: 0;">
                            <span class="material-symbols-outlined" style="font-size: 20px;">add</span>
                        </button>
                    </div>`;
                    container.appendChild(row);

                    row.querySelector('.remove-child').onclick = () => {
                        row.remove();
                        updateChildButtons();
                    };

                    row.querySelector('.add-child').onclick = () => addSoloChild();

                    updateChildButtons();
            }
            
            function updateChildButtons() {
                const allRows = container.querySelectorAll('.child-row');
                allRows.forEach((row, index) => {
                    const deleteBtn = row.querySelector('.remove-child');
                    const addBtn = row.querySelector('.add-child');
                    
                    // Disable delete button if only one row exists, enable otherwise
                    if (allRows.length === 1) {
                        deleteBtn.disabled = true;
                    } else {
                        deleteBtn.disabled = false;
                    }
                    
                    // Only show add button on last row
                    if (index === allRows.length - 1) {
                        addBtn.style.display = '';
                    } else {
                        addBtn.style.display = 'none';
                    }
                });
            }

            // Parse and populate existing children data if available
            if (window.existingChildrenData && window.existingChildrenData.trim() && window.existingChildrenData !== 'null') {
                try {
                    const childrenArray = JSON.parse(window.existingChildrenData);
                    if (Array.isArray(childrenArray) && childrenArray.length > 0) {
                        childrenArray.forEach(child => addSoloChild(child));
                    } else {
                        addSoloChild();
                    }
                } catch (e) {
                    addSoloChild();
                }
            } else {
                addSoloChild();
            }

            // CRITICAL: Ensure button visibility is updated after all children are added
            setTimeout(() => {
                updateChildButtons();
            }, 50);

            buildClaimOptionsInto(wrapper.querySelector('#soloClaimHolder'));
        }
        // ========== INSERT SOLO PARENT CODE HERE (END) ==========

        const purposeFieldConfig = { id: 'purpose', label: 'Purpose', type: 'purpose_select' };
        const purposeRow = document.createElement('div');
        purposeRow.className = 'row mb-3';
        purposeRow.innerHTML = `
            <label class="col-sm-2 col-form-label fw-bold">${purposeFieldConfig.label}:</label>
            <div class="col-sm-10" id="purposeFieldContainer_${type}"></div>
        `;

        // Insert before claim container
        const claimContainer = type === 'guardianship' 
            ? document.getElementById('guardianClaimContainer') 
            : document.getElementById('soloClaimContainer');
            
        if (claimContainer && claimContainer.parentNode) {
            claimContainer.parentNode.insertBefore(purposeRow, claimContainer);
        }

        // Render the purpose select
        const purposeContainer = document.getElementById(`purposeFieldContainer_${type}`);
        if (purposeContainer) {
            const purposes = ['Medical Assistance','Employment','School Enrollment','Passport','Scholarship','4Ps Application','Others'];
            purposeContainer.innerHTML = `
                <select id="purposeSelect_purpose_${type}" name="purpose_select" class="form-control" required>
                    <option value="">Select Purpose</option>
                    ${purposes.map(p => `<option value="${p}">${p}</option>`).join('')}
                </select>
                <input type="text" id="purposeOther_purpose_${type}" name="purpose_other" 
                    class="form-control mt-2 d-none" placeholder="Please specify purpose">
                <input type="hidden" id="purposeHidden_purpose_${type}" name="purpose" value="">
            `;
            
            // Attach event listeners
            const selectEl = document.getElementById(`purposeSelect_purpose_${type}`);
            const otherEl = document.getElementById(`purposeOther_purpose_${type}`);
            const hiddenEl = document.getElementById(`purposeHidden_purpose_${type}`);
            
            selectEl?.addEventListener('change', function() {
                if (this.value === 'Others') {
                    otherEl?.classList.remove('d-none');
                    if (otherEl) otherEl.required = true;
                    hiddenEl.value = otherEl?.value || 'Others';
                } else {
                    otherEl?.classList.add('d-none');
                    if (otherEl) otherEl.required = false;
                    hiddenEl.value = this.value;
                }
            });
            
            otherEl?.addEventListener('input', function() {
                hiddenEl.value = this.value.trim() || 'Others';
            });
        }
    }

    function addGoodMoralParentFields() {
        // remove existing if present (avoid duplicates on re-render)
        const existingGroup = document.getElementById('goodMoralParentFields');
        if (existingGroup) existingGroup.remove();

        const parentSexPref = (window.existingParentSex || window.currentUser.sex || '').toString();
        const group = document.createElement('div');
        group.id = 'goodMoralParentFields';

        group.innerHTML = `
            <div class="row mb-3 gm-parent-sex-row">
                <label class="col-sm-2 col-form-label fw-bold">Parent Sex:</label>
                <div class="col-sm-10">
                    <select name="parent_sex" class="form-select" required>
                        <option value="">Select Parent Sex</option>
                        <option value="Male">Male</option>
                        <option value="Female">Female</option>
                    </select>
                </div>
            </div>

            <div class="row mb-3 gm-parent-address-row">
                <label class="col-sm-2 col-form-label fw-bold">Parent Address:</label>
                <div class="col-sm-10">
                    <input type="text" name="parent_address" class="form-control" placeholder="Optional">
                </div>
            </div>
        `;

        // try to insert right after the civil_status row; otherwise append at end
        const civilEl = certFieldsHolder.querySelector('[name="civil_status"]');
        if (civilEl) {
            const civilRow = civilEl.closest('.row');
            if (civilRow && civilRow.parentNode) {
                civilRow.parentNode.insertBefore(group, civilRow.nextSibling);
                return;
            }
        }
        certFieldsHolder.appendChild(group);
    }

    function renderCertFields(value, mode = forSelect.value) {
        const key = (value || '').trim().toLowerCase();
        const cfg = certConfigs[key] || [];
        certFieldsHolder.innerHTML = '';

        ensureHiddenClaimFields();
        ensureHiddenPaymentFields();

        cfg.forEach(f => {
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

            if (f.type === 'claim') {
                inner += `<div id="claimContainer_${f.id}"></div></div>`;
                row.innerHTML = inner;
                certFieldsHolder.appendChild(row);
                const container = document.getElementById(`claimContainer_${f.id}`);
                const existingDate = hiddenClaimDate?.value || null;
                const existingPart = hiddenClaimTime?.value || null;
                buildClaimOptionsInto(container, existingDate, existingPart);
                return;
            }

            if (f.type === 'purpose_select') {
                const purposes = ['Medical Assistance','Employment','School Enrollment','Passport','Scholarship','4Ps Application','Others'];
                const existingPurpose = ''; // Will be populated from existing data if available
                const isInList = purposes.includes(existingPurpose);
                const otherValue = isInList ? '' : existingPurpose;
                
                inner += `
                    <select id="purposeSelect_${f.id}" name="purpose_select" class="form-control" required>
                        <option value="">Select Purpose</option>
                        ${purposes.map(p => `<option value="${p}">${p}</option>`).join('')}
                    </select>
                    <input type="text" id="purposeOther_${f.id}" name="purpose_other" 
                        class="form-control mt-2 d-none" placeholder="Please specify purpose">
                    <input type="hidden" id="purposeHidden_${f.id}" name="purpose" value="">
                `;
                inner += `</div>`;
                row.innerHTML = inner;
                certFieldsHolder.appendChild(row);
                
                // Attach purpose toggle logic
                const selectEl = document.getElementById(`purposeSelect_${f.id}`);
                const otherEl = document.getElementById(`purposeOther_${f.id}`);
                const hiddenEl = document.getElementById(`purposeHidden_${f.id}`);
                
                selectEl?.addEventListener('change', function() {
                    if (this.value === 'Others') {
                        otherEl?.classList.remove('d-none');
                        if (otherEl) otherEl.required = true;
                        hiddenEl.value = otherEl?.value || 'Others';
                    } else {
                        otherEl?.classList.add('d-none');
                        if (otherEl) otherEl.required = false;
                        hiddenEl.value = this.value;
                    }
                });
                
                otherEl?.addEventListener('input', function() {
                    hiddenEl.value = this.value.trim() || 'Others';
                });
                
                return;
            }

            if (f.type === 'select') {
                let attrs = isReadOnly ? 'class="form-select select-readonly" readonly' : 'class="form-select"';
                inner += `<select id="${f.id}" name="${f.id}" ${attrs} required>
                    ${f.options.map(o => `<option value="${o}" ${val===o?'selected':''}>${o}</option>`).join('')}
                    </select>`;
            } else {
                // Add min="1" for residing_years field
                const extraAttrs = (f.id === 'residing_years') ? ' min="1"' : '';
                inner += `<input type="${f.type}" id="${f.id}" name="${f.id}" class="form-control${isReadOnly ? ' bg-e9ecef' : ''}" value="${val}" ${isReadOnly ? 'readonly' : ''}${extraAttrs} required>`;
            }

            inner += `</div>`;
            row.innerHTML = inner;
            certFieldsHolder.appendChild(row);
        });

        if (key === 'guardianship' || key === 'solo parent') {
            renderChildSections(key);
        }

        if (key === 'good moral') {
            addGoodMoralParentFields();
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

        if (key === 'indigency' || key === 'first time job seeker') {
            if (hiddenPaymentInput) hiddenPaymentInput.value = '';
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
            if (hiddenPaymentStatus) {
                if (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) {
                    hiddenPaymentStatus.value = String(window.existingPaymentStatus).trim();
                } else {
                    hiddenPaymentStatus.value = 'Free of Charge';
                }
            }

            const paymentContent = document.getElementById('paymentStep');
            if (paymentContent && paymentContent.style) paymentContent.style.display = 'none';
            const progressPayment = document.querySelector('.payment-progress-step');
            if (progressPayment && progressPayment.style) progressPayment.style.display = 'none';
            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = 'none'; });

            try {
                if (progressFill) {
                    const pct = computeProgressPercent(currentStep, totalSteps());
                    progressFill.style.width = pct + '%';
                    progressFill.setAttribute('aria-valuenow', String(pct));
                }
            } catch (e) {}
        } else {
            const paymentContent = document.getElementById('paymentStep');
            if (paymentContent && paymentContent.style && paymentContent.style.display === 'none') {
                paymentContent.style.display = '';
            }
            const progressPayment = document.querySelector('.payment-progress-step');
            if (progressPayment && progressPayment.style && progressPayment.style.display === 'none') {
                progressPayment.style.display = '';
            }

            if (window.existingPaymentMethod && (!hiddenPaymentInput.value || !hiddenPaymentInput.value.trim())) {
                hiddenPaymentInput.value = window.existingPaymentMethod;
            }
            if (window.existingPaymentAmount && (!hiddenPaymentAmount.value || !hiddenPaymentAmount.value.trim())) {
                hiddenPaymentAmount.value = String(window.existingPaymentAmount);
            }
            if (window.existingPaymentStatus && (!hiddenPaymentStatus.value || !hiddenPaymentStatus.value.trim())) {
                hiddenPaymentStatus.value = String(window.existingPaymentStatus);
            }

            if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = 'Brgy Payment Device';
            if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
            if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
        }

        const requestForContainer = document.getElementById('requestForContainer');
        if (key === 'first time job seeker') {
            if (requestForContainer) requestForContainer.style.display = 'none';
            forSelect.value = 'myself'; // Force to "myself"
        } else {
            if (requestForContainer) requestForContainer.style.display = '';
        }

        refreshStepCollections();
        updateNavigation();
        setupPaymentControls();
    }

    // Add event listener for residing_years validation
    certFieldsHolder.addEventListener('input', function(e) {
        if (e.target && e.target.id === 'residing_years') {
            const value = parseFloat(e.target.value);
            if (value <= 0 || isNaN(value)) {
                e.target.setCustomValidity('Years residing must be greater than 0');
                e.target.classList.add('is-invalid');
            } else {
                e.target.setCustomValidity('');
                e.target.classList.remove('is-invalid');
            }
        }
        
        // Add validation for years_solo_parent
        if (e.target && e.target.name === 'years_solo_parent') {
            const value = parseFloat(e.target.value);
            if (value <= 0 || isNaN(value)) {
                e.target.setCustomValidity('Years as Solo Parent must be greater than 0');
                e.target.classList.add('is-invalid');
            } else {
                e.target.setCustomValidity('');
                e.target.classList.remove('is-invalid');
            }
        }
    });

    function refreshFields() { renderCertFields(certInput.value, forSelect.value); }

    refreshFields();

    function toggleRequestForVisibility() {
        const requestForContainer = document.getElementById('requestForContainer');
        const certValue = (certInput.value || '').trim().toLowerCase();
        
        if (!certValue) {
            // No cert type selected - hide Request For
            if (requestForContainer) requestForContainer.style.display = 'none';
        } else if (certValue === 'first time job seeker') {
            // First Time Job Seeker - hide Request For
            if (requestForContainer) requestForContainer.style.display = 'none';
            forSelect.value = 'myself';
        } else {
            // Other cert types - show Request For
            if (requestForContainer) requestForContainer.style.display = '';
        }
    }

    // Call on page load
    toggleRequestForVisibility();

    certInput.addEventListener('input', () => {
        refreshFields();
        toggleRequestForVisibility();
    });
    certInput.addEventListener('change', () => {
        refreshFields();
        toggleRequestForVisibility();
        onCertTypeChange();
    });
    certInput.addEventListener('blur', () => {
        refreshFields();
        toggleRequestForVisibility();
    });
    forSelect.addEventListener('change', refreshFields);

    setupPaymentControls();
    
    // Make goToStep globally accessible for URL navigation
    function goToStep(targetStep) {
        refreshStepCollections();
        const tSteps = totalSteps();
        
        if (targetStep < 1) targetStep = 1;
        if (targetStep > tSteps) targetStep = tSteps;
        
        // Update step display
        steps.forEach((step, idx) => {
            step.classList.remove('active-step');
            if (idx === targetStep - 1) {
                step.classList.add('active-step');
            }
        });
        
        circleSteps.forEach((c, idx) => {
            c.classList.remove('active', 'completed');
            if (idx < targetStep - 1) c.classList.add('completed');
            if (idx === targetStep - 1) c.classList.add('active');
        });
        
        stepLabels.forEach((l, idx) => {
            l.classList.remove('active', 'completed');
            if (idx < targetStep - 1) l.classList.add('completed');
            if (idx === targetStep - 1) l.classList.add('active');
        });
        
        const pct = computeProgressPercent(targetStep, tSteps);
        if (progressFill) {
            progressFill.style.width = pct + '%';
            progressFill.setAttribute('aria-valuenow', String(pct));
        }
        
        currentStep = targetStep;
        updateNavigation();
        
        if (getSummaryStepIndex() === currentStep) {
            populateSummary();
        }
    }

    // Expose globally for URL navigation
    window.goToStep = goToStep;

    nextBtn.addEventListener('click', () => {
        refreshStepCollections();
        const tSteps = totalSteps();

        let isValid = true;

        let activeIndex = currentStep;
        const activeStepEl = steps[activeIndex - 1];
        if (activeStepEl) {
            activeStepEl.querySelectorAll("input[required], select[required]").forEach(field => {
                if (!field.value || !String(field.value).trim()) {
                    isValid = false;
                    field.classList.add("is-invalid");
                } else {
                    field.classList.remove("is-invalid");
                }
                
                // Check for residing_years specific validation
                if (field.id === 'residing_years') {
                    const value = parseFloat(field.value);
                    if (value <= 0 || isNaN(value)) {
                        isValid = false;
                        field.classList.add("is-invalid");
                        field.setCustomValidity('Years residing must be greater than 0');
                    }
                }

                // Validate years_solo_parent
                if (field.name === 'years_solo_parent') {
                    const value = parseFloat(field.value);
                    if (value <= 0 || isNaN(value)) {
                        isValid = false;
                        field.classList.add("is-invalid");
                        field.setCustomValidity('Years as Solo Parent must be greater than 0');
                    }
                }
            });
        }

        // Validate payment method selection at payment step (but skip for GCash since it submits immediately)
        const paymentIndex = getPaymentStepIndex();
        if (paymentIndex > 0 && currentStep === paymentIndex) {
            const selectedMethod = hiddenPaymentInput?.value || '';
            if (!selectedMethod) {
                isValid = false;
            }
        }

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

        // === NEW: Handle GCash payment submission at Step 2 ===
        if (paymentIndex > 0 && currentStep === paymentIndex) {
            const selectedMethod = hiddenPaymentInput?.value || '';
            const certType = (certInput.value || '').trim().toLowerCase();
            const isPaidService = !(certType === 'indigency' || certType === 'first time job seeker');
            
            if (isPaidService && selectedMethod === 'GCash') {
                // For GCash, submit the form directly - it will redirect to GCash checkout
                const form = document.getElementById('certForm');
                if (form) {
                    // Show loading indicator
                    nextBtn.disabled = true;
                    nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    
                    form.submit();
                    return; // Stop here, don't proceed to next step
                }
            }
        }
        // === END GCash handling ===

        const summaryIndex = getSummaryStepIndex();
        const submissionIndex = getSubmissionStepIndex();

        if (summaryIndex > 0 && currentStep === summaryIndex) {
            if ((certInput.value || '').trim().toLowerCase() === 'indigency') {
                if (hiddenPaymentInput) hiddenPaymentInput.value = '';
                if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
                if (hiddenPaymentStatus) {
                    hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
                }
            } else {
                if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
            }
            if (confirmationModal) confirmationModal.show();
            return;
        }

        if (submissionIndex > 0 && currentStep === submissionIndex) {
            window.location.href = 'userPanel.php?page=userDashboard';
            return;
        }

        if (currentStep < tSteps) {
            const prevIdx = currentStep - 1;
            if (circleSteps[prevIdx]) circleSteps[prevIdx].classList.add('completed');
            if (stepLabels[prevIdx]) stepLabels[prevIdx].classList.add('completed');
            if (steps[prevIdx]) steps[prevIdx].classList.remove('active-step');

            currentStep++;
            const newIdx = currentStep - 1;
            if (steps[newIdx]) steps[newIdx].classList.add('active-step');
            if (circleSteps[newIdx]) circleSteps[newIdx].classList.add('active');
            if (stepLabels[newIdx]) stepLabels[newIdx].classList.add('active');

            const pct = computeProgressPercent(currentStep, tSteps);
            if (progressFill) {
                progressFill.style.width = pct + '%';
                progressFill.setAttribute('aria-valuenow', String(pct));
            }

            if (getSummaryStepIndex() > 0 && currentStep === getSummaryStepIndex()) {
                populateSummary();
            }

            updateNavigation();
        }
    });

    backBtn.addEventListener('click', () => {
        refreshStepCollections();
        if (currentStep > 1) {
            const prevIdx = currentStep - 1;
            if (steps[prevIdx]) steps[prevIdx].classList.remove("active-step");
            if (circleSteps[prevIdx]) circleSteps[prevIdx].classList.remove('active');
            if (stepLabels[prevIdx]) stepLabels[prevIdx].classList.remove('active');

            const priorIdx = currentStep - 2;
            if (circleSteps[priorIdx]) circleSteps[priorIdx].classList.remove('completed');
            if (stepLabels[priorIdx]) stepLabels[priorIdx].classList.remove('completed');

            currentStep--;
            const newIdx = currentStep - 1;
            if (steps[newIdx]) steps[newIdx].classList.add("active-step");
            if (circleSteps[newIdx]) circleSteps[newIdx].classList.add('active');
            if (stepLabels[newIdx]) stepLabels[newIdx].classList.add('active');

            const pct = computeProgressPercent(currentStep, totalSteps());
            if (progressFill) {
                progressFill.style.width = pct + '%';
                progressFill.setAttribute('aria-valuenow', String(pct));
            }

            updateNavigation();
        }
    });

    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', () => {
            if ((certInput.value || '').trim().toLowerCase() === 'indigency') {
                if (hiddenPaymentInput) hiddenPaymentInput.value = '';
                if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
                if (hiddenPaymentStatus) {
                    hiddenPaymentStatus.value = (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) || 'Free of Charge';
                }
            } else {
                if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = String(DEFAULT_AMOUNT);
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = 'Pending';
            }

            const cd = hiddenClaimDate?.value || '';
            const ct = hiddenClaimTime?.value || '';
            if (!isValidClaimSeparate(cd, ct)) {
                let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
                validationModal.show();
                return;
            }

            document.getElementById("certForm").submit();
        });
    }

    function updateNavigation() {
        refreshStepCollections();

        if (currentStep < 1) currentStep = 1;
        if (currentStep > totalSteps()) currentStep = totalSteps();

        if (progressFill) {
            const pct = computeProgressPercent(currentStep, totalSteps());
            progressFill.style.width = pct + '%';
            progressFill.setAttribute('aria-valuenow', String(pct));
        }

        backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';
        const labelText = (stepLabels[currentStep - 1] && stepLabels[currentStep - 1].textContent.trim()) || '';

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
            if (mainHeader && mainHeader.parentNode) mainHeader.remove();
            if (subHeader && subHeader.parentNode) subHeader.remove();
            const hr = document.getElementById('mainHr');
            if (hr && hr.parentNode) hr.remove();

            backBtn.style.visibility = 'hidden';
            nextBtn.textContent = "Back to Home";
            const newNext = nextBtn.cloneNode(true);
            if (nextBtn.parentNode) nextBtn.parentNode.replaceChild(newNext, nextBtn);
            newNext.addEventListener('click', () => {
                window.location.href = 'userPanel.php?page=userDashboard';
            });
        } else {
            mainHeader.textContent = labelText || "APPLICATION";
            subHeader.textContent = "";
            nextBtn.textContent = "NEXT >";
        }
    }

    function populateSummary() {
        ensureHiddenPaymentFields();

        const type = (certInput.value || '').trim().toLowerCase();
        const container = document.getElementById('summaryContainer');

        const rows = [
            ['Type of Certification:', certInput?.value || '—'],
            ['Requesting For:', forSelect?.value === 'myself' ? 'Myself' : 'Others'],
            ['Full Name:', (document.querySelector('[name="full_name"]')?.value) || '—'],
            ['Age:', (document.querySelector('[name="age"]')?.value) || '—'],
            ['Civil Status:', (document.querySelector('[name="civil_status"]')?.value) || '—'],
            ['Purok:', (document.querySelector('[name="purok"]')?.value) || '—']
        ];

        // Add Sex field for First Time Job Seeker
        if (type === 'first time job seeker') {
            rows.push(['Sex:', (document.querySelector('[name="sex"]')?.value) || '—']);
        }

        // if (type === 'first time job seeker') {
        //     const sex = document.querySelector('[name="sex"]')?.value || '—';
        //     rows.push(['Sex:', sex]);
        // }

        if (type === 'good moral') {
            const parentSexGM = document.querySelector('[name="parent_sex"]')?.value || window.existingParentSex || window.currentUser.sex || '—';
            const parentAddressGM = document.querySelector('[name="parent_address"]')?.value || window.existingParentAddress || '—';
            rows.push(['Parent Sex:', parentSexGM || '—']);
            rows.push(['Parent Address:', parentAddressGM || '—']);
        }

        if (type === 'solo parent') {
            const parentSex = document.querySelector('[name="parent_sex"]')?.value || (window.existingParentSex || '') || '—';
            rows.push(['Parent Sex:', parentSex || '—']);

            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childSexes = Array.from(document.querySelectorAll('[name="child_sex[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childBirthdates = Array.from(document.querySelectorAll('[name="child_birthdate[]"]')).map(el => el.value.trim()).filter(Boolean);

            // Function to calculate age display from birthdate
            function calculateAgeDisplay(birthdate) {
                if (!birthdate) return '—';
                
                const birth = new Date(birthdate);
                const today = new Date();
                
                let years = today.getFullYear() - birth.getFullYear();
                let months = today.getMonth() - birth.getMonth();
                let days = today.getDate() - birth.getDate();
                
                // Adjust for negative days
                if (days < 0) {
                    months--;
                    const lastMonth = new Date(today.getFullYear(), today.getMonth(), 0);
                    days += lastMonth.getDate();
                }
                
                // Adjust for negative months
                if (months < 0) {
                    years--;
                    months += 12;
                }
                
                // Format display based on age
                if (years > 0) {
                    return years + ' year' + (years !== 1 ? 's' : '') + ' old';
                } else if (months > 0) {
                    return months + ' month' + (months !== 1 ? 's' : '') + ' old';
                } else {
                    const weeks = Math.floor(days / 7);
                    if (weeks > 0) {
                        return weeks + ' week' + (weeks !== 1 ? 's' : '') + ' old';
                    } else {
                        return days + ' day' + (days !== 1 ? 's' : '') + ' old';
                    }
                }
            }

            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
                rows.push([`Child ${i + 1} Sex:`, childSexes[i] || '—']);
                
                // Calculate age from birthdate
                const ageDisplay = childBirthdates[i] ? calculateAgeDisplay(childBirthdates[i]) : '—';
                rows.push([`Child ${i + 1} Age:`, ageDisplay]);
            });

            const years = document.querySelector('[name="years_solo_parent"]')?.value || '—';
            rows.push(['Years as Solo Parent:', years]);
        }

        if (type === 'guardianship') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            const childRelationships = Array.from(document.querySelectorAll('[name="child_relationship[]"]')).map(el => el.value.trim()).filter(Boolean);
            
            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
                rows.push([`Child ${i + 1} Relationship:`, childRelationships[i] || '—']);
            });
        }

        if (type === 'residency') {
            rows.push([
                'Years Residing:',
                document.querySelector('[name="residing_years"]')?.value || '—'
            ]);
        }

        const claimDateVal = hiddenClaimDate?.value || document.querySelector('[name="claim_date"]')?.value || '';
        const claimTimeVal = hiddenClaimTime?.value || document.querySelector('[name="claim_time"]')?.value || '';
        let claimLabel = '—';
        if (isValidClaimSeparate(claimDateVal, claimTimeVal)) {
            const opt = getNextBusinessDays(3).find(x => x.date === claimDateVal);
            const dateLabel = opt ? opt.label : claimDateVal;
            const partLabel = claimTimeVal === 'Morning' ? 'Morning (8:00 AM to 12:00 NN)' : 'Afternoon (1:00 PM to 5:00 PM)';
            claimLabel = dateLabel + ' - ' + partLabel;
        } else if (claimDateVal) {
            claimLabel = claimDateVal + (claimTimeVal ? (' - ' + claimTimeVal) : '');
        }

        rows.push(['Claim Date:', claimLabel]);
        // Purpose - ONLY for certificates that have purpose field (NOT First Time Job Seeker)
        if (type !== 'first time job seeker' && type !== 'indigency') {
            const purposeHiddenEl = document.querySelector('[name="purpose"]');
            const purposeDisplay = purposeHiddenEl?.value || '—';
            rows.push(['Purpose:', purposeDisplay]);
        }

        const clientAmount = (hiddenPaymentAmount?.value || '').toString().trim();
        const clientStatus = (hiddenPaymentStatus?.value || '').toString().trim();
        const serverAmount = (window.existingPaymentAmount || '').toString().trim();
        const serverStatus = (window.existingPaymentStatus || '').toString().trim();

        const amountVal = clientAmount || serverAmount || '';
        const statusVal = clientStatus || serverStatus || '';

        // Payment display - different for free vs paid certificates
        if (type === 'indigency' || type === 'first time job seeker') {
            // Free certificates: show ONLY payment status, NO amount
            rows.push(['Payment Status:', statusVal || 'Free of Charge']);
        } else {
            // Paid certificates: show both amount and payment status
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

    function onCertTypeChange() {
        ensureHiddenPaymentFields();

        const val = (certInput.value || '').trim().toLowerCase();
        if (val === 'indigency' || val === 'first time job seeker') {
            if (hiddenPaymentInput) hiddenPaymentInput.value = '';
            if (hiddenPaymentAmount) hiddenPaymentAmount.value = '';
            if (hiddenPaymentStatus) {
                if (window.existingPaymentStatus && String(window.existingPaymentStatus).trim()) {
                    hiddenPaymentStatus.value = String(window.existingPaymentStatus).trim();
                } else {
                    hiddenPaymentStatus.value = 'Free of Charge';
                }
            }

            const pStep = document.getElementById('paymentStep');
            if (pStep && pStep.parentNode) pStep.parentNode.removeChild(pStep);
            const pProgress = document.querySelector('.payment-progress-step');
            if (pProgress && pProgress.parentNode) pProgress.parentNode.removeChild(pProgress);

            renumberSteps();

            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = 'none'; });

            refreshStepCollections();
            if (currentStep > totalSteps()) currentStep = totalSteps();
            updateNavigation();
            populateSummary();

            if (progressFill) {
                progressFill.style.width = '100%';
                progressFill.setAttribute('aria-valuenow', '100');
            }
        } else {
            if (!document.getElementById('paymentStep')) {
                // Store the current selection before reload
                sessionStorage.setItem('pendingCertType', certInput.value);
                location.reload();
            } else {
                if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = (window.existingPaymentMethod || 'Brgy Payment Device');
                if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = (window.existingPaymentAmount || String(DEFAULT_AMOUNT));
                if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = (window.existingPaymentStatus || 'Pending');
            }

            refreshStepCollections();
            updateNavigation();
        }
    }

    // certInput.addEventListener('change', onCertTypeChange);
    // certInput.addEventListener('input', onCertTypeChange);

    (function initFromServer() {
        ensureHiddenPaymentFields();

        // URL step handling is now done by the global handler at the end of file
        // Just set initial step if provided from server
        const urlParams = new URLSearchParams(window.location.search);
        if (window.initialStep && !urlParams.get('step')) {
            currentStep = window.initialStep;
            refreshStepCollections();
            updateNavigation();
        }

        if (window.existingPaymentMethod && hiddenPaymentInput && !hiddenPaymentInput.value) {
            hiddenPaymentInput.value = window.existingPaymentMethod;
        }
        if (window.existingPaymentAmount !== undefined && window.existingPaymentAmount !== null && hiddenPaymentAmount && !hiddenPaymentAmount.value) {
            hiddenPaymentAmount.value = String(window.existingPaymentAmount);
        }
        if (window.existingPaymentStatus !== undefined && window.existingPaymentStatus !== null && hiddenPaymentStatus && !hiddenPaymentStatus.value) {
            hiddenPaymentStatus.value = String(window.existingPaymentStatus);
        }
        setupPaymentControls();

        if ((window.existingCertType || '').toString().toLowerCase() === 'indigency' || 
            (window.existingCertType || '').toString().toLowerCase() === 'first time job seeker') {
            certInput.value = window.existingCertType;
            renderCertFields(window.existingCertType, forSelect.value);
            if (hiddenPaymentStatus && (!hiddenPaymentStatus.value || !hiddenPaymentStatus.value.trim())) {
                hiddenPaymentStatus.value = 'Free of Charge';
            }
            if (progressFill) {
                progressFill.style.width = '100%';
                progressFill.setAttribute('aria-valuenow', '100');
            }
        }

        if (Number(window.initialStep || 1) >= 3) {
            setTimeout(() => { populateSummary(); }, 120);
        }
    })();

    refreshStepCollections();
    updateNavigation();

    // Handle URL step parameter and back button warnings
    (function() {
        // Check URL for step parameter (for GCash returns)
        const urlParams = new URLSearchParams(window.location.search);
        const urlStep = urlParams.get('step');
        const hasTid = urlParams.get('tid');
        
        if (urlStep && hasTid) {
            const targetStep = parseInt(urlStep);
            console.log('URL step detected:', targetStep);
            
            // Wait for goToStep to be defined
            const checkAndNavigate = setInterval(function() {
                if (typeof window.goToStep === 'function') {
                    clearInterval(checkAndNavigate);
                    console.log('Navigating to step:', targetStep);
                    window.goToStep(targetStep);
                }
            }, 50);
            
            // Timeout after 2 seconds
            setTimeout(function() {
                clearInterval(checkAndNavigate);
            }, 2000);
        }
        
        // Back button warning for payment page
        let isOnPaymentStep = false;
        
        // Detect payment step
        const observer = new MutationObserver(function() {
            const activeStep = Array.from(steps).findIndex(s => s.classList.contains('active-step'));
            isOnPaymentStep = (activeStep === getPaymentStepIndex() - 1);
        });
        
        steps.forEach(step => {
            observer.observe(step, { attributes: true, attributeFilter: ['class'] });
        });
        
        // Warn on back button
        window.addEventListener('popstate', function(e) {
            if (isOnPaymentStep) {
                const paymentMethod = hiddenPaymentInput;
                if (paymentMethod && paymentMethod.value === 'GCash') {
                    if (!confirm('Going back may cancel your payment. Continue?')) {
                        window.history.pushState(null, null, window.location.href);
                        e.preventDefault();
                    }
                }
            }
        });
    })();
});