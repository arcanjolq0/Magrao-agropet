(function () {
  const form = document.getElementById("monthlyFeedForm");
  if (!form) return;

  const params = new URLSearchParams(window.location.search);
  const productFromUrl = params.get("produto");
  const productField = document.getElementById("monthlyProduct");
  const productSelect = document.getElementById("monthlyProductSelect");
  const preview = document.getElementById("monthlyPreview");

  if (productFromUrl && productField && !productField.value) {
    productField.value = productFromUrl;
  }

  function fieldValue(id) {
    return (document.getElementById(id)?.value || "").trim();
  }

  function normalizeText(value) {
    return String(value || "")
      .normalize("NFD")
      .replace(/[\u0300-\u036f]/g, "")
      .toLowerCase();
  }

  function escapeHtml(value = "") {
    return String(value ?? "").replace(/[&<>"']/g, char => ({
      "&": "&amp;",
      "<": "&lt;",
      ">": "&gt;",
      '"': "&quot;",
      "'": "&#039;"
    })[char]);
  }

  function formatPrice(value) {
    if (value === null || value === undefined || value === "") return "";
    const number = Number(value);
    if (!Number.isFinite(number)) return "";
    return number.toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
  }

  function productLabel(product) {
    const parts = [product.name];
    if (product.weight && product.weight !== "Consultar") parts.push(product.weight);
    if (product.priceMode === "consult") parts.push("sob consulta");
    else if (product.price) parts.push(formatPrice(product.price));
    return parts.filter(Boolean).join(" • ");
  }

  function isFeedProduct(product) {
    const content = normalizeText([
      product.name,
      product.category,
      product.categoryName,
      product.subcategory,
      product.description,
      Array.isArray(product.tags) ? product.tags.join(" ") : ""
    ].join(" "));
    return content.includes("racao") || content.includes("alimentacao") || content.includes("caes") || content.includes("gatos");
  }

  async function loadCatalogProducts() {
    let products = Array.isArray(window.MAGRAO_DATA?.products) ? window.MAGRAO_DATA.products : [];

    if (!/^https?:$/.test(window.location.protocol)) {
      return products.filter(product => product && product.active !== false);
    }

    try {
      const response = await fetch("api/produtos.php", {
        headers: { "Accept": "application/json" },
        cache: "no-store"
      });
      if (response.ok) {
        const payload = await response.json();
        if (payload && payload.success !== false && Array.isArray(payload.products)) {
          products = payload.products;
        }
      }
    } catch (error) {}

    return products.filter(product => product && product.active !== false);
  }

  function populateProductSelect(products) {
    if (!productSelect) return;

    const monthlyAllowed = products.filter(product => product.monthlyEnabled === true);
    const preferred = monthlyAllowed.length ? monthlyAllowed : products.filter(isFeedProduct);
    const items = preferred.length ? preferred : products;

    productSelect.innerHTML = "";

    const initialOption = document.createElement("option");
    initialOption.value = "";
    initialOption.textContent = items.length ? "Selecionar produto do catálogo (opcional)" : "Sem produtos carregados no catálogo";
    productSelect.appendChild(initialOption);

    items.forEach((product, index) => {
      const option = document.createElement("option");
      option.value = String(index);
      option.textContent = productLabel(product);
      option.dataset.productName = product.name || "";
      option.dataset.productWeight = product.weight || "";
      option.dataset.productCategory = product.category || product.categoryName || "";
      productSelect.appendChild(option);
    });

    productSelect.addEventListener("change", () => {
      const option = productSelect.selectedOptions[0];
      if (!option || !option.dataset.productName) return;
      const name = option.dataset.productName;
      const weight = option.dataset.productWeight;
      productField.value = weight && weight !== "Consultar" ? `${name} ${weight}` : name;
      updatePreview();
    });
  }

  function buildMessage() {
    const storeData = window.MAGRAO_DATA?.store || {};
    const delivery = fieldValue("monthlyDelivery");
    const address = fieldValue("monthlyAddress");
    const district = fieldValue("monthlyDistrict");
    const notes = fieldValue("monthlyNotes");
    const petName = fieldValue("monthlyPetName");
    const petSize = fieldValue("monthlyPetSize");

    return [
      storeData.monthlyIntro || "Olá, gostaria de agendar uma compra mensal de ração pela Magrão Agro Pet.",
      "",
      "DADOS DO CLIENTE",
      `Nome: ${fieldValue("monthlyName") || "-"}`,
      `WhatsApp: ${fieldValue("monthlyPhone") || "-"}`,
      "",
      "DADOS DO PET",
      `Tipo de pet: ${fieldValue("monthlyPetType") || "-"}`,
      petName ? `Nome do pet: ${petName}` : "",
      petSize && petSize !== "Não informado" ? `Porte/peso: ${petSize}` : "",
      "",
      "COMPRA PROGRAMADA",
      `Ração/produto desejado: ${fieldValue("monthlyProduct") || "-"}`,
      `Frequência: ${fieldValue("monthlyFrequency") || "-"}`,
      `Quantidade por ciclo: ${fieldValue("monthlyQty") || "-"}`,
      `Melhor dia: ${fieldValue("monthlyDay") || "-"}`,
      `Lembrete: ${fieldValue("monthlyReminder") || "-"}`,
      "",
      "RECEBIMENTO",
      `Forma de recebimento: ${delivery || "-"}`,
      district ? `Bairro: ${district}` : "",
      address ? `Endereço: ${address}` : "",
      `Pagamento preferido: ${fieldValue("monthlyPayment") || "A combinar"}`,
      notes ? `Observações: ${notes}` : "",
      "",
      "Por favor, confirme disponibilidade, preço e melhor forma de atendimento."
    ].filter(Boolean).join("\n");
  }

  function updatePreview() {
    if (!preview) return;
    const product = fieldValue("monthlyProduct");
    const frequency = fieldValue("monthlyFrequency") || "Mensal";
    const qty = fieldValue("monthlyQty") || "1 unidade/saco por ciclo";
    const day = fieldValue("monthlyDay") || "dia escolhido";
    const delivery = fieldValue("monthlyDelivery") || "forma de recebimento";

    preview.innerHTML = `
      <strong>Resumo do agendamento</strong>
      <p>${escapeHtml(product ? product : "Produto ainda não informado")}</p>
      <small>${escapeHtml(frequency)} • ${escapeHtml(qty)} • ${escapeHtml(day)} • ${escapeHtml(delivery)}</small>
    `;
  }

  form.querySelectorAll("input, select, textarea").forEach(element => {
    element.addEventListener("input", updatePreview);
    element.addEventListener("change", updatePreview);
  });

  loadCatalogProducts().then(populateProductSelect).finally(updatePreview);

  form.addEventListener("submit", function (event) {
    event.preventDefault();

    const phoneInput = document.getElementById("monthlyPhone");
    if (phoneInput) phoneInput.setCustomValidity("");

    if (!form.checkValidity()) {
      form.reportValidity();
      return;
    }
    if (phoneInput && fieldValue("monthlyPhone").replace(/\D+/g, "").length < 10) {
      phoneInput.setCustomValidity("Informe um WhatsApp válido.");
      form.reportValidity();
      return;
    }

    const storeData = window.MAGRAO_DATA?.store || {};
    const whatsapp = String(storeData.whatsapp || "5547996329281").replace(/\D/g, "");
    const message = buildMessage();

    window.open(`https://wa.me/${whatsapp}?text=${encodeURIComponent(message)}`, "_blank", "noopener");
  });
})();
