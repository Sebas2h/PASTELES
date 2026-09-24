(() => {
  const forms = [...document.querySelectorAll('[data-editable-profile-field]')];
  const modal = document.querySelector('[data-profile-confirm]');
  const message = modal?.querySelector('[data-confirm-message]');
  const yesButton = modal?.querySelector('[data-confirm-yes]');
  const noButton = modal?.querySelector('[data-confirm-no]');
  let pendingForm = null;

  const setEditing = (form, editing) => {
    const input = form.querySelector('.profile-inline-input');
    const help = form.querySelector('[data-field-help]');
    const action = form.querySelector('[data-field-action]');
    if (!input || !action) return;

    form.classList.toggle('is-editing', editing);
    input.readOnly = !editing;
    action.setAttribute('aria-label', editing ? `Guardar ${form.dataset.label}` : `Editar ${form.dataset.label}`);
    action.setAttribute('title', editing ? `Guardar ${form.dataset.label}` : `Editar ${form.dataset.label}`);
    if (help) help.textContent = editing ? 'Editando · presiona el disquete para guardar' : 'Presiona el lápiz para editar';

    if (editing) {
      input.focus();
      input.setSelectionRange(input.value.length, input.value.length);
    }
  };

  const restoreOriginal = (form) => {
    const input = form.querySelector('.profile-inline-input');
    if (!input) return;
    input.value = input.dataset.original ?? '';
    setEditing(form, false);
  };

  const closeModal = () => {
    if (!modal) return;
    modal.hidden = true;
    document.body.classList.remove('profile-modal-open');
  };

  const openConfirmation = (form) => {
    if (!modal || !message) return;
    const input = form.querySelector('.profile-inline-input');
    if (!input) return;
    const label = form.dataset.label || 'dato';
    const oldValue = input.dataset.original || '';
    const newValue = input.value.trim();
    pendingForm = form;
    message.textContent = `¿Estás seguro de cambiar tu ${label} de “${oldValue}” a “${newValue}”?`;
    modal.hidden = false;
    document.body.classList.add('profile-modal-open');
    yesButton?.focus();
  };

  forms.forEach((form) => {
    const input = form.querySelector('.profile-inline-input');
    const action = form.querySelector('[data-field-action]');
    if (!input || !action) return;

    setEditing(form, false);

    action.addEventListener('click', () => {
      if (!form.classList.contains('is-editing')) {
        setEditing(form, true);
        return;
      }

      const nextValue = input.value.trim();
      const original = input.dataset.original || '';
      input.value = nextValue;

      if (!nextValue) {
        input.setCustomValidity(`El ${form.dataset.label || 'dato'} no puede quedar vacío.`);
        input.reportValidity();
        input.setCustomValidity('');
        input.focus();
        return;
      }

      if (nextValue.length > 80) {
        input.setCustomValidity('El valor no puede superar 80 caracteres.');
        input.reportValidity();
        input.setCustomValidity('');
        return;
      }

      if (nextValue === original) {
        setEditing(form, false);
        return;
      }

      openConfirmation(form);
    });

    input.addEventListener('keydown', (event) => {
      if (event.key === 'Escape' && form.classList.contains('is-editing')) {
        event.preventDefault();
        restoreOriginal(form);
      }
    });
  });

  yesButton?.addEventListener('click', () => {
    if (!pendingForm) return;
    yesButton.disabled = true;
    yesButton.textContent = 'Guardando...';
    pendingForm.submit();
  });

  noButton?.addEventListener('click', () => {
    if (pendingForm) restoreOriginal(pendingForm);
    pendingForm = null;
    closeModal();
  });

  modal?.addEventListener('click', (event) => {
    if (event.target === modal && pendingForm) {
      restoreOriginal(pendingForm);
      pendingForm = null;
      closeModal();
    }
  });

  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && modal && !modal.hidden && pendingForm) {
      restoreOriginal(pendingForm);
      pendingForm = null;
      closeModal();
    }
  });

  document.querySelectorAll('.code-input').forEach((input) => {
    input.addEventListener('input', () => {
      input.value = input.value.replace(/\D/g, '').slice(0, 6);
    });
    input.addEventListener('paste', (event) => {
      const value = (event.clipboardData?.getData('text') || '').replace(/\D/g, '').slice(0, 6);
      if (value) {
        event.preventDefault();
        input.value = value;
      }
    });
  });

  const emailStart = document.querySelector('[data-email-change-start]');
  emailStart?.addEventListener('submit', () => {
    const button = emailStart.querySelector('button[type="submit"]');
    if (button) {
      button.disabled = true;
      button.textContent = 'Enviando código...';
    }
  });
})();
