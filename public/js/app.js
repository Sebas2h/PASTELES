document.addEventListener('DOMContentLoaded', () => {
  const cartDrawer = document.querySelector('[data-cart-drawer]');
  const cartOpen = document.querySelector('[data-cart-open]');
  const cartClosers = document.querySelectorAll('[data-cart-close]');
  const cartBackdrop = document.querySelector('.cart-drawer-backdrop');
  const closeCartDrawer = () => {
    if (!cartDrawer) return;
    cartDrawer.classList.remove('is-open');
    cartDrawer.setAttribute('aria-hidden', 'true');
    cartDrawer.setAttribute('inert', '');
    if (cartBackdrop) cartBackdrop.hidden = true;
    document.body.classList.remove('cart-drawer-open');
    cartOpen?.focus();
  };
  cartOpen?.addEventListener('click', () => {
    if (!cartDrawer) return;
    cartDrawer.removeAttribute('inert');
    cartDrawer.setAttribute('aria-hidden', 'false');
    cartDrawer.classList.add('is-open');
    if (cartBackdrop) cartBackdrop.hidden = false;
    document.body.classList.add('cart-drawer-open');
    cartDrawer.querySelector('[data-cart-close]:not(.cart-drawer-backdrop)')?.focus();
  });
  cartClosers.forEach(button => button.addEventListener('click', closeCartDrawer));
  document.addEventListener('keydown', event => {
    if (event.key === 'Escape' && cartDrawer?.classList.contains('is-open')) closeCartDrawer();
  });

  document.querySelectorAll('[data-purchase-history]').forEach(history => {
    const tabs = [...history.querySelectorAll('[data-purchase-tab]')];
    const panels = [...history.querySelectorAll('[data-purchase-panel]')];
    tabs.forEach(tab => tab.addEventListener('click', () => {
      const selected = tab.dataset.purchaseTab;
      tabs.forEach(item => {
        const active = item === tab;
        item.classList.toggle('active', active);
        item.setAttribute('aria-selected', String(active));
      });
      panels.forEach(panel => { panel.hidden = panel.dataset.purchasePanel !== selected; });
    }));
  });

  document.querySelectorAll('[data-reward-choice]').forEach(choice => {
    const form = choice.closest('form');
    const modeInputs = [...choice.querySelectorAll('input[name="reward_mode"]')];
    const pointField = choice.querySelector('[data-points-reward]');
    const pointSelect = pointField?.querySelector('select[name="reward_id"]');
    const promoField = choice.querySelector('[data-promo-field]');
    const promoChars = [...choice.querySelectorAll('[data-promo-char]')];
    const promoValue = choice.querySelector('[data-promo-value]');
    const updatePromo = () => {
      const code = promoChars.map(input => input.value).join('').toUpperCase();
      if (promoValue) promoValue.value = code.length === 10 ? `DN-${code}` : '';
    };
    const syncMode = () => {
      const mode = choice.querySelector('input[name="reward_mode"]:checked')?.value || 'none';
      const usePoints = mode === 'points';
      const usePromo = mode === 'promo';
      if (pointSelect) pointSelect.disabled = !usePoints;
      if (pointField) pointField.hidden = !usePoints;
      if (promoField) promoField.hidden = !usePromo;
      promoChars.forEach(input => {
        input.disabled = !usePromo;
        input.required = usePromo;
      });
      if (!usePromo) {
        promoChars.forEach(input => { input.value = ''; });
        updatePromo();
      }
    };
    modeInputs.forEach(input => input.addEventListener('change', syncMode));
    promoChars.forEach((input, index) => {
      input.addEventListener('input', () => {
        input.value = input.value.toUpperCase().replace(/[^A-F0-9]/g, '').slice(-1);
        updatePromo();
        if (input.value && promoChars[index + 1]) promoChars[index + 1].focus();
      });
      input.addEventListener('keydown', event => {
        if (event.key === 'Backspace' && !input.value && promoChars[index - 1]) {
          promoChars[index - 1].focus();
          promoChars[index - 1].value = '';
          updatePromo();
        }
      });
      input.addEventListener('paste', event => {
        const pasted = event.clipboardData?.getData('text') || '';
        const code = pasted.toUpperCase().replace(/^DN-/, '').replace(/[^A-F0-9]/g, '').slice(0, 10);
        if (!code) return;
        event.preventDefault();
        promoChars.forEach((slot, slotIndex) => { slot.value = code[slotIndex] || ''; });
        updatePromo();
        (promoChars[Math.min(code.length, 9)] || input).focus();
      });
    });
    form?.addEventListener('submit', updatePromo);
    syncMode();
  });

  document.querySelectorAll('[data-toggle-password]').forEach(button => {
    button.addEventListener('click', () => {
      const input = document.getElementById(button.dataset.togglePassword);
      if (input) input.type = input.type === 'password' ? 'text' : 'password';
    });
  });

  document.querySelectorAll('[data-strength]').forEach(input => {
    input.addEventListener('input', () => {
      let score = 0;
      const value = input.value;
      if (value.length >= 8) score++;
      if (/[A-Z]/.test(value) && /[a-z]/.test(value)) score++;
      if (/\d/.test(value)) score++;
      if (/[^A-Za-z0-9]/.test(value)) score++;
      const meter = input.parentElement.querySelector('.strength i');
      if (meter) meter.style.width = `${score * 25}%`;
    });
  });

  const search = document.getElementById('searchProducts');
  const cards = [...document.querySelectorAll('.product-card')];
  let category = 'all';
  const filterProducts = () => cards.forEach(card => {
    const query = (search?.value || '').toLowerCase();
    card.style.display = card.dataset.name.includes(query)
      && (category === 'all' || card.dataset.category === category) ? '' : 'none';
  });
  search?.addEventListener('input', filterProducts);
  document.querySelectorAll('.chip').forEach(button => button.addEventListener('click', () => {
    document.querySelectorAll('.chip').forEach(chip => chip.classList.remove('active'));
    button.classList.add('active');
    category = button.dataset.category;
    filterProducts();
  }));

  const modal = document.querySelector('[data-app-modal]');
  const modalTitle = modal?.querySelector('[data-modal-title]');
  const modalMessage = modal?.querySelector('[data-modal-message]');
  const modalIcon = modal?.querySelector('[data-modal-icon]');
  const cancelButton = modal?.querySelector('[data-modal-cancel]');
  const okButton = modal?.querySelector('[data-modal-ok]');
  let pendingForm = null;
  let pendingSubmitter = null;
  let pendingFocus = null;

  const closeModal = () => {
    if (modal?.open) modal.close();
    pendingForm = null;
    pendingSubmitter = null;
    const focusTarget = pendingFocus;
    pendingFocus = null;
    focusTarget?.focus();
  };

  const showNotice = (title, message, icon = 'i') => {
    if (!modal || modal.open) return;
    modal.dataset.modalType = 'notice';
    modalTitle.textContent = title;
    modalMessage.textContent = message;
    modalIcon.textContent = icon;
    cancelButton.hidden = true;
    okButton.textContent = 'Entendido';
    modal.showModal();
  };

  document.addEventListener('invalid', event => {
    event.preventDefault();
    const field = event.target;
    field.form?.removeAttribute('data-confirmed');
    const label = field.labels?.[0]?.innerText?.trim() || field.getAttribute('aria-label') || field.name || 'este campo';
    let message = `Revisa el valor de ${label}.`;
    if (field.validity.valueMissing) message = `Completa ${label} para continuar.`;
    else if (field.validity.typeMismatch) message = `Escribe un valor válido para ${label}.`;
    else if (field.validity.tooShort) message = `${label} debe tener al menos ${field.minLength} caracteres.`;
    else if (field.validity.tooLong) message = `${label} debe tener como máximo ${field.maxLength} caracteres.`;
    else if (field.validity.rangeUnderflow) message = `${label} debe ser igual o mayor que ${field.min}.`;
    else if (field.validity.rangeOverflow) message = `${label} debe ser igual o menor que ${field.max}.`;
    else if (field.validity.stepMismatch) message = `Revisa el valor de ${label}.`;
    if (!modal?.open) {
      pendingFocus = field;
      showNotice('Falta revisar un dato', message, '!');
    }
  }, true);

  document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
      if (form.dataset.confirmed === '1') {
        delete form.dataset.confirmed;
        return;
      }
      event.preventDefault();
      if (!modal || modal.open) return;
      pendingForm = form;
      pendingSubmitter = event.submitter;
      modal.dataset.modalType = 'confirm';
      modalTitle.textContent = '¿Confirmas esta acción?';
      modalMessage.textContent = form.dataset.confirm;
      modalIcon.textContent = '?';
      cancelButton.hidden = false;
      okButton.textContent = 'Continuar';
      modal.showModal();
    });
  });

  cancelButton?.addEventListener('click', closeModal);
  okButton?.addEventListener('click', () => {
    if (!pendingForm) {
      closeModal();
      return;
    }
    const form = pendingForm;
    const submitter = pendingSubmitter;
    form.dataset.confirmed = '1';
    closeModal();
    form.requestSubmit(submitter || undefined);
  });
  modal?.addEventListener('cancel', event => {
    if (pendingForm) event.preventDefault();
    closeModal();
  });
  modal?.addEventListener('click', event => {
    if (event.target === modal && !pendingForm) closeModal();
  });

  document.querySelectorAll('[data-preview]').forEach(input => input.addEventListener('change', () => {
    const image = input.parentElement.querySelector('.preview');
    if (input.files[0] && image) {
      image.src = URL.createObjectURL(input.files[0]);
      image.style.display = 'block';
    }
  }));

  const wheelModal = document.querySelector('[data-wheel-modal]');
  const wheelDisc = wheelModal?.querySelector('[data-wheel-disc]');
  const wheelResult = wheelModal?.querySelector('[data-wheel-result]');
  const wheelSpinForm = wheelModal?.querySelector('[data-wheel-spin]');
  const wheelSpinButton = wheelModal?.querySelector('[data-wheel-spin-button]');
  const wheelCloseButton = wheelModal?.querySelector('[data-close-wheel]');
  const wheelAcceptForm = wheelModal?.querySelector('[data-wheel-accept-form]');
  const wheelError = wheelModal?.querySelector('[data-wheel-error]');
  const wheelConfirm = document.querySelector('[data-wheel-confirm]');
  const wheelConfirmAccept = wheelConfirm?.querySelector('[data-wheel-confirm-accept]');
  const wheelConfirmCancel = wheelConfirm?.querySelector('[data-wheel-confirm-cancel]');
  const wheelConfirmError = wheelConfirm?.querySelector('[data-wheel-confirm-error]');
  const wheelFirstConfirm = document.querySelector('[data-wheel-first-confirm]');
  const wheelFirstConfirmAccept = wheelFirstConfirm?.querySelector('[data-wheel-first-accept]');
  const wheelFirstConfirmCancel = wheelFirstConfirm?.querySelector('[data-wheel-first-cancel]');
  const renderWheelLayout = layout => {
    if (!wheelDisc || !layout?.gradient || !Array.isArray(layout.segments)) return;
    wheelDisc.style.background = layout.gradient;
    wheelDisc.querySelectorAll('.wheel-sector-label').forEach(label => label.remove());
    const labels = {muffin: 'Muffin gratis', five: 'Cupón 5%', ten: 'Cupón 10%', twenty: 'Cupón 20%'};
    let segmentStart = 0;
    layout.segments.forEach(segment => {
      const angle = segmentStart + Number(segment.size) * 1.8;
      const label = document.createElement('span');
      label.className = 'wheel-sector-label';
      label.style.setProperty('--label-angle', `${angle}deg`);
      label.style.setProperty('--label-counter-angle', `${-angle}deg`);
      label.style.setProperty('--label-width', segment.key === 'twenty' ? '46px' : '54px');
      const text = document.createElement('span');
      text.className = 'wheel-sector-label__text';
      text.textContent = labels[segment.key] || segment.name || 'Premio';
      label.appendChild(text);
      wheelDisc.appendChild(label);
      segmentStart += Number(segment.size) * 3.6;
    });
  };
  let wheelAnimating = false;
  let firstSpinApproved = false;
  let afterWheelAccept = null;
  document.querySelectorAll('[data-open-wheel]').forEach(button => button.addEventListener('click', () => {
    if (!wheelModal?.open) wheelModal?.showModal();
  }));
  const confirmWheelExit = (afterAccept = null) => {
    if (wheelAnimating || !wheelModal?.dataset.pendingCode) return;
    afterWheelAccept = afterAccept;
    if (wheelConfirmError) { wheelConfirmError.hidden = true; wheelConfirmError.textContent = ''; }
    if (wheelConfirm && !wheelConfirm.open) wheelConfirm.showModal();
  };
  const acceptWheelPrize = async () => {
    if (wheelAnimating) return;
    const code = wheelModal?.dataset.pendingCode;
    if (!code) { wheelModal?.close(); return; }
    wheelCloseButton.disabled = true;
    if (wheelConfirmAccept) wheelConfirmAccept.disabled = true;
    const codeField = wheelAcceptForm?.querySelector('[name="code"]');
    if (codeField) codeField.value = code;
    try {
      const response = await fetch(wheelAcceptForm.action, {
        method: 'POST',
        body: new FormData(wheelAcceptForm),
        headers: {'Accept': 'application/json'},
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo guardar el premio.');
      delete wheelModal.dataset.pendingCode;
      if (wheelConfirm?.open) wheelConfirm.close();
      const next = afterWheelAccept;
      afterWheelAccept = null;
      if (next) next();
      else window.location.reload();
    } catch (error) {
      wheelCloseButton.disabled = false;
      if (wheelConfirmError) {
        wheelConfirmError.textContent = error.message || 'No se pudo guardar el premio.';
        wheelConfirmError.hidden = false;
      }
    } finally {
      if (wheelConfirmAccept) wheelConfirmAccept.disabled = false;
    }
  };
  wheelConfirmAccept?.addEventListener('click', acceptWheelPrize);
  wheelConfirmCancel?.addEventListener('click', () => {
    afterWheelAccept = null;
    wheelConfirm?.close();
  });
  wheelConfirm?.addEventListener('cancel', () => { afterWheelAccept = null; });
  wheelCloseButton?.addEventListener('click', () => {
    if (wheelModal?.dataset.pendingCode) confirmWheelExit();
    else wheelModal?.close();
  });
  wheelModal?.addEventListener('cancel', event => {
    event.preventDefault();
    if (wheelAnimating) return;
    if (wheelModal.dataset.pendingCode) confirmWheelExit();
    else wheelModal.close();
  });
  document.addEventListener('click', event => {
    if (!wheelModal?.dataset.pendingCode || event.defaultPrevented || event.button !== 0
      || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;
    const link = event.target.closest('a[href]');
    if (!link || link.target === '_blank' || link.hasAttribute('download')) return;
    const destination = new URL(link.href, window.location.href);
    if (destination.origin !== window.location.origin || destination.href === window.location.href) return;
    event.preventDefault();
    confirmWheelExit(() => window.location.assign(destination.href));
  });
  wheelSpinForm?.addEventListener('submit', async event => {
    event.preventDefault();
    if (wheelAnimating) return;
    if (!wheelModal.dataset.pendingCode && !firstSpinApproved) {
      if (wheelFirstConfirm && !wheelFirstConfirm.open) wheelFirstConfirm.showModal();
      return;
    }
    firstSpinApproved = false;
    wheelAnimating = true;
    wheelSpinButton.disabled = true;
    wheelCloseButton.disabled = true;
    wheelError.hidden = true;
    wheelError.textContent = '';
    try {
      const response = await fetch(wheelSpinForm.action, {
        method: 'POST',
        body: new FormData(wheelSpinForm),
        headers: {'Accept': 'application/json'},
        credentials: 'same-origin',
      });
      const payload = await response.json();
      if (!response.ok || !payload.ok) throw new Error(payload.message || 'No se pudo completar el giro.');

      wheelModal.querySelector('[data-wheel-result-name]').textContent = payload.result.name;
      wheelModal.querySelector('[data-wheel-result-code]').textContent = payload.result.code;
      wheelModal.querySelector('[data-wheel-result-points]').textContent = `Se descontaron ${new Intl.NumberFormat('es-EC').format(payload.result.points_spent)} puntos.`;
      wheelResult.hidden = true;
      wheelModal.dataset.pendingCode = payload.result.code;
      wheelAcceptForm.querySelector('[name="code"]').value = payload.result.code;
      wheelModal.dataset.wheelStock = payload.stock === null ? '' : String(payload.stock);
      wheelSpinButton.textContent = 'Otra vez';
      wheelModal.querySelector('[data-wheel-cost-label]').textContent = 'Cambiar el premio cuesta';
      wheelModal.querySelector('[data-wheel-cost-value]').textContent = new Intl.NumberFormat('es-EC').format(payload.next_cost);
      const cost = wheelModal.querySelector('.wheel-modal__cost');
      if (cost) cost.hidden = false;
      const balance = document.querySelector('.store-balance strong');
      if (balance) balance.textContent = `⭐ ${new Intl.NumberFormat('es-EC').format(payload.balance)} pts`;

      renderWheelLayout(payload.wheel);
      wheelDisc.style.setProperty('--wheel-stop', `${2160 + Number(payload.result.stop_angle)}deg`);
      wheelDisc.classList.remove('is-spinning');
      void wheelDisc.offsetWidth;
      wheelDisc.classList.add('is-spinning');
    } catch (error) {
      wheelAnimating = false;
      wheelSpinButton.disabled = false;
      wheelCloseButton.disabled = false;
      wheelError.textContent = error.message || 'Ocurrió un problema. Intenta de nuevo.';
      wheelError.hidden = false;
    }
  });
  wheelFirstConfirmAccept?.addEventListener('click', () => {
    wheelFirstConfirm.close();
    firstSpinApproved = true;
    wheelSpinForm?.requestSubmit();
  });
  wheelFirstConfirmCancel?.addEventListener('click', () => wheelFirstConfirm?.close());
  wheelDisc?.addEventListener('animationend', () => {
    wheelAnimating = false;
    wheelCloseButton.disabled = false;
    wheelSpinButton.disabled = wheelModal.dataset.wheelStock !== '' && Number(wheelModal.dataset.wheelStock) <= 0;
    if (wheelResult) wheelResult.hidden = false;
  });

  if (modal?.hasAttribute('data-open-on-load')) {
    cancelButton.hidden = true;
    okButton.textContent = 'Entendido';
    modal.showModal();
  }

  if (wheelModal?.hasAttribute('data-open-on-load')) {
    wheelModal.showModal();
    wheelAnimating = true;
    wheelCloseButton.disabled = true;
    wheelSpinButton.disabled = true;
    requestAnimationFrame(() => wheelDisc?.classList.add('is-spinning'));
  }
});
