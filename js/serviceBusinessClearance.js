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

    // Purpose controls (may not exist on business form; guard checks)
    const purposeSelect = document.getElementById('purposeSelect');
    const purposeOther = document.getElementById('purposeOther');
    const purposeHidden = document.getElementById('purposeHidden');

    // Modals / confirmation
    const validationModalEl = document.getElementById("validationModal");
    const confirmationModalEl = document.getElementById("confirmationModal");
    const validationModal = validationModalEl ? new bootstrap.Modal(validationModalEl) : null;
    const confirmationModal = confirmationModalEl ? new bootstrap.Modal(confirmationModalEl) : null;
    const confirmSubmitBtn = document.getElementById("confirmSubmitBtn");

    // form id (business)
    const form = document.getElementById("businessClearanceForm");

    // Initial render
    showStep(currentStep);
    setupPaymentControls();
    setupPurposeControls();
    attachLiveSummaryUpdates();

    // --- Navigation handlers ---
    nextBtn && nextBtn.addEventListener('click', function () {
        // If on final (submission) screen -> redirect or goto dashboard depending on page state
        if (currentStep === totalSteps) {
            // If the form is already submitted and showing tid, we simply go to user dashboard
            window.location.href = 'userPanel.php?page=userDashboard';
            return;
        }

        // Validate step-specific requirements
        if (currentStep === 1) {
            if (!validateStep1()) {
                if (validationModal) validationModal.show();
                return;
            }
        }

        if (currentStep === 2) {
            // ensure payment method selected
            if (!hiddenPaymentInput || !hiddenPaymentInput.value) {
                if (validationModal) validationModal.show();
                return;
            }
        }

        // If stepping into confirmation/review (step 3 for a 4-step flow), show confirmation modal first
        if (currentStep === 3) {
            // populate summary before confirmation
            populateSummary();
            if (confirmationModal) {
                confirmationModal.show();
                return;
            }
        }

        // advance step
        goToStep(currentStep + 1);
    });

    backBtn && backBtn.addEventListener('click', function () {
        if (currentStep > 1) {
            goToStep(currentStep - 1);
        }
    });

    // Confirm submission button in modal
    if (confirmSubmitBtn) {
        confirmSubmitBtn.addEventListener('click', function () {
            // Ensure purpose hidden canonical value is synced
            syncPurposeHidden();
            if (form) form.submit();
        });
    }

    // --- Helpers ---
    function goToStep(n) {
        if (n < 1) n = 1;
        if (n > totalSteps) n = totalSteps;

        // update classes
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

        // progress percent
        const percent = ((n - 1) / (totalSteps - 1)) * 100;
        if (progressFill) progressFill.style.width = percent + '%';

        currentStep = n;
        updateNavigation();
        if (currentStep === 3) populateSummary();
    }

    function showStep(n) {
        // initial set (used on page load)
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
        // back button visibility
        if (backBtn) backBtn.style.visibility = currentStep === 1 ? 'hidden' : 'visible';

        // headers & next button text
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
            // Submission screen: hide headers and change next behavior
            if (mainHeader && mainHeader.parentNode) mainHeader.remove();
            if (subHeader && subHeader.parentNode) subHeader.remove();
            const mainHr = document.getElementById('mainHr');
            if (mainHr && mainHr.parentNode) mainHr.remove();

            if (backBtn) backBtn.style.visibility = 'hidden';
            if (nextBtn) {
                nextBtn.textContent = "Back to Home";
                // replace click behavior to simply go to dashboard
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
        // List required ids on business form (ids are from serviceBusinessClearance.php)
        const requiredIds = [
            'lastname','firstname','purok','barangay','municipality','province',
            'age','maritalstatus','business_name','business_type','address','claimdate'
        ];

        requiredIds.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return; // skip missing fields (defensive)
            if (el.type === 'file') {
                if (!el.files || el.files.length === 0) {
                    if (el.hasAttribute('required')) {
                        ok = false;
                        el.classList.add('is-invalid');
                    } else {
                        el.classList.remove('is-invalid');
                    }
                } else {
                    el.classList.remove('is-invalid');
                }
                return;
            }
            const val = (el.value || '').toString().trim();
            if (!val) {
                ok = false;
                el.classList.add('is-invalid');
            } else {
                el.classList.remove('is-invalid');
            }
        });

        // If purpose controls exist, validate canonical purpose
        if (purposeSelect) {
            const selectVal = (purposeSelect.value || '').trim();
            if (!selectVal) {
                ok = false;
                purposeSelect.classList.add('is-invalid');
            } else {
                purposeSelect.classList.remove('is-invalid');
            }
            if (selectVal === 'Others') {
                const oth = (purposeOther && purposeOther.value || '').trim();
                if (!oth) {
                    ok = false;
                    purposeOther && purposeOther.classList.add('is-invalid');
                } else {
                    purposeOther && purposeOther.classList.remove('is-invalid');
                }
            }
            // final hidden check
            if (purposeHidden && (!purposeHidden.value || !purposeHidden.value.trim())) {
                ok = false;
                purposeSelect.classList.add('is-invalid');
            }
        }

        return ok;
    }

    // Payment controls setup
    function setupPaymentControls() {
        if (!paymentButtons || paymentButtons.length === 0) {
            // ensure hidden input has a default if absent
            if (hiddenPaymentInput && !hiddenPaymentInput.value) hiddenPaymentInput.value = 'Brgy Payment Device';
            return;
        }

        const initialMethod = hiddenPaymentInput ? (hiddenPaymentInput.value || '') : '';

        paymentButtons.forEach(btn => {
            // initial active marking
            if (initialMethod && btn.dataset.method === initialMethod) {
                btn.classList.add('active');
                instructionPanels.forEach(p => {
                    p.classList.toggle('d-none', p.dataset.method !== initialMethod);
                });
            }

            btn.addEventListener('click', function () {
                paymentButtons.forEach(b => b.classList.remove('active'));
                this.classList.add('active');

                const method = this.dataset.method;
                if (hiddenPaymentInput) hiddenPaymentInput.value = method;

                instructionPanels.forEach(p => {
                    p.classList.toggle('d-none', p.dataset.method !== method);
                });
            });
        });

        if (!initialMethod) {
            const def = Array.from(paymentButtons).find(b => b.dataset.method === 'Brgy Payment Device');
            if (def) def.click();
        }
    }

    // Purpose controls setup (defensive: may not exist)
    function setupPurposeControls() {
        if (!purposeSelect || !purposeHidden) return;

        // Initialize hidden/select values if prefilled by PHP
        if (purposeHidden.value) {
            const hiddenVal = purposeHidden.value.trim();
            const match = Array.from(purposeSelect.options).some(o => o.value === hiddenVal);
            if (!match) {
                // try to select "Others" if present
                const othersOpt = Array.from(purposeSelect.options).find(o => o.text === 'Others' || o.value === 'Others');
                if (othersOpt) {
                    othersOpt.selected = true;
                    if (purposeOther) purposeOther.value = hiddenVal;
                }
            } else {
                purposeSelect.value = hiddenVal;
            }
        } else {
            // set hidden to select initial value
            purposeHidden.value = purposeSelect.value || '';
        }

        const togglePurposeOther = () => {
            if (purposeSelect.value === 'Others') {
                purposeOther && purposeOther.classList.remove('d-none');
                purposeOther && (purposeOther.required = true);
                if (purposeHidden && purposeHidden.value && purposeHidden.value !== 'Others') {
                    if (purposeOther && !purposeOther.value) purposeOther.value = purposeHidden.value;
                }
                if (purposeOther && purposeOther.value.trim()) {
                    purposeHidden.value = purposeOther.value.trim();
                } else if (purposeHidden && !purposeHidden.value.trim()) {
                    purposeHidden.value = 'Others';
                }
            } else {
                purposeOther && purposeOther.classList.add('d-none');
                purposeOther && (purposeOther.required = false);
                purposeOther && purposeOther.classList.remove('is-invalid');
                purposeHidden.value = purposeSelect.value || '';
            }
        };

        purposeSelect.addEventListener('change', function () {
            togglePurposeOther();
            populateSummary();
        });

        if (purposeOther) {
            purposeOther.addEventListener('input', function () {
                purposeHidden.value = this.value.trim() || 'Others';
                populateSummary();
            });
        }

        // initial toggle
        togglePurposeOther();
    }

    // Ensure hidden purpose is canonical before submit
    function syncPurposeHidden() {
        if (!purposeSelect || !purposeHidden) return;
        if (purposeSelect.value === 'Others') {
            if (purposeOther && purposeOther.value.trim()) purposeHidden.value = purposeOther.value.trim();
            else purposeHidden.value = 'Others';
        } else {
            purposeHidden.value = purposeSelect.value;
        }
    }

    // Populate summary (IDs aligned with serviceBusinessClearance.php)
    function populateSummary() {
        const get = id => (document.getElementById(id) ? document.getElementById(id).value : '');

        const last = get('lastname');
        const first = get('firstname');
        const middle = get('middlename');
        const purok = get('purok');
        const barangay = get('barangay');
        const municipality = get('municipality');
        const province = get('province');
        const age = get('age');
        const marital = get('maritalstatus');
        const business = get('business_name');
        const btype = get('business_type');
        const baddr = get('address');
        const ctc = get('ctcnumber');
        const claim = get('claimdate');
        const payment = hiddenPaymentInput ? (hiddenPaymentInput.value || '') : '';

        if (document.getElementById('summaryLastName')) document.getElementById('summaryLastName').textContent = last;
        if (document.getElementById('summaryFirstName')) document.getElementById('summaryFirstName').textContent = first;
        if (document.getElementById('summaryMiddleName')) document.getElementById('summaryMiddleName').textContent = middle;
        if (document.getElementById('summaryPurok')) document.getElementById('summaryPurok').textContent = purok;

        const fullAddressParts = [barangay, municipality, province].filter(Boolean);
        if (document.getElementById('summaryAddress')) document.getElementById('summaryAddress').textContent = fullAddressParts.join(' / ');

        if (document.getElementById('summaryAgeMarital')) document.getElementById('summaryAgeMarital').textContent = (age ? age : '') + (marital ? (' / ' + marital) : '');
        if (document.getElementById('summaryBusiness')) document.getElementById('summaryBusiness').textContent = business + (btype ? (' / ' + btype) : '');
        if (document.getElementById('summaryBusinessAddress')) document.getElementById('summaryBusinessAddress').textContent = baddr;
        if (document.getElementById('summaryCTC')) document.getElementById('summaryCTC').textContent = ctc;
        if (document.getElementById('summaryClaimDate')) document.getElementById('summaryClaimDate').textContent = claim;
        if (document.getElementById('summaryPaymentMethod')) document.getElementById('summaryPaymentMethod').textContent = payment;

        // Purpose (if present)
        let purposeVal = '';
        if (purposeHidden && purposeHidden.value) purposeVal = purposeHidden.value;
        else if (purposeSelect) {
            purposeVal = purposeSelect.value === 'Others' ? (purposeOther && purposeOther.value.trim() ? purposeOther.value.trim() : 'Others') : (purposeSelect.value || '');
        }
        if (document.getElementById('summaryPurpose')) document.getElementById('summaryPurpose').textContent = purposeVal;
    }

    // Add listeners to update summary live when editing fields so review step is up-to-date
    function attachLiveSummaryUpdates() {
        const ids = ['lastname','firstname','middlename','purok','barangay','municipality','province','age','maritalstatus','business_name','business_type','address','ctcnumber','claimdate'];
        ids.forEach(id => {
            const el = document.getElementById(id);
            if (!el) return;
            el.addEventListener('input', populateSummary);
            el.addEventListener('change', populateSummary);
        });

        // also payment & purpose
        if (hiddenPaymentInput) hiddenPaymentInput.addEventListener('change', populateSummary);
        if (purposeSelect) purposeSelect.addEventListener('change', populateSummary);
        if (purposeOther) purposeOther.addEventListener('input', populateSummary);
    }
});
