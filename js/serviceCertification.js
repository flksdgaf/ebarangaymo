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

        let preDate = hiddenClaimDate?.value || null;
        let prePart = hiddenClaimTime?.value || null;
        const first = claimList.querySelector('input[type="radio"]');
        if (preDate && prePart && isValidClaimSeparate(preDate, prePart)) {
            const target = claimList.querySelector(`input[data-date="${preDate}"][data-part="${prePart}"]`);
            if (target) {
                target.checked = true;
                target.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }
        if (first && !claimList.querySelector('input[type="radio"]:checked')) {
            first.checked = true;
            first.dispatchEvent(new Event('change', { bubbles: true }));
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
            { id: 'civil_status',    label: 'Marital Status',  type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
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
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     }
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

            <div class="row mb-3" id="purposeContainer_soloparent"></div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#guardianChildren');

            function addChild(prefillName = '') {
                const row = document.createElement('div');
                row.className = 'row mb-3 child-row';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name:</label>
                    <div class="col-sm-9">
                        <input type="text" name="child_name[]" class="form-control" value="${prefillName}" required>
                    </div>
                    <div class="col-sm-1 d-flex gap-1">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child" style="white-space: nowrap;">DELETE</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-child" style="white-space: nowrap;">+ Add</button>
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
                    
                    // Hide delete button if only one row
                    if (allRows.length === 1) {
                        deleteBtn.style.display = 'none';
                    } else {
                        deleteBtn.style.display = '';
                    }
                    
                    // Only show add button on last row
                    if (index === allRows.length - 1) {
                        addBtn.style.display = '';
                    } else {
                        addBtn.style.display = 'none';
                    }
                });
            }

            // Add one empty child row initially
            addChild();

            buildClaimOptionsInto(wrapper.querySelector('#guardianClaimHolder'));
        } else if (type === 'solo parent') {
            const parentSexVal = (window.existingParentSex || window.currentUser?.sex || '').toString();
            const parentSexHtml = `
                <div class="row mb-3">
                    <label class="col-sm-2 col-form-label fw-bold">Parent Sex:</label>
                    <div class="col-sm-4">
                        <select name="parent_sex" class="form-select">
                            <option value="" ${parentSexVal === '' ? 'selected' : ''}>-- Select --</option>
                            <option value="Male" ${parentSexVal === 'Male' ? 'selected' : ''}>Male</option>
                            <option value="Female" ${parentSexVal === 'Female' ? 'selected' : ''}>Female</option>
                            <option value="Other" ${parentSexVal && parentSexVal !== 'Male' && parentSexVal !== 'Female' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>
                </div>
            `;
            wrapper.innerHTML = parentSexHtml;
            
            wrapper.innerHTML += `
            <div id="soloChildren"></div>

            <div class="row mb-3">
                <label class="col-sm-2 fw-bold">Years as Solo Parent:</label>
                <div class="col-sm-10">
                <input type="number" name="years_solo_parent" class="form-control" required>
                </div>
            </div>

            <div id="soloClaimContainer" class="row mb-3">
                <label class="col-sm-2 fw-bold">Claim Date:</label>
                <div class="col-sm-10" id="soloClaimHolder"></div>
            </div>

            <div class="row mb-3" id="purposeContainer_soloparent"></div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#soloChildren');
            
            function addChild(prefillData = null) {
                const row = document.createElement('div');
                row.className = 'row mb-3 child-row';
                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name:</label>
                    <div class="col-sm-4">
                        <input type="text" name="child_name[]" class="form-control" 
                            value="${prefillData?.name || ''}" required>
                    </div>

                    <label class="col-sm-1 col-form-label fw-bold">Age:</label>
                    <div class="col-sm-1">
                        <input type="number" name="child_age[]" class="form-control" 
                            value="${prefillData?.age || ''}" required>
                    </div>

                    <label class="col-sm-1 col-form-label fw-bold">Sex:</label>
                    <div class="col-sm-2">
                        <select name="child_sex[]" class="form-select" required>
                            <option value="">—</option>
                            <option ${prefillData?.sex === 'Male' ? 'selected' : ''}>Male</option>
                            <option ${prefillData?.sex === 'Female' ? 'selected' : ''}>Female</option>
                            <option ${prefillData?.sex === 'Other' ? 'selected' : ''}>Other</option>
                        </select>
                    </div>

                    <div class="col-sm-1 d-flex gap-1">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child" style="white-space: nowrap;">DELETE</button>
                        <button type="button" class="btn btn-outline-primary btn-sm add-child" style="white-space: nowrap;">+ Add</button>
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
                    
                    // Hide delete button if only one row
                    if (allRows.length === 1) {
                        deleteBtn.style.display = 'none';
                    } else {
                        deleteBtn.style.display = '';
                    }
                    
                    // Only show add button on last row
                    if (index === allRows.length - 1) {
                        addBtn.style.display = '';
                    } else {
                        addBtn.style.display = 'none';
                    }
                });
            }
            
            // Parse and populate existing children data OR add one empty row
            if (window.existingChildrenData && window.existingChildrenData.trim()) {
                try {
                    const childrenArray = JSON.parse(window.existingChildrenData);
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

            buildClaimOptionsInto(wrapper.querySelector('#soloClaimHolder'));
        }

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
            const purposes = ['Employment','Another Valid ID','School Enrollment','Passport','Scholarship','4Ps Application','Others'];
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
                <div class="col-sm-4">
                    <select name="parent_sex" class="form-select">
                        <option value="" ${parentSexPref === '' ? 'selected' : ''}>-- Select --</option>
                        <option value="Male" ${parentSexPref === 'Male' ? 'selected' : ''}>Male</option>
                        <option value="Female" ${parentSexPref === 'Female' ? 'selected' : ''}>Female</option>
                        <option value="Other" ${parentSexPref && parentSexPref !== 'Male' && parentSexPref !== 'Female' ? 'selected' : ''}>Other</option>
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
                const purposes = ['Employment','Another Valid ID','School Enrollment','Passport','Scholarship','4Ps Application','Others'];
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
                inner += `<input type="${f.type}" id="${f.id}" name="${f.id}" class="form-control${isReadOnly ? ' bg-e9ecef' : ''}" value="${val}" ${isReadOnly ? 'readonly' : ''} required>`;
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
            });
        }

        const claimGroupInActive = activeStepEl ? activeStepEl.querySelector('.claim-list') : null;
        if (claimGroupInActive) {
            const cd = hiddenClaimDate?.value || '';
            const ct = hiddenClaimTime?.value || '';
            if (!isValidClaimSeparate(cd, ct)) {
                isValid = false;
                const firstCard = claimGroupInActive.querySelector('.claim-card');
                if (firstCard) {
                    firstCard.style.outline = '1px solid #dc3545';
                    firstCard.setAttribute('aria-invalid', 'true');
                }
            }
        }

        const paymentIndex = getPaymentStepIndex();
        if (paymentIndex > 0 && currentStep === paymentIndex) {
            if (!hiddenPaymentInput.value) isValid = false;
        }

        if (!isValid) {
            let validationModal = new bootstrap.Modal(document.getElementById("validationModal"));
            validationModal.show();
            return;
        }

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
            ['Type of Certification:', certInput.value || '—'],
            ['Requesting For:', forSelect.value === 'myself' ? 'Myself' : 'Others'],
            ['Full Name:', (document.querySelector('[name="full_name"]')?.value) || '—'],
            ['Age:', (document.querySelector('[name="age"]')?.value) || '—'],
            ['Civil Status:', (document.querySelector('[name="civil_status"]')?.value) || '—'],
            ['Purok:', (document.querySelector('[name="purok"]')?.value) || '—']
        ];

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

        if (type === 'guardianship') {
            const childNames = Array.from(document.querySelectorAll('[name="child_name[]"]')).map(el => el.value.trim()).filter(Boolean);
            childNames.forEach((name, i) => {
                rows.push([`Child ${i + 1} Name:`, name || '—']);
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
        const purposeHiddenEl = document.querySelector('[name="purpose"]');
        const purposeDisplay = purposeHiddenEl?.value || '—';
        rows.push(['Purpose:', purposeDisplay]);

        const clientAmount = (hiddenPaymentAmount?.value || '').toString().trim();
        const clientStatus = (hiddenPaymentStatus?.value || '').toString().trim();
        const serverAmount = (window.existingPaymentAmount || '').toString().trim();
        const serverStatus = (window.existingPaymentStatus || '').toString().trim();

        const amountVal = clientAmount || serverAmount || '';
        const statusVal = clientStatus || serverStatus || '';

        if (type === 'indigency') {
            rows.push(['Payment Status:', statusVal || 'Free of Charge']);
        } else {
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
});