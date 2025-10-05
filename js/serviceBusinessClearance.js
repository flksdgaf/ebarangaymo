// js/serviceBusinessClearance.js
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

    // form
    const form = document.getElementById("businessClearanceForm");

    // Claim options group & hidden fields (updated naming)
    const claimOptionsGroup = document.getElementById('claimOptionsGroup');
    const hiddenClaimDate = document.getElementById('hiddenClaimDate');
    const hiddenClaimTime = document.getElementById('hiddenClaimTime');
    const claimNoticeContainerSelector = '.claim-list'; // where to append notices
    let claimAllowedDates = []; // array of 'YYYY-MM-DD'
    let claimAllowedSet = new Set();

    // --- Helpers ---
    function toISODate(d) {
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, '0');
        const dd = String(d.getDate()).padStart(2, '0');
        return `${y}-${m}-${dd}`;
    }

    // Fallback computation mirrors server:
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

    // Initialize allowed dates from server-injected window._claimOptions or fallback
    function initClaimOptionsFromServer() {
        if (window._claimOptions && Array.isArray(window._claimOptions) && window._claimOptions.length > 0) {
            claimAllowedDates = window._claimOptions.map(co => co.date);
        } else {
            claimAllowedDates = fallbackComputeAllowedDates(3);
        }
        claimAllowedSet = new Set(claimAllowedDates);
    }
    initClaimOptionsFromServer();

    // Determine whether the server started options on Monday due to weekend request
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
                // Build friendly label from server options if present
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

    function isValidDateString(d) {
        return /^\d{4}-\d{2}-\d{2}$/.test(d);
    }

    // Validation for separated hidden fields
    function isValidClaimSeparate(date, part) {
        if (!date || !isValidDateString(date)) return false;
        if (!claimAllowedSet.has(date)) return false;
        if (!['Morning','Afternoon'].includes(part)) return false;
        return true;
    }

    // If legacy raw "YYYY-MM-DD|Morning" format appears, validate as well.
    function isValidClaimRaw(raw) {
        if (!raw) return false;
        const parts = raw.split('|');
        if (parts.length !== 2) return false;
        const date = parts[0];
        const part = parts[1];
        if (!/^\d{4}-\d{2}-\d{2}$/.test(date)) return false;
        if (!claimAllowedSet.has(date)) return false;
        if (!['Morning','Afternoon'].includes(part)) return false;
        return true;
    }

    // Friendly label for the summary: prefer server labels (window._claimOptions)
    function getFriendlyClaimLabelFromParts(date, part) {
        if (!date) return '-';
        if (window._claimOptions && Array.isArray(window._claimOptions)) {
            const co = window._claimOptions.find(c => c.date === date);
            if (co) {
                const dateLabel = co.label || date;
                const found = Array.isArray(co.parts) ? co.parts.find(p => p.key === part) : null;
                const partLabel = found ? found.label : part;
                return `${dateLabel} - ${partLabel}`;
            }
        }
        // fallback
        try {
            const d = new Date(date + 'T00:00:00');
            const opts = { year: 'numeric', month: 'long', day: 'numeric' };
            const dateLabel = d.toLocaleDateString(undefined, opts);
            return `${dateLabel} - ${part}`;
        } catch (e) {
            return `${date} - ${part}`;
        }
    }

    // Helper: set hidden fields from a radio (data-* or legacy value)
    function setHiddenFromRadio(radio) {
        if (!radio) return;
        // Prefer data attributes (new)
        const d = radio.dataset && radio.dataset.date ? radio.dataset.date : null;
        const p = radio.dataset && radio.dataset.part ? radio.dataset.part : null;

        if (d && p) {
            if (hiddenClaimDate) hiddenClaimDate.value = d;
            if (hiddenClaimTime) hiddenClaimTime.value = p;
            return;
        }

        // Legacy fallback: value = "YYYY-MM-DD|Morning"
        if (radio.value && radio.value.indexOf('|') !== -1) {
            const parts = radio.value.split('|');
            if (parts.length === 2) {
                if (hiddenClaimDate) hiddenClaimDate.value = parts[0];
                if (hiddenClaimTime) hiddenClaimTime.value = parts[1];
            }
        }
    }

    // Attach listeners to claim radios/labels and handle UI update
    function attachClaimRadioListeners() {
        if (!claimOptionsGroup) return;

        // support both new radios (name=claim_slot) and legacy (name=claim_date)
        const selectorNew = 'input[name="claim_slot"]';
        const selectorLegacy = 'input[name="claim_date"]';
        const radios = Array.from(claimOptionsGroup.querySelectorAll(selectorNew)).length ?
                        Array.from(claimOptionsGroup.querySelectorAll(selectorNew)) :
                        Array.from(claimOptionsGroup.querySelectorAll(selectorLegacy));

        // Clear state utility
        function clearCardStates() {
            claimOptionsGroup.querySelectorAll('.claim-card').forEach(card => {
                card.classList.remove('active', 'outlined', 'invalid');
                card.setAttribute('aria-pressed', 'false');
                card.removeAttribute('aria-invalid');
                card.style.outline = '';
            });
        }

        // Add keyboard & click handlers to labels for accessibility
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
            lbl.addEventListener('click', function (ev) {
                const input = lbl.querySelector('input[type="radio"]');
                if (!input) return;
                if (!input.checked) {
                    input.checked = true;
                    input.dispatchEvent(new Event('change', { bubbles: true }));
                } else {
                    // still trigger change to update UI
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

        // Central change handler
        claimOptionsGroup.addEventListener('change', function (e) {
            clearCardStates();

            // search for newly checked radio from either naming scheme
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

                // set hidden fields for submission
                setHiddenFromRadio(checked);

                // update textual summary
                const dateVal = hiddenClaimDate ? hiddenClaimDate.value : null;
                const timeVal = hiddenClaimTime ? hiddenClaimTime.value : null;
                const summaryEl = document.getElementById('summaryClaimDate');
                if (summaryEl) {
                    if (isValidClaimSeparate(dateVal, timeVal)) summaryEl.textContent = getFriendlyClaimLabelFromParts(dateVal, timeVal);
                    else {
                        // fallback: if legacy radio available, try friendly label from radio.value
                        if (checked.value && checked.value.indexOf('|') !== -1) {
                            const parts = checked.value.split('|');
                            if (parts.length === 2) summaryEl.textContent = getFriendlyClaimLabelFromParts(parts[0], parts[1]);
                            else summaryEl.textContent = '-';
                        } else summaryEl.textContent = (dateVal ? `${dateVal} - ${timeVal}` : '-');
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
                        const dateMatchLegacy = claimOptionsGroup.querySelector(`input[name="claim_date"][value^="${d}"]`);
                        if (dateMatchLegacy) {
                            dateMatchLegacy.checked = true;
                            dateMatchLegacy.dispatchEvent(new Event('change', { bubbles: true }));
                        }
                    }
                }
            }
        } else {
            // If server didn't provide existing selection, ensure first radio selected by default
            const anyCheckedNew = claimOptionsGroup.querySelector('input[name="claim_slot"]:checked');
            const anyCheckedLegacy = claimOptionsGroup.querySelector('input[name="claim_date"]:checked');
            if (!anyCheckedNew && !anyCheckedLegacy) {
                const firstRadio = claimOptionsGroup.querySelector('input[name="claim_slot"], input[name="claim_date"]');
                if (firstRadio) {
                    firstRadio.checked = true;
                    firstRadio.dispatchEvent(new Event('change', { bubbles: true }));
                }
            } else {
                // still ensure hidden fields are in sync if radio already checked
                const already = claimOptionsGroup.querySelector('input[name="claim_slot"]:checked') || claimOptionsGroup.querySelector('input[name="claim_date"]:checked');
                if (already) setHiddenFromRadio(already);
            }
        }
    }

    // Initialize claim area behaviors
    showWeekendNoticeIfApplicable();
    attachClaimRadioListeners();

    // Initial render + other UI setup
    showStep(currentStep);
    setupPaymentControls();
    setupPurposeControls();
    attachLiveSummaryUpdates();

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
            // final guard: ensure hidden claim fields exist & valid
            const cd = hiddenClaimDate ? hiddenClaimDate.value : null;
            const ct = hiddenClaimTime ? hiddenClaimTime.value : null;
            if (!isValidClaimSeparate(cd, ct)) {
                if (validationModal) validationModal.show();
                return;
            }
            if (form) form.submit();
        });
    }

    // --- Navigation helpers ---
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

    function updateNavigation() {
        if (backBtn) backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        if (currentStep === 1) {
            if (mainHeader) mainHeader.textContent = "APPLICATION FORM";
            if (subHeader) subHeader.textContent = "Provide the necessary details to request a Business Clearance.";
            if (nextBtn) nextBtn.textContent = "NEXT >";
        } else if (currentStep === 2) {
            if (mainHeader) mainHeader.textContent = "PAYMENT";
            if (subHeader) subHeader.textContent = "Settle your payment for the Business Clearance.";
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

    // Step 1 validation (check required fields + separated claim fields)
    function validateStep1() {
        let ok = true;
        // New required set: full_name replaces visible firstname/lastname/middlename; barangay/municipality/province removed
        const requiredIds = [
            'full_name','purok','age','maritalstatus',
            'business_name','business_type','address'
        ];

        requiredIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            if (el.type === 'file') {
                if (!el.files || el.files.length === 0) {
                    if (el.hasAttribute('required')) { ok = false; el.classList.add('is-invalid'); }
                    else el.classList.remove('is-invalid');
                } else el.classList.remove('is-invalid');
                return;
            }
            const val = (el.value || '').toString().trim();
            if (!val) { ok = false; el.classList.add('is-invalid'); } else el.classList.remove('is-invalid');
        });

        // validate claim selection via hidden fields first (preferred)
        const dateVal = hiddenClaimDate ? hiddenClaimDate.value : null;
        const timeVal = hiddenClaimTime ? hiddenClaimTime.value : null;

        if (!isValidClaimSeparate(dateVal, timeVal)) {
            // fallback: check legacy raw input value (checked radio)
            const legacyChecked = document.querySelector('input[name="claim_date"]:checked');
            if (!legacyChecked || !isValidClaimRaw(legacyChecked.value)) {
                ok = false;
                // show red outline on first card to indicate problem
                if (claimOptionsGroup) {
                    const firstCard = claimOptionsGroup.querySelector('.claim-card');
                    if (firstCard) {
                        firstCard.setAttribute('aria-invalid', 'true');
                        firstCard.style.outline = '1px solid #dc3545';
                    }
                }
            }
        }

        // purpose validation (if present)
        if (purposeSelect) {
            const selectVal = (purposeSelect.value || '').trim();
            if (!selectVal) { ok = false; purposeSelect.classList.add('is-invalid'); }
            else purposeSelect.classList.remove('is-invalid');

            if (selectVal === 'Others') {
                const oth = (purposeOther && purposeOther.value || '').trim();
                if (!oth) { ok = false; purposeOther && purposeOther.classList.add('is-invalid'); }
                else purposeOther && purposeOther.classList.remove('is-invalid');
            }

            if (purposeHidden && (!purposeHidden.value || !purposeHidden.value.trim())) {
                ok = false; purposeSelect.classList.add('is-invalid');
            }
        }

        return ok;
    }

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

    // utility: return '-' when value empty
    function asDash(v) {
        if (v === null || v === undefined) return '-';
        const s = String(v).trim();
        return s === '' ? '-' : s;
    }

    // Summary population (adjusted to new single full_name field)
    function populateSummary() {
        const get = id => (document.getElementById(id) ? document.getElementById(id).value : '');

        const fullName = get('full_name');
        const purok = get('purok');
        const age = get('age');
        const marital = get('maritalstatus');
        const business = get('business_name');
        const btype = get('business_type');
        const baddr = get('address');
        const ctc = get('ctcnumber');
        const payment = hiddenPaymentInput ? (hiddenPaymentInput.value || '') : '';

        // New summary structure (match serviceBusinessClearance.php)
        const sFull = document.getElementById('summaryFullName');
        const sPurok = document.getElementById('summaryPurok');
        const sAgeMarital = document.getElementById('summaryAgeMarital');
        const sBusiness = document.getElementById('summaryBusiness');
        const sBusinessAddr = document.getElementById('summaryBusinessAddress');
        const sCTC = document.getElementById('summaryCTC');
        const sClaim = document.getElementById('summaryClaimDate');
        const sPayment = document.getElementById('summaryPaymentMethod');

        if (sFull) sFull.textContent = asDash(fullName);
        if (sPurok) sPurok.textContent = asDash(purok);
        if (sAgeMarital) {
            const agePart = age ? String(age) : '-';
            const maritalPart = marital ? marital : '-';
            // If both are '-', show single '-'
            if (agePart === '-' && maritalPart === '-') sAgeMarital.textContent = '-';
            else sAgeMarital.textContent = (age ? age : '-') + (marital ? (' / ' + marital) : '');
        }
        if (sBusiness) {
            if (!business && !btype) sBusiness.textContent = '-';
            else sBusiness.textContent = (business ? business : '-') + (btype ? (' / ' + btype) : '');
        }
        if (sBusinessAddr) sBusinessAddr.textContent = asDash(baddr);
        if (sCTC) sCTC.textContent = asDash(ctc);

        // Use hidden values (preferred)
        const dateVal = hiddenClaimDate ? hiddenClaimDate.value : null;
        const timeVal = hiddenClaimTime ? hiddenClaimTime.value : null;
        let claimLabel = '-';
        if (isValidClaimSeparate(dateVal, timeVal)) {
            claimLabel = getFriendlyClaimLabelFromParts(dateVal, timeVal);
        } else {
            // fallback to checked radio (legacy)
            const legacyChecked = document.querySelector('input[name="claim_date"]:checked');
            if (legacyChecked && legacyChecked.value && legacyChecked.value.indexOf('|') !== -1) {
                const parts = legacyChecked.value.split('|');
                if (parts.length === 2) claimLabel = getFriendlyClaimLabelFromParts(parts[0], parts[1]);
            }
        }
        if (sClaim) sClaim.textContent = claimLabel;
        if (sPayment) sPayment.textContent = asDash(payment);

        // purpose (if exists)
        const sPurpose = document.getElementById('summaryPurpose');
        if (sPurpose) {
            let purposeVal = '';
            if (purposeHidden && purposeHidden.value) purposeVal = purposeHidden.value;
            else if (purposeSelect) purposeVal = purposeSelect.value === 'Others' ? (purposeOther && purposeOther.value.trim() ? purposeOther.value.trim() : 'Others') : (purposeSelect.value || '');
            sPurpose.textContent = asDash(purposeVal);
        }
    }

    // Live summary updates (fields + claim radios)
    function attachLiveSummaryUpdates() {
        const ids = ['full_name','purok','age','maritalstatus','business_name','business_type','address','ctcnumber'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', populateSummary);
            el.addEventListener('change', populateSummary);
        });

        // When hidden claim fields change (they are set programmatically), update summary
        if (hiddenClaimDate) hiddenClaimDate.addEventListener('change', populateSummary);
        if (hiddenClaimTime) hiddenClaimTime.addEventListener('change', populateSummary);

        if (hiddenPaymentInput) hiddenPaymentInput.addEventListener('change', populateSummary);
        if (purposeSelect) purposeSelect.addEventListener('change', populateSummary);
        if (purposeOther) purposeOther.addEventListener('input', populateSummary);
    }
    attachLiveSummaryUpdates();

    // initial population of summary placeholders for server-prefilled values
    (function initialSummaryFill() {
        // full name
        const sFull = document.getElementById('summaryFullName');
        if (sFull && (!sFull.textContent || sFull.textContent.trim() === '')) {
            const src = document.getElementById('full_name');
            if (src) sFull.textContent = src.value || '-';
        }

        const sPurok = document.getElementById('summaryPurok');
        if (sPurok && (!sPurok.textContent || sPurok.textContent.trim() === '')) {
            const src = document.getElementById('purok');
            if (src) sPurok.textContent = src.value || '-';
        }

        // Age / Marital
        const sAgeMarital = document.getElementById('summaryAgeMarital');
        if (sAgeMarital && (!sAgeMarital.textContent || sAgeMarital.textContent.trim() === '')) {
            const age = document.getElementById('age')?.value || '';
            const marital = document.getElementById('maritalstatus')?.value || '';
            if (!age && !marital) sAgeMarital.textContent = '-';
            else sAgeMarital.textContent = (age ? age : '-') + (marital ? ' / ' + marital : '');
        }

        // Business / Type
        const sBusiness = document.getElementById('summaryBusiness');
        if (sBusiness && (!sBusiness.textContent || sBusiness.textContent.trim() === '')) {
            const b = document.getElementById('business_name')?.value || '';
            const t = document.getElementById('business_type')?.value || '';
            if (!b && !t) sBusiness.textContent = '-';
            else sBusiness.textContent = (b ? b : '-') + (t ? ' / ' + t : '');
        }

        const sBusinessAddr = document.getElementById('summaryBusinessAddress');
        if (sBusinessAddr && (!sBusinessAddr.textContent || sBusinessAddr.textContent.trim() === '')) {
            sBusinessAddr.textContent = document.getElementById('address')?.value || '-';
        }

        const sCTC = document.getElementById('summaryCTC');
        if (sCTC && (!sCTC.textContent || sCTC.textContent.trim() === '')) {
            sCTC.textContent = document.getElementById('ctcnumber')?.value || '-';
        }

        // Claim (may be set during claim initialization)
        const sClaim = document.getElementById('summaryClaimDate');
        if (sClaim && (!sClaim.textContent || sClaim.textContent.trim() === '')) {
            // if hidden claim fields are set, use them
            const cd = hiddenClaimDate ? hiddenClaimDate.value : null;
            const ct = hiddenClaimTime ? hiddenClaimTime.value : null;
            if (isValidClaimSeparate(cd, ct)) sClaim.textContent = getFriendlyClaimLabelFromParts(cd, ct);
            else sClaim.textContent = '-';
        }

        // Payment
        const pm = document.getElementById('paymentMethod');
        const sPayment = document.getElementById('summaryPaymentMethod');
        if (sPayment && (!sPayment.textContent || sPayment.textContent.trim() === '')) {
            sPayment.textContent = pm ? (pm.value || '-') : '-';
        }
    })();

});
