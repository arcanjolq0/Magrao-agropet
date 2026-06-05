(() => {
  const form = document.getElementById('appointmentForm');
  if (!form) return;

  const statusBox = document.getElementById('appointmentStatus');
  const serviceSelect = document.getElementById('appointmentService');
  const params = new URLSearchParams(window.location.search);
  const requestedService = (params.get('servico') || '').toLowerCase();
  const serviceMap = {
    banho: 'banho',
    tosa: 'tosa',
    veterinario: 'veterinario',
    veterinarios: 'veterinario',
    'servicos-veterinarios': 'veterinario',
    'serviços-veterinários': 'veterinario'
  };

  if (serviceSelect && serviceMap[requestedService]) {
    serviceSelect.value = serviceMap[requestedService];
  }

  function setStatus(message, type = '') {
    if (!statusBox) return;
    statusBox.textContent = message || '';
    statusBox.className = `appointment-status-box ${type}`.trim();
  }

  function formToObject(fd) {
    const data = {};
    fd.forEach((value, key) => {
      data[key] = String(value || '').trim();
    });
    return data;
  }

  function fallbackWhatsApp(data) {
    const services = { banho: 'Banho', tosa: 'Tosa', veterinario: 'Serviços veterinários' };
    const lines = [
      'Olá, gostaria de agendar um atendimento na Magrão Agro Pet.',
      '',
      `Serviço: ${services[data.servico] || 'Serviço'}`,
      `Nome: ${data.nome_cliente || ''}`,
      `WhatsApp: ${data.whatsapp || ''}`,
    ];
    if (data.pet_nome) lines.push(`Pet: ${data.pet_nome}`);
    if (data.tipo_pet) lines.push(`Tipo de pet: ${data.tipo_pet}`);
    if (data.porte_pet) lines.push(`Porte/peso: ${data.porte_pet}`);
    if (data.data_preferida) lines.push(`Data preferida: ${data.data_preferida}`);
    if (data.periodo_preferido) lines.push(`Período preferido: ${data.periodo_preferido}`);
    if (data.observacoes) lines.push(`Observações: ${data.observacoes}`);
    lines.push('', 'Pode confirmar disponibilidade para esse horário?');
    return `https://wa.me/5547996329281?text=${encodeURIComponent(lines.join('\n'))}`;
  }

  form.addEventListener('submit', async (event) => {
    event.preventDefault();

    const data = formToObject(new FormData(form));
    const phoneInput = form.querySelector('[name="whatsapp"]');
    if (phoneInput) phoneInput.setCustomValidity('');

    if (data.empresa) return;
    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    if ((data.whatsapp || '').replace(/\D+/g, '').length < 10) {
      if (phoneInput) phoneInput.setCustomValidity('Informe um WhatsApp válido.');
      form.reportValidity();
      return;
    }

    const submitButton = form.querySelector('button[type="submit"]');
    if (submitButton) submitButton.disabled = true;
    setStatus('Registrando solicitação e preparando WhatsApp...', 'loading');

    if (window.MAGRAO_DATA?.staticDemo === true || !/^https?:$/.test(window.location.protocol)) {
      setStatus('Abrindo WhatsApp para confirmar a solicitação.', 'success');
      window.open(fallbackWhatsApp(data), '_blank', 'noopener');
      if (submitButton) submitButton.disabled = false;
      return;
    }

    let shouldOpenFallback = true;
    try {
      const response = await fetch('api/agendamentos.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
      });
      const result = await response.json();
      if (!response.ok || !result.success) {
        shouldOpenFallback = response.status >= 500;
        throw new Error(result.message || 'Não foi possível registrar o agendamento.');
      }

      setStatus(result.message || 'Solicitação registrada. Abrindo WhatsApp...', 'success');
      window.open(result.whatsappUrl, '_blank', 'noopener');
      form.reset();
      if (serviceSelect && serviceMap[requestedService]) serviceSelect.value = serviceMap[requestedService];
    } catch (error) {
      if (shouldOpenFallback) {
        setStatus(`${error.message} Abrindo WhatsApp mesmo assim.`, 'error');
        window.open(fallbackWhatsApp(data), '_blank', 'noopener');
      } else {
        setStatus(error.message || 'Não foi possível enviar a solicitação agora.', 'error');
      }
    } finally {
      if (submitButton) submitButton.disabled = false;
    }
  });
})();
