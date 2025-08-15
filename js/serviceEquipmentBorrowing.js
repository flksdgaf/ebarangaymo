(() => {
  const total = 3;
  const stepEls = {1: document.getElementById('step1'), 2: document.getElementById('step2'), 3: document.getElementById('step3')};
  const prevBtn = document.getElementById('prevBtn');
  let nextBtn = document.getElementById('nextBtn');
  const mainHeader = document.getElementById('mainHeader');
  const subHeader = document.getElementById('subHeader');
  const mainHr = document.getElementById('mainHr');
  let current = 1;

  function updateNavigation() {
    prevBtn.style.visibility = current === 1 ? 'hidden' : 'visible';
    if (!mainHeader) return;
    if (current === 1) {
      mainHeader.textContent = "APPLICATION FORM"; subHeader.textContent = "Provide the necessary details to borrow equipment.";
      nextBtn.textContent = "NEXT >";
    } else if (current === 2) {
      mainHeader.textContent = "REVIEW & CONFIRMATION"; subHeader.textContent = "Please review all your information before submitting.";
      nextBtn.textContent = "SUBMIT";
    } else if (current === 3) {
      if (mainHeader) mainHeader.remove(); if (subHeader) subHeader.remove(); if (mainHr) mainHr.remove();
      prevBtn.style.visibility = 'hidden';
      const newNext = nextBtn.cloneNode(true);
      newNext.textContent = "Back to Home";
      newNext.className = nextBtn.className;
      nextBtn.replaceWith(newNext);
      nextBtn = document.getElementById('nextBtn');
      nextBtn.addEventListener('click', () => window.location.href = 'userPanel.php?page=userDashboard');
      nextBtn.style.display = 'inline-block';
    }
  }

  function showStep(n) {
    for (let i=1;i<=total;i++) stepEls[i].classList.toggle('active-step', i===n);
    prevBtn.style.display = (n===1)?'none':'inline-block';
    nextBtn.style.display = (n===3)?'none':'inline-block';
    updateNavigation();
  }

  function fillSummary() {
    document.getElementById('s_resident_name').textContent = document.getElementById('resident_name').value || '-';
    document.getElementById('s_purok').textContent = document.getElementById('purok').value || '-';
    document.getElementById('s_equipment').textContent = document.getElementById('equipment_name').value || '-';
    document.getElementById('s_qty').textContent = document.getElementById('qty').value || '-';
    document.getElementById('s_used_for').textContent = document.getElementById('used_for').value || '-';
    document.getElementById('s_location').textContent = document.getElementById('location').value || '-';
    const pickupOptEl = document.getElementById('pudo_option');
    document.getElementById('s_pudo_option').textContent = pickupOptEl ? pickupOptEl.options[pickupOptEl.selectedIndex].text : '-';
    document.getElementById('s_borrow_info').textContent = document.getElementById('borrow_date').value || '-';
  }

  prevBtn.addEventListener('click', () => { if (current>1) { current--; showStep(current); } });

  nextBtn.addEventListener('click', () => {
    if (current === 1) {
      const qty = Number(document.getElementById('qty').value || 0);
      const available = Number(document.getElementById('availableQty').textContent || 0);
      const used_for = document.getElementById('used_for').value.trim();
      const location = document.getElementById('location').value.trim();
      const borrow_date = document.getElementById('borrow_date').value;
      const pudo_option = document.getElementById('pudo_option').value;
      if (!used_for || !location || !borrow_date || qty < 1 || !pudo_option) {
        new bootstrap.Modal(document.getElementById('validationModal')).show();
        return;
      }
      if (qty > available) { alert('Requested quantity exceeds available quantity.'); return; }
      fillSummary(); current = 2; showStep(current); return;
    }

    if (current === 2) {
      const submitBtn = nextBtn;
      submitBtn.disabled = true;
      const originalText = submitBtn.textContent;
      submitBtn.textContent = 'Submitting...';

      const formData = new FormData();
      formData.append('resident_name', document.getElementById('resident_name').value);
      formData.append('purok', document.getElementById('purok').value);
      formData.append('equipment_sn', document.getElementById('equipment_sn').value);
      formData.append('qty', document.getElementById('qty').value);
      formData.append('location', document.getElementById('location').value);
      formData.append('used_for', document.getElementById('used_for').value);
      formData.append('borrow_date', document.getElementById('borrow_date').value);
      formData.append('pudo_option', document.getElementById('pudo_option').value);

      // POST to functions/serviceEquipmentBorrowing_submit.php (submit file is in functions folder)
      fetch('functions/serviceEquipmentBorrowing_submit.php', {
        method: 'POST',
        body: formData,
        credentials: 'same-origin',
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
      })
      .then(async r => {
        const text = await r.text();
        try { return JSON.parse(text); }
        catch (e) { throw new Error(text || 'Invalid server response'); }
      })
      .then(data => {
        if (data.status === 'success') {
          current = 3; showStep(current);
          document.getElementById('submissionMessage').textContent = 'Your request has been submitted. Request ID: ' + (data.id || '');
          const txnBox = document.getElementById('txnBox'); txnBox.innerHTML = '';
          const idStr = 'BRW-' + String(data.id || '').padStart(6, '0');
          for (const ch of idStr) { const sp = document.createElement('span'); sp.className='txn-char'; sp.textContent = ch; txnBox.appendChild(sp); }
        } else {
          alert(data.message || 'Submission failed.');
        }
      })
      .catch(err => {
        console.error('Submit error:', err);
        alert('An error occurred while submitting. Server said:\n' + (err.message || err));
      })
      .finally(() => {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
      });
    }
  });

  showStep(current);
})();
