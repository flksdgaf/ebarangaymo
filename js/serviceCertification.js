// (entire file — updated populateSummary behavior for First Time Job Seeker)
// Replace your current js file with this exact content.

document.addEventListener("DOMContentLoaded", function () {
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

    // Purpose controls (may be present from server-side PHP)
    const purposeSelect = document.getElementById('purposeSelect');
    const purposeOther  = document.getElementById('purposeOther');
    const purposeHidden = document.getElementById('purposeHidden');

    const confirmationModalEl = document.getElementById("confirmationModal");
    const confirmationModal = confirmationModalEl ? new bootstrap.Modal(confirmationModalEl) : null;
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    let currentStep = window.initialStep || 1;

    const currentUser = window.currentUser || {};
    const forSelect = document.getElementById('forSelect');
    const certInput = document.getElementById('certType');
    const certFieldsHolder = document.getElementById('certFields');
    const purposeContainer = document.getElementById('purposeContainer'); // may be null if not rendered

    const DEFAULT_AMOUNT = 130;

    function refreshStepCollections() {
        steps = document.querySelectorAll(".step");
        circleSteps = document.querySelectorAll('.circle');
        stepLabels = document.querySelectorAll('.step-label');
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

    // -------------------------
    // Purpose controls (minimal adaptation from serviceBarangayClearance.js)
    // -------------------------
    function setupPurposeControls() {
        // Only run if the server rendered the purposeSelect & hidden purpose input
        const sel = document.getElementById('purposeSelect');
        const other = document.getElementById('purposeOther');
        const hidden = document.getElementById('purposeHidden');
        const purposeContainer = document.getElementById('purposeContainer') || null;

        // If server didn't render the global purpose UI, nothing to do here.
        if (!sel || !hidden) return;

        // Purpose exclusion list from server (fallback if not present)
        const purposeExcluded = Array.isArray(window.purposeExcluded) ? window.purposeExcluded : ['First Time Job Seeker'];

        function normalize(s){ return (s||'').toString().trim().toLowerCase(); }
        function isExcludedForCert(certName){
            if(!certName) return false;
            return purposeExcluded.some(x => normalize(x) === normalize(certName));
        }

        // Decide visibility based on current certificate selection or server value
        const currentCertName = (document.getElementById('certType')?.value || window.existingCertType || '').toString();
        if (isExcludedForCert(currentCertName)) {
            // hide the purpose UI and clear the hidden storage so it won't be submitted accidentally
            if (purposeContainer) purposeContainer.style.display = 'none';
            hidden.value = '';
            // still return so we don't wire change handlers for the hidden UI
            return;
        } else {
            // make sure it's visible if not excluded
            if (purposeContainer) purposeContainer.style.display = '';
        }

        // initialize based on hidden value if present
        if (hidden.value) {
            const hiddenVal = hidden.value.trim();
            const match = Array.from(sel.options).some(o => o.value === hiddenVal);
            if (!match) {
                const othersOpt = Array.from(sel.options).find(o => o.value === 'Others' || o.text === 'Others');
                if (othersOpt) { othersOpt.selected = true; if (other) other.value = hiddenVal; }
            } else sel.value = hiddenVal;
        } else {
            // ensure hidden has initial select value when possible
            hidden.value = sel.value || hidden.value || '';
        }

        const toggle = () => {
            if (sel.value === 'Others') {
                if (other) { other.classList.remove('d-none'); other.required = true; if (!other.value && hidden.value && hidden.value !== 'Others') other.value = hidden.value; }
                if (other && other.value.trim()) hidden.value = other.value.trim();
                else hidden.value = 'Others';
            } else {
                if (other) { other.classList.add('d-none'); other.required = false; other.classList.remove('is-invalid'); }
                hidden.value = sel.value;
            }
            if (typeof populateSummary === 'function') populateSummary();
        };

        sel.addEventListener('change', function() { toggle(); });
        if (other) other.addEventListener('input', function() {
            hidden.value = other.value.trim() || 'Others';
            if (typeof populateSummary === 'function') populateSummary();
        });

        // initial toggle
        toggle();
    }

    // -------------------------
    // End purpose controls
    // -------------------------

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
            { id: 'purpose',         label: 'Purpose',         type: 'text'   },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim'   }
        ],
        indigency: [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'text' },
            { id: 'claim_date',      label: 'Claim Date',      type: 'claim' }
        ],
        'good moral': [
            { id: 'full_name',       label: 'Full Name',       type: 'text',     disabled: true },
            { id: 'age',             label: 'Age',             type: 'number',   disabled: true },
            { id: 'civil_status',    label: 'Civil Status',    type: 'select',   options: ['Single','Married','Widowed','Separated','Divorced','Unknown']   },
            { id: 'purok',           label: 'Purok',           type: 'select',   options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6']     },
            { id: 'purpose',         label: 'Purpose',         type: 'text' },
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
        ],
        'first time job seeker': [
            { id: 'full_name',    label: 'Full Name',    type: 'text',   disabled: true },
            { id: 'age',          label: 'Age',          type: 'number', disabled: true },
            { id: 'civil_status', label: 'Civil Status', type: 'select', options: ['Single','Married','Widowed','Separated','Divorced','Unknown'] },
            { id: 'purok',        label: 'Purok',        type: 'select', options: ['Purok 1','Purok 2','Purok 3','Purok 4','Purok 5','Purok 6'] },
            { id: 'claim_date',   label: 'Claim Date',   type: 'claim' }
        ]
    };

    function renderChildSections(type) {
        const wrapper = document.createElement('div');
        wrapper.id = 'childSection';

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

        if (type === 'guardianship') {
            wrapper.innerHTML += `
                <div id="guardianChildren"></div>

                <div id="guardianClaimContainer" class="row mb-3">
                    <label class="col-sm-2 fw-bold">Claim Date:</label>
                    <div class="col-sm-10" id="guardianClaimHolder"></div>
                </div>
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#guardianChildren');

            // Each child row includes both DELETE and + Add Child buttons aligned on the right
            function addChild() {
                const row = document.createElement('div');
                row.className = 'row mb-3 align-items-center';

                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name:</label>
                    <div class="col-sm-7">
                        <input type="text" name="child_name[]" class="form-control" required>
                    </div>
                    <div class="col-sm-3 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child">DELETE</button>
                        <button type="button" class="btn btn-sm btn-outline-primary add-child-local">+ Add Child</button>
                    </div>
                `;

                container.appendChild(row);

                const removeBtn = row.querySelector('.remove-child');
                const addBtnLocal = row.querySelector('.add-child-local');

                removeBtn.onclick = () => {
                    // Count number of child rows (fields)
                    const rows = container.querySelectorAll('input[name="child_name[]"]');
                    if (rows.length <= 1) {
                        // prevent deletion of last field
                        // visual feedback: briefly add a bootstrap 'shake' style (if available) or fallback to alert
                        try {
                            // add a quick outline to show invalid action
                            removeBtn.classList.add('disabled');
                            removeBtn.setAttribute('aria-disabled', 'true');
                            setTimeout(() => {
                                removeBtn.classList.remove('disabled');
                                removeBtn.removeAttribute('aria-disabled');
                            }, 600);
                        } catch (e) {}
                        // fallback message
                        alert('At least one Child Name is required for Guardianship and cannot be removed.');
                        return;
                    }
                    // Otherwise allow removal
                    row.remove();
                };

                addBtnLocal.onclick = () => {
                    // append a new child row and focus its input
                    addChild();
                    const inputs = container.querySelectorAll('input[name="child_name[]"]');
                    if (inputs.length) inputs[inputs.length - 1].focus();
                };
            }

            // create one initial row
            addChild();

            // Insert purposeContainer (if present) directly after guardianChildren
            try {
                if (purposeContainer) {
                    if (!purposeContainer.parentNode || purposeContainer.parentNode !== wrapper) {
                        const guardianChildrenEl = wrapper.querySelector('#guardianChildren');
                        if (guardianChildrenEl && guardianChildrenEl.parentNode) {
                            guardianChildrenEl.parentNode.insertBefore(purposeContainer, guardianChildrenEl.nextSibling);
                            purposeContainer.style.display = '';
                        }
                    }
                }
            } catch (e) { /* ignore insertion errors */ }

            buildClaimOptionsInto(wrapper.querySelector('#guardianClaimHolder'));
        } else if (type === 'solo parent') {
            wrapper.innerHTML += `
                <div id="soloChildren"></div>
                <!-- per-row Add button replaces the global add button -->
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
            `;
            certFieldsHolder.appendChild(wrapper);

            const container = wrapper.querySelector('#soloChildren');

            function addChild() {
                const row = document.createElement('div');
                row.className = 'row mb-3 align-items-center';

                row.innerHTML = `
                    <label class="col-sm-2 col-form-label fw-bold">Child's Name:</label>

                    <div class="col-sm-3">
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
                            <option>Other</option>
                        </select>
                    </div>

                    <div class="col-sm-2 d-flex justify-content-end gap-2">
                        <button type="button" class="btn btn-outline-danger btn-sm remove-child">DELETE</button>
                        <button type="button" class="btn btn-sm btn-outline-primary add-child-local">+ Add Child</button>
                    </div>
                `;

                container.appendChild(row);

                const removeBtn = row.querySelector('.remove-child');
                const addBtnLocal = row.querySelector('.add-child-local');

                removeBtn.onclick = () => {
                    // Count number of child name inputs
                    const rows = container.querySelectorAll('input[name="child_name[]"]');
                    if (rows.length <= 1) {
                        // prevent deletion of last field
                        try {
                            removeBtn.classList.add('disabled');
                            removeBtn.setAttribute('aria-disabled', 'true');
                            setTimeout(() => {
                                removeBtn.classList.remove('disabled');
                                removeBtn.removeAttribute('aria-disabled');
                            }, 600);
                        } catch (e) {}
                        alert('At least one Child Name is required for Solo Parent and cannot be removed.');
                        return;
                    }
                    // otherwise allow removal
                    row.remove();
                };

                addBtnLocal.onclick = () => {
                    addChild();
                    const inputs = container.querySelectorAll('input[name="child_name[]"]');
                    if (inputs.length) inputs[inputs.length - 1].focus();
                };
            }

            // create one initial row
            addChild();

            // Insert purposeContainer (if present) after the years row (same logic as before)
            try {
                if (purposeContainer) {
                    const yearsInput = wrapper.querySelector('input[name="years_solo_parent"]');
                    const yearsRow = yearsInput ? yearsInput.closest('.row') : null;
                    if (yearsRow && (!purposeContainer.parentNode || purposeContainer.parentNode !== wrapper)) {
                        yearsRow.parentNode.insertBefore(purposeContainer, yearsRow.nextSibling);
                        purposeContainer.style.display = '';
                    }
                }
            } catch (e) { /* ignore insertion errors */ }

            buildClaimOptionsInto(wrapper.querySelector('#soloClaimHolder'));
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

        if (purposeContainer) {
            // temporarily detach from DOM if it is currently placed somewhere else
            // Note: remove() only supported in modern browsers; fallback via parentNode if needed
            try {
                if (purposeContainer.parentNode) purposeContainer.parentNode.removeChild(purposeContainer);
            } catch (e) {
                /* ignore */
            }
            purposeContainer.style.display = 'none';
        }

        cfg.forEach(f => {
            // If the page uses the global purposeSelect (server-rendered), avoid rendering
            // the inline text 'purpose' field inside certFields to prevent duplication.
            if (f.id === 'purpose' && document.getElementById('purposeSelect')) {
                return; // skip rendering this inline purpose field because we have the global select
            }

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

            if (purposeContainer) {
                // Helper normalized key
                // `key` variable is defined earlier as: const key = (value || '').trim().toLowerCase();
                // f.id is the field id being processed
                const fieldId = f.id;
                // Residency -> place after 'residing_years'
                if (key === 'residency' && fieldId === 'residing_years') {
                    // insert right after the current row
                    certFieldsHolder.insertBefore(purposeContainer, row.nextSibling);
                    purposeContainer.style.display = '';
                }
                // Indigency -> place after 'purok'
                else if (key === 'indigency' && fieldId === 'purok') {
                    certFieldsHolder.insertBefore(purposeContainer, row.nextSibling);
                    purposeContainer.style.display = '';
                }
                // Good Moral -> place after 'purok'
                else if (key === 'good moral' && fieldId === 'purok') {
                    certFieldsHolder.insertBefore(purposeContainer, row.nextSibling);
                    purposeContainer.style.display = '';
                }
                // For other types (including 'first time job seeker'), ensure it's hidden/removed
                else {
                    purposeContainer.style.display = 'none';
                }
            }
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

        // Hide payment for indigency and First Time Job Seeker (no-payment flow)
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

        // Ensure purpose controls are (re)initialized after fields rendered
        try { setupPurposeControls(); } catch (e) { /* ignore */ }

        refreshStepCollections();
        updateNavigation();
        setupPaymentControls();
    }

    function refreshFields() { renderCertFields(certInput.value, forSelect.value); }

    refreshFields();

    certInput.addEventListener('input', refreshFields);
    certInput.addEventListener('change', refreshFields);
    certInput.addEventListener('blur', refreshFields);
    forSelect.addEventListener('change', refreshFields);

    setupPaymentControls();
    // initialize purpose controls once (renderCertFields also calls it after updates)
    setupPurposeControls();

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

        // If user is on the summary step, show confirmation modal (but treat no-payment types similarly)
        if (summaryIndex > 0 && currentStep === summaryIndex) {
            const lowType = (certInput.value || '').trim().toLowerCase();
            if (lowType === 'indigency' || lowType === 'first time job seeker') {
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

        // MOVE to next visible step (handles removed payment step gracefully)
        if (currentStep < tSteps) {
            const prevIdx = currentStep - 1;
            if (circleSteps[prevIdx]) circleSteps[prevIdx].classList.add('completed');
            if (stepLabels[prevIdx]) stepLabels[prevIdx].classList.add('completed');
            if (steps[prevIdx]) steps[prevIdx].classList.remove('active-step');

            // increment step
            currentStep++;

            // If the newly targeted step element doesn't exist (e.g., payment was removed), clamp
            if (!steps[currentStep - 1]) {
                currentStep = Math.min(tSteps, currentStep);
            }

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
            const lowType = (certInput.value || '').trim().toLowerCase();
            if (lowType === 'indigency' || lowType === 'first time job seeker') {
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

        // Build rows differently if First Time Job Seeker: *DO NOT* include Requesting For or Purpose
        const rows = [];
        rows.push(['Type of Certification:', certInput.value || '—']);

        if (type !== 'first time job seeker') {
            rows.push(['Requesting For:', forSelect.value === 'myself' ? 'Myself' : 'Others']);
        }

        rows.push(['Full Name:', (document.querySelector('[name="full_name"]')?.value) || '—']);
        rows.push(['Age:', (document.querySelector('[name="age"]')?.value) || '—']);
        rows.push(['Civil Status:', (document.querySelector('[name="civil_status"]')?.value) || '—']);
        rows.push(['Purok:', (document.querySelector('[name="purok"]')?.value) || '—']);

        if (type === 'good moral') {
            const parentSexGM = document.querySelector('[name="parent_sex"]')?.value || window.existingParentSex || window.currentUser.sex || '—';
            const parentAddressGM = document.querySelector('[name="parent_address"]')?.value || window.existingParentAddress || '—';
            rows.push(['Parent Sex:', parentSexGM || '—']);
            rows.push(['Parent Address:', parentAddressGM || '—']);
        }

        if (type === 'solo parent' || type === 'guardianship') {
            const parentSex = document.querySelector('[name="parent_sex"]')?.value || (window.existingParentSex || '') || '—';
            rows.push(['Parent Sex:', parentSex || '—']);
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

        // Add Purpose only when NOT FTJS
        if (type !== 'first time job seeker') {
            // prefer hidden purpose value (works whether inline or global select + hidden input)
            const purposeVal = (document.querySelector('[name="purpose"]')?.value) || (document.getElementById('purposeHidden')?.value) || '—';
            rows.push(['Purpose:', purposeVal || '—']);
        }

        const clientAmount = (hiddenPaymentAmount?.value || '').toString().trim();
        const clientStatus = (hiddenPaymentStatus?.value || '').toString().trim();
        const serverAmount = (window.existingPaymentAmount || '').toString().trim();
        const serverStatus = (window.existingPaymentStatus || '').toString().trim();

        const amountVal = clientAmount || serverAmount || '';
        const statusVal = clientStatus || serverStatus || '';

        // Treat Indigency and First Time Job Seeker as no-payment types in summary
        if (type === 'indigency' || type === 'first time job seeker') {
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
        // treat both indigency and first time job seeker as no-payment
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

            // hide payment step and progress (do NOT remove from DOM)
            const pStep = document.getElementById('paymentStep');
            if (pStep && pStep.style) pStep.style.display = 'none';
            const pProgress = document.querySelector('.payment-progress-step');
            if (pProgress && pProgress.style) pProgress.style.display = 'none';

            // hide any fee/payment related boxes (they may exist)
            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = 'none'; });

            try { setupPurposeControls(); } catch (e) { /* ignore */ }

            refreshStepCollections();
            if (currentStep > totalSteps()) currentStep = totalSteps();
            updateNavigation();
            populateSummary();

            if (progressFill) {
                progressFill.style.width = '100%';
                progressFill.setAttribute('aria-valuenow', '100');
            }
        } else {
            // If payment step was previously hidden/removed, restore display instead of reload
            const pStep = document.getElementById('paymentStep');
            const pProgress = document.querySelector('.payment-progress-step');

            if (pStep && pStep.style && pStep.style.display === 'none') {
                pStep.style.display = '';
            }
            if (pProgress && pProgress.style && pProgress.style.display === 'none') {
                pProgress.style.display = '';
            }

            // Also show fee boxes if previously hidden
            const feeBoxes = document.querySelectorAll('.payment-container, .fee-box, #payment-instructions, .payment-instruction, .payment-btn');
            feeBoxes.forEach(el => { if (el && el.style) el.style.display = ''; });

            // Ensure hidden payment fields have sensible defaults
            if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = (window.existingPaymentMethod || 'Brgy Payment Device');
            if (hiddenPaymentAmount && !hiddenPaymentAmount.value) hiddenPaymentAmount.value = (window.existingPaymentAmount || String(DEFAULT_AMOUNT));
            if (hiddenPaymentStatus && !hiddenPaymentStatus.value) hiddenPaymentStatus.value = (window.existingPaymentStatus || 'Pending');

            refreshStepCollections();
            updateNavigation();
        }
    }

    certInput.addEventListener('change', onCertTypeChange);
    certInput.addEventListener('input', onCertTypeChange);

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

        // If server provided an existing cert type that is a no-payment type, treat same as indigency
        const existingLow = (window.existingCertType || '').toString().toLowerCase();
        if (existingLow === 'indigency' || existingLow === 'first time job seeker') {
            certInput.value = window.existingCertType || 'Indigency';
            renderCertFields(window.existingCertType || 'Indigency', forSelect.value);
            try { setupPurposeControls(); } catch (e) { /* ignore */ }
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
