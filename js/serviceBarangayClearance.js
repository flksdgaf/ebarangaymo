// js/serviceBarangayClearance.js
// Updated to use single fullname field and to show '-' when fields are empty.
// Also keeps claim radio / single-date support and payment/purpose logic intact.
document.addEventListener("DOMContentLoaded", function () {
    let currentStep = window.initialStep || 1;

    const steps = document.querySelectorAll(".step");
    const circleSteps = document.querySelectorAll('.circle');
    const stepLabels = document.querySelectorAll('.step-label');
    const progressFill = document.getElementById('progressFill');
    const totalSteps = circleSteps.length;

    const mainHeader = document.getElementById("mainHeader");
    const subHeader = document.getElementById("subHeader");
    const nextBtn = document.getElementById('nextBtn');
    const backBtn = document.getElementById('backBtn');

    // Payment controls
    const paymentButtons = document.querySelectorAll('.payment-btn');
    const instructionPanels = document.querySelectorAll('.payment-instruction');
    const hiddenPaymentInput = document.getElementById('paymentMethod');

    // Purpose controls (may not exist)
    const purposeSelect = document.getElementById('purposeSelect');
    const purposeOther = document.getElementById('purposeOther');
    const purposeHidden = document.getElementById('purposeHidden');

    // Modals / confirmation
    const validationModalEl = document.getElementById("validationModal");
    const confirmationModalEl = document.getElementById("confirmationModal");
    const validationModal = validationModalEl ? new bootstrap.Modal(validationModalEl) : null;
    const confirmationModal = confirmationModalEl ? new bootstrap.Modal(confirmationModalEl) : null;
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    // form (expects id barangayClearanceForm)
    const form = document.getElementById("barangayClearanceForm");

    // Claim inputs (supports both radio group and single date input)
    const claimOptionsGroup = document.getElementById('claimOptionsGroup'); // optional
    const hiddenClaimDate = document.getElementById('hiddenClaimDate');     // optional (YYYY-MM-DD)
    const hiddenClaimTime = document.getElementById('hiddenClaimTime');     // optional (Morning|Afternoon)
    const claimDateInput = document.getElementById('claimdate');            // optional single date input
    const claimNoticeContainerSelector = '.claim-list'; // used for weekend notice

    // allowed claim dates (from server or fallback)
    let claimAllowedDates = [];
    let claimAllowedSet = new Set();

    // --- Helpers ---
    function toISODate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    }

    function fallbackComputeAllowedDates(count = 3) {
        const now = new Date();
        now.setHours(0,0,0,0);
        const weekday = now.getDay(); // 0=Sun,1=Mon...6=Sat
        const cursor = new Date(now);

        if (weekday === 6) { // Sat -> next Mon
            cursor.setDate(cursor.getDate() + 2);
        } else if (weekday === 0) { // Sun -> next Mon
            cursor.setDate(cursor.getDate() + 1);
        } else {
            // Mon-Fri -> start tomorrow
            cursor.setDate(cursor.getDate() + 1);
        }

        const out = [];
        while (out.length < count) {
            const dow = cursor.getDay();
            if (dow !== 0 && dow !== 6) out.push(toISODate(cursor));
            cursor.setDate(cursor.getDate() + 1);
        }
        return out;
    }

    function initClaimOptionsFromServer() {
        if (window._claimOptions && Array.isArray(window._claimOptions) && window._claimOptions.length > 0) {
            claimAllowedDates = window._claimOptions.map(co => co.date);
        } else {
            claimAllowedDates = fallbackComputeAllowedDates(3);
        }
        claimAllowedSet = new Set(claimAllowedDates);
    }
    initClaimOptionsFromServer();

    function isValidDateString(d) {
        return /^\d{4}-\d{2}-\d{2}$/.test(d);
    }

    function isAllowedClaimDate(d) {
        return isValidDateString(d) && claimAllowedSet.has(d);
    }

    function isValidClaimSeparate(date, part) {
        if (!date || !isValidDateString(date)) return false;
        if (!claimAllowedSet.has(date)) return false;
        if (!['Morning','Afternoon'].includes(part)) return false;
        return true;
    }

    function isValidClaimRaw(raw) {
        if (!raw) return false;
        const parts = raw.split('|');
        if (parts.length !== 2) return false;
        const date = parts[0];
        const part = parts[1];
        if (!isValidDateString(date)) return false;
        if (!claimAllowedSet.has(date)) return false;
        if (!['Morning','Afternoon'].includes(part)) return false;
        return true;
    }

    function getFriendlyClaimLabelFromParts(date, part) {
        if (!date) return '';
        if (window._claimOptions && Array.isArray(window._claimOptions)) {
            const co = window._claimOptions.find(c => c.date === date);
            if (co) {
                const dateLabel = co.label || date;
                const found = Array.isArray(co.parts) ? co.parts.find(p => p.key === part) : null;
                const partLabel = found ? found.label : part;
                return `${dateLabel} - ${partLabel}`;
            }
        }
        try {
            const d = new Date(date + 'T00:00:00');
            const opts = { year: 'numeric', month: 'long', day: 'numeric' };
            const dateLabel = d.toLocaleDateString(undefined, opts);
            return `${dateLabel} - ${part}`;
        } catch (e) {
            return `${date} - ${part}`;
        }
    }

    function getFriendlyClaimLabelSimple(date) {
        if (!date) return '';
        if (window._claimOptions && Array.isArray(window._claimOptions)) {
            const co = window._claimOptions.find(c => c.date === date);
            if (co) return co.label || date;
        }
        try {
            const d = new Date(date + 'T00:00:00');
            const opts = { year: 'numeric', month: 'long', day: 'numeric' };
            return d.toLocaleDateString(undefined, opts);
        } catch (e) {
            return date;
        }
    }

    // Weekend notice if request made on weekend (mirrors server behavior)
    function showWeekendNoticeIfApplicable() {
        try {
            const container = document.querySelector(claimNoticeContainerSelector);
            if (!container) return;

            const today = new Date();
            today.setHours(0,0,0,0);
            const isWeekendRequest = (today.getDay() === 6 || today.getDay() === 0);

            const firstDate = claimAllowedDates && claimAllowedDates.length ? claimAllowedDates[0] : null;
            let firstDateObj = null;
            if (firstDate) firstDateObj = new Date(firstDate + 'T00:00:00');

            const existingNotice = document.getElementById('claim-weekend-notice');
            if (existingNotice) existingNotice.remove();

            if (isWeekendRequest && firstDateObj && firstDateObj.getDay() === 1) { // Monday
                let friendly = firstDate;
                if (window._claimOptions && Array.isArray(window._claimOptions)) {
                    const found = window._claimOptions.find(co => co.date === firstDate);
                    if (found && found.label) friendly = found.label;
                } else {
                    const opts = { year:'numeric', month:'long', day:'numeric' };
                    friendly = firstDateObj.toLocaleDateString(undefined, opts);
                }

                const note = document.createElement('div');
                note.id = 'claim-weekend-notice';
                note.className = 'alert alert-info small mt-2';
                note.textContent = `Note: You are submitting this request on a weekend. Available claim slots start on Monday (${friendly}) during barangay hours (Mon–Fri, 8:00 AM – 5:00 PM).`;
                container.appendChild(note);
            }
        } catch (e) {
            console.warn('claim notice render error', e);
        }
    }

    showWeekendNoticeIfApplicable();

    // Single-date input legacy handling (keeps a claim_time hidden if the single input contains "YYYY-MM-DD|Morning")
    function ensureClaimTimeHiddenForSingleInput() {
        if (!claimDateInput) return null;
        let el = document.getElementById('claimTimeHidden');
        if (!el) {
            el = document.createElement('input');
            el.type = 'hidden';
            el.id = 'claimTimeHidden';
            el.name = 'claim_time';
            if (form) form.appendChild(el);
        }
        const currentVal = (claimDateInput.value || '').trim();
        const rawAttr = claimDateInput.getAttribute('value') || '';
        if (!isValidDateString(currentVal) && rawAttr && rawAttr.indexOf('|') !== -1) {
            const parts = rawAttr.split('|');
            if (parts.length === 2 && isValidDateString(parts[0])) {
                try { claimDateInput.value = parts[0]; } catch (e) {}
                el.value = parts[1].trim();
            }
        } else if (rawAttr && rawAttr.indexOf('|') !== -1 && !el.value) {
            const parts = rawAttr.split('|');
            if (parts.length === 2) el.value = parts[1].trim();
        }
        return el;
    }

    (function configureSingleDateInput() {
        if (!claimDateInput) return;
        if (claimAllowedDates.length > 0) {
            claimDateInput.setAttribute('min', claimAllowedDates[0]);
            claimDateInput.placeholder = claimAllowedDates[0];
        }
        claimDateInput.addEventListener('change', function () {
            const v = (claimDateInput.value || '').trim();
            if (v && !isValidDateString(v)) {
                claimDateInput.classList.add('is-invalid');
            } else {
                claimDateInput.classList.remove('is-invalid');
            }
            populateSummary();
        });
        ensureClaimTimeHiddenForSingleInput();
    })();

    // Radio handlers (business-style claim grid)
    function setHiddenFromRadio(radio) {
        if (!radio) return;
        const d = radio.dataset && radio.dataset.date ? radio.dataset.date : null;
        const p = radio.dataset && radio.dataset.part ? radio.dataset.part : null;

        if (d && p) {
            if (hiddenClaimDate) hiddenClaimDate.value = d;
            if (hiddenClaimTime) hiddenClaimTime.value = p;
            return;
        }

        if (radio.value && radio.value.indexOf('|') !== -1) {
            const parts = radio.value.split('|');
            if (parts.length === 2) {
                if (hiddenClaimDate) hiddenClaimDate.value = parts[0];
                if (hiddenClaimTime) hiddenClaimTime.value = parts[1];
            }
        }
    }

    function attachClaimRadioListeners() {
        if (!claimOptionsGroup) return;
        // attach keyboard/click handlers and change listener (same behavior as before)
        claimOptionsGroup.querySelectorAll('label').forEach(function (lbl) {
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

            const inputInside = lbl.querySelector('input[type="radio"]');
            if (inputInside) {
                inputInside.addEventListener('focus', function () {
                    const parent = inputInside.closest('.claim-card');
                    if (parent) parent.classList.add('outlined');
                });
                inputInside.addEventListener('blur', function () {
                    const parent = inputInside.closest('.claim-card');
                    if (parent) parent.classList.remove('outlined');
                });
            }
        });

        function clearCardStates() {
            claimOptionsGroup.querySelectorAll('.claim-card').forEach(card => {
                card.classList.remove('active', 'outlined', 'invalid');
                card.setAttribute('aria-pressed', 'false');
                card.removeAttribute('aria-invalid');
            });
        }

        claimOptionsGroup.addEventListener('change', function () {
            clearCardStates();

            const checkedNew = claimOptionsGroup.querySelector('input[name="claim_slot"]:checked');
            const checkedLegacy = claimOptionsGroup.querySelector('input[name="claim_date"]:checked');
            const checked = checkedNew || checkedLegacy;

            if (checked) {
                const parentLabel = checked.closest('label');
                if (parentLabel) {
                    parentLabel.classList.add('active');
                    parentLabel.setAttribute('aria-pressed', 'true');
                    parentLabel.removeAttribute('aria-invalid');
                }

                setHiddenFromRadio(checked);

                const dateVal = hiddenClaimDate ? hiddenClaimDate.value : null;
                const timeVal = hiddenClaimTime ? hiddenClaimTime.value : null;
                const summaryEl = document.getElementById('summaryClaimDate');
                if (summaryEl) {
                    if (isValidClaimSeparate(dateVal, timeVal)) summaryEl.textContent = getFriendlyClaimLabelFromParts(dateVal, timeVal);
                    else {
                        if (checked.value && checked.value.indexOf('|') !== -1) {
                            const parts = checked.value.split('|');
                            if (parts.length === 2) summaryEl.textContent = getFriendlyClaimLabelFromParts(parts[0], parts[1]);
                            else summaryEl.textContent = parts[0] || '-';
                        } else {
                            summaryEl.textContent = (dateVal ? getFriendlyClaimLabelSimple(dateVal) : '-');
                        }
                    }
                }
            }
        });

        // Pre-select existing claim logic:
        // Prefer server-provided object window._existingClaimObj {date, part}
        if (window._existingClaimObj && window._existingClaimObj.date) {
            const d = window._existingClaimObj.date;
            const p = window._existingClaimObj.part;
            // try to find new-style radio first
            const desiredNew = claimOptionsGroup.querySelector(`input[name="claim_slot"][data-date="${d}"][data-part="${p}"]`);
            if (desiredNew) {
                desiredNew.checked = true;
                desiredNew.dispatchEvent(new Event('change', { bubbles: true }));
            } else {
                // try legacy radio value match
                const legacyVal = `${d}|${p}`;
                const desiredLegacy = claimOptionsGroup.querySelector(`input[name="claim_date"][value="${legacyVal}"]`);
                if (desiredLegacy) {
                    desiredLegacy.checked = true;
                    desiredLegacy.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    // fallback: match by date only (prefer morning)
                    const dateMatchNew = claimOptionsGroup.querySelector(`input[name="claim_slot"][data-date="${d}"]`);
                    if (dateMatchNew) {
                        dateMatchNew.checked = true;
                        dateMatchNew.dispatchEvent(new Event('change', { bubbles: true }));
                    } else {
                        // best-effort legacy partial match
                        const legacyCandidates = Array.from(claimOptionsGroup.querySelectorAll('input[name="claim_date"]'));
                        for (const c of legacyCandidates) {
                            if (c.value && c.value.indexOf(d) === 0) {
                                c.checked = true;
                                c.dispatchEvent(new Event('change', { bubbles: true }));
                                break;
                            }
                        }
                    }
                }
            }
        } else {
            // DEFAULT SELECTION: Always select first radio (Morning of first available date)
            const firstRadio = claimOptionsGroup.querySelector('input[name="claim_slot"], input[name="claim_date"]');
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

    attachClaimRadioListeners();

    // Payment controls
    function setupPaymentControls() {
        if (!paymentButtons || paymentButtons.length === 0) {
            if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = 'Brgy Payment Device';
            return;
        }

        const initialMethod = hiddenPaymentInput ? (hiddenPaymentInput.value || '') : '';

        paymentButtons.forEach(btn => {
            if (initialMethod && btn.dataset.method === initialMethod) {
                btn.classList.add('active');
                instructionPanels.forEach(p => { p.classList.toggle('d-none', p.dataset.method !== initialMethod); });
            }
            btn.addEventListener('click', function () {
                paymentButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                const method = this.dataset.method;
                if (hiddenPaymentInput) hiddenPaymentInput.value = method;
                instructionPanels.forEach(p => p.classList.toggle('d-none', p.dataset.method !== method));
            });
        });

        if (!initialMethod) {
            const def = Array.from(paymentButtons).find(b => b.dataset.method === 'Brgy Payment Device');
            if (def) def.click();
        }
    }

    // Purpose controls
    function setupPurposeControls() {
        if (!purposeSelect || !purposeHidden) return;

        if (purposeHidden.value) {
            const hiddenVal = purposeHidden.value.trim();
            const match = Array.from(purposeSelect.options).some(o => o.value === hiddenVal);
            if (!match) {
                const othersOpt = Array.from(purposeSelect.options).find(o => o.text === 'Others' || o.value === 'Others');
                if (othersOpt) { othersOpt.selected = true; if (purposeOther) purposeOther.value = hiddenVal; }
            } else purposeSelect.value = hiddenVal;
        } else purposeHidden.value = purposeSelect.value || '';

        const togglePurposeOther = () => {
            if (purposeSelect.value === 'Others') {
                purposeOther && purposeOther.classList.remove('d-none');
                purposeOther && (purposeOther.required = true);
                if (purposeHidden && purposeHidden.value && purposeHidden.value !== 'Others') {
                    if (purposeOther && !purposeOther.value) purposeOther.value = purposeHidden.value;
                }
                if (purposeOther && purposeOther.value.trim()) purposeHidden.value = purposeOther.value.trim();
                else if (purposeHidden && !purposeHidden.value.trim()) purposeHidden.value = 'Others';
            } else {
                purposeOther && purposeOther.classList.add('d-none');
                purposeOther && (purposeOther.required = false);
                purposeOther && purposeOther.classList.remove('is-invalid');
                purposeHidden.value = purposeSelect.value || '';
            }
        };

        purposeSelect.addEventListener('change', function () { togglePurposeOther(); populateSummary(); });
        if (purposeOther) purposeOther.addEventListener('input', function () { purposeHidden.value = this.value.trim() || 'Others'; populateSummary(); });
        togglePurposeOther();
    }

    function syncPurposeHidden() {
        if (!purposeSelect || !purposeHidden) return;
        if (purposeSelect.value === 'Others') {
            if (purposeOther && purposeOther.value.trim()) purposeHidden.value = purposeOther.value.trim();
            else purposeHidden.value = 'Others';
        } else purposeHidden.value = purposeSelect.value;
    }

    // Summary population — uses single fullname and shows '-' for empty values.
    function populateSummary() {
        const get = id => (document.getElementById(id) ? (document.getElementById(id).value || '') : '');
        const setTextOrDash = (id, val) => {
            const el = document.getElementById(id);
            if (!el) return;
            if (val === null || val === undefined || (typeof val === 'string' && val.trim() === '')) el.textContent = '-';
            else el.textContent = val;
        };

        const fullname = get('fullname');
        const street = get('street');
        const purok = get('purok');
        const age = get('age');
        const birthdate = get('birthdate');
        const birthplace = get('birthplace');
        const marital = get('maritalstatus');
        const ctc = get('ctcnumber');

        setTextOrDash('summaryFullName', fullname);
        setTextOrDash('summaryStreet', street);
        setTextOrDash('summaryPurok', purok);

        const birthAge = birthdate ? (birthdate + (age ? (' / ' + age) : '')) : (age ? ('' + age) : '');
        setTextOrDash('summaryBirthAge', birthAge);

        setTextOrDash('summaryBirthplace', birthplace);
        setTextOrDash('summaryMaritalStatus', marital);
        setTextOrDash('summaryCTC', ctc);

        // Claim resolution (supports hiddenClaimDate+hiddenClaimTime, single date input, or legacy radio)
        let claimLabel = '';

        if (hiddenClaimDate && hiddenClaimDate.value) {
            const d = hiddenClaimDate.value.trim();
            const p = (hiddenClaimTime && hiddenClaimTime.value) ? hiddenClaimTime.value.trim() : null;
            if (p && isValidClaimSeparate(d, p)) {
                claimLabel = getFriendlyClaimLabelFromParts(d, p);
            } else if (isValidDateString(d)) {
                claimLabel = getFriendlyClaimLabelSimple(d);
            }
        }

        if (!claimLabel && claimDateInput && (claimDateInput.value || '').trim()) {
            const d = (claimDateInput.value || '').trim();
            if (isValidDateString(d)) {
                claimLabel = getFriendlyClaimLabelSimple(d);
                const claimTimeHidden = document.getElementById('claimTimeHidden');
                if (claimTimeHidden && claimTimeHidden.value) claimLabel += ' - ' + claimTimeHidden.value;
            }
        }

        if (!claimLabel) {
            const legacyChecked = document.querySelector('input[name="claim_date"]:checked');
            if (legacyChecked && legacyChecked.value) {
                const parts = legacyChecked.value.split('|');
                if (parts.length === 2 && isValidDateString(parts[0])) claimLabel = getFriendlyClaimLabelFromParts(parts[0], parts[1]);
                else if (isValidDateString(legacyChecked.value)) claimLabel = getFriendlyClaimLabelSimple(legacyChecked.value);
            }
        }

        setTextOrDash('summaryClaimDate', claimLabel);

        // Payment & Purpose
        const payment = hiddenPaymentInput ? (hiddenPaymentInput.value || '') : '';
        setTextOrDash('summaryPaymentMethod', payment);

        let purposeVal = '';
        if (purposeHidden && purposeHidden.value) purposeVal = purposeHidden.value;
        else if (purposeSelect) purposeVal = (purposeSelect.value === 'Others' ? (purposeOther && purposeOther.value.trim() ? purposeOther.value.trim() : '') : purposeSelect.value);
        setTextOrDash('summaryPurpose', purposeVal);
    }

    // Live summary updates
    function attachLiveSummaryUpdates() {
        const ids = ['fullname','street','purok','birthdate','age','birthplace','maritalstatus','ctcnumber'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', populateSummary);
            el.addEventListener('change', populateSummary);
        });

        if (hiddenClaimDate) hiddenClaimDate.addEventListener('change', populateSummary);
        if (hiddenClaimTime) hiddenClaimTime.addEventListener('change', populateSummary);
        if (claimDateInput) claimDateInput.addEventListener('change', populateSummary);

        if (hiddenPaymentInput) hiddenPaymentInput.addEventListener('change', populateSummary);
        if (purposeSelect) purposeSelect.addEventListener('change', populateSummary);
        if (purposeOther) purposeOther.addEventListener('input', populateSummary);

        // initial fill
        populateSummary();
    }

    attachLiveSummaryUpdates();

    // Initial render + other UI setup
    showStep(currentStep);
    setupPaymentControls();
    setupPurposeControls();

    // Navigation and helpers
    function showStep(n) {
        steps.forEach((s, idx) => {
            s.classList.remove('active-step');
            if (idx === n - 1) s.classList.add('active-step');
        });
        circleSteps.forEach((c, idx) => {
            c.classList.remove('active','completed');
            if (idx < n - 1) c.classList.add('completed');
            if (idx === n - 1) c.classList.add('active');
        });
        const percent = ((n - 1) / (totalSteps - 1)) * 100;
        if (progressFill) progressFill.style.width = percent + '%';
        updateNavigation();
    }

    function goToStep(n) {
        if (n < 1) n = 1;
        if (n > totalSteps) n = totalSteps;

        steps.forEach((s, idx) => {
            s.classList.remove('active-step');
            if (idx === n - 1) s.classList.add('active-step');
        });

        circleSteps.forEach((c, idx) => {
            c.classList.remove('active', 'completed');
            if (idx < n - 1) c.classList.add('completed');
            if (idx === n - 1) c.classList.add('active');
        });

        stepLabels.forEach((l, idx) => {
            l.classList.remove('active', 'completed');
            if (idx < n - 1) l.classList.add('completed');
            if (idx === n - 1) l.classList.add('active');
        });

        const percent = ((n - 1) / (totalSteps - 1)) * 100;
        if (progressFill) progressFill.style.width = percent + '%';

        currentStep = n;
        updateNavigation();
        if (currentStep === 3) populateSummary();
    }

    // ADD THIS LINE HERE:
    window.goToStep = goToStep;

    function updateNavigation() {
        if (backBtn) backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === 1) {
            if (mainHeader) mainHeader.textContent = "APPLICATION FORM";
            if (subHeader) subHeader.textContent = "Provide the necessary details to request a Clearance.";
            if (nextBtn) nextBtn.textContent = "NEXT >";
        } else if (currentStep === 2) {
            if (mainHeader) mainHeader.textContent = "PAYMENT";
            if (subHeader) subHeader.textContent = "Settle your payment for the Clearance.";
            if (nextBtn) nextBtn.textContent = "NEXT >";
        } else if (currentStep === 3) {
            if (mainHeader) mainHeader.textContent = "REVIEW & CONFIRMATION";
            if (subHeader) subHeader.textContent = "Please review all information before submitting.";
            if (nextBtn) nextBtn.textContent = "SUBMIT";
        } else if (currentStep === 4) {
            if (mainHeader && mainHeader.parentNode) mainHeader.remove();
            if (subHeader && subHeader.parentNode) subHeader.remove();
            const mainHr = document.getElementById('mainHr');
            if (mainHr && mainHr.parentNode) mainHr.remove();

            if (backBtn) backBtn.style.visibility = 'hidden';
            if (nextBtn) {
                nextBtn.textContent = "Back to Home";
                const newNext = nextBtn.cloneNode(true);
                nextBtn.parentNode.replaceChild(newNext, nextBtn);
                newNext.addEventListener('click', () => {
                    window.location.href = 'userPanel.php?page=userDashboard';
                });
            }
        }
    }

    // Step 1 validation
    function validateStep1() {
        let ok = true;
        document.querySelectorAll(".step.active-step input[required], .step.active-step select[required], .step.active-step textarea[required]")
        .forEach(field => {
            if (field.type === 'file') {
                if (!field.files || field.files.length === 0) {
                    if (field.hasAttribute('required')) { ok = false; field.classList.add('is-invalid'); }
                    else field.classList.remove('is-invalid');
                } else field.classList.remove('is-invalid');
                return;
            }
            const val = (field.value || '').toString().trim();
            if (!val) { ok = false; field.classList.add('is-invalid'); } else field.classList.remove('is-invalid');
        });

        // purpose validation (if present)
        if (purposeSelect) {
            const selectVal = (purposeSelect.value || '').trim();
            
            // Check if value is empty OR is "Select Purpose"
            if (!selectVal || selectVal === 'Select Purpose') { 
                ok = false; 
                purposeSelect.classList.add('is-invalid'); 
            } else {
                purposeSelect.classList.remove('is-invalid');
            }

            if (selectVal === 'Others') {
                const oth = (purposeOther && purposeOther.value || '').trim();
                if (!oth) { ok = false; purposeOther && purposeOther.classList.add('is-invalid'); }
                else purposeOther && purposeOther.classList.remove('is-invalid');
            }

            if (purposeHidden && (!purposeHidden.value || !purposeHidden.value.trim() || purposeHidden.value === 'Select Purpose')) {
                ok = false; purposeSelect.classList.add('is-invalid');
            }
        }

        return ok;
    }

    // Navigation handlers
    nextBtn && nextBtn.addEventListener('click', function () {
        if (currentStep === totalSteps) {
            window.location.href = 'userPanel.php?page=userDashboard';
            return;
        }

        if (currentStep === 1) {
            if (!validateStep1()) {
                if (validationModal) validationModal.show();
                return;
            }
        }

        if (currentStep === 2) {
            if (!hiddenPaymentInput || !hiddenPaymentInput.value) {
                if (validationModal) validationModal.show();
                return;
            }
            
            // === GCash Payment Handling ===
            if (hiddenPaymentInput.value === 'GCash') {
                // Submit form directly - server will redirect to GCash
                if (form) {
                    nextBtn.disabled = true;
                    nextBtn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Processing...';
                    form.submit();
                    return; // Stop here, don't proceed to step 3
                }
            }
            // === End GCash Handling ===
        }

        if (currentStep === 3) {
            populateSummary();
            if (confirmationModal) {
                confirmationModal.show();
                return;
            }
        }

        goToStep(currentStep + 1);
    });

    backBtn && backBtn.addEventListener('click', function () {
        if (currentStep > 1) goToStep(currentStep - 1);
    });

    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', function () {
            syncPurposeHidden();
            if (hiddenClaimDate && hiddenClaimTime) {
                const cd = hiddenClaimDate.value || null;
                const ct = hiddenClaimTime.value || null;
                if (cd && ct && !isValidClaimSeparate(cd, ct)) {
                    if (validationModal) validationModal.show();
                    return;
                }
            }
            if (claimDateInput && claimDateInput.value) {
                const cd = claimDateInput.value.trim();
                if (!isValidDateString(cd)) {
                    if (validationModal) validationModal.show();
                    return;
                }
            }
            if (form) form.submit();
        });
    }

    // initial step setup
    (function initialStepSetup() {
        steps.forEach(s => s.classList.remove('active-step'));
        circleSteps.forEach(c => c.classList.remove('active','completed'));
        stepLabels.forEach(l => l.classList.remove('active','completed'));

        const idx = currentStep - 1;
        if (steps[idx]) steps[idx].classList.add('active-step');
        circleSteps.forEach((c, i) => {
            if (i < idx) c.classList.add('completed');
            if (i === idx) c.classList.add('active');
        });
        stepLabels.forEach((l, i) => {
            if (i < idx) l.classList.add('completed');
            if (i === idx) l.classList.add('active');
        });

        const percent = ((currentStep - 1) / (totalSteps - 1)) * 100;
        if (progressFill) progressFill.style.width = percent + '%';
        updateNavigation();
    })();

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
            const steps = document.querySelectorAll('.step');
            const activeStep = Array.from(steps).findIndex(s => s.classList.contains('active-step'));
            isOnPaymentStep = (activeStep === 1); // Step 2 (index 1) is payment
        });
        
        document.querySelectorAll('.step').forEach(step => {
            observer.observe(step, { attributes: true, attributeFilter: ['class'] });
        });
        
        // Warn on back button
        window.addEventListener('popstate', function(e) {
            if (isOnPaymentStep) {
                const paymentMethod = document.getElementById('paymentMethod');
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
