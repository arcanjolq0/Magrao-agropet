const baseData = window.MAGRAO_DATA || { store: {}, categories: [], brands: [], products: [] };
const store = baseData.store || {};
let categories = baseData.categories || [];
let brands = baseData.brands || [];
const catalogStatus = {
  loadedFromApi: false,
  error: null
};

function getDefaultProducts() {
  return (baseData.products || []).map(normalizeProduct);
}

function canUseApi() {
  if (baseData.staticDemo === true) return false;
  return /^https?:$/.test(window.location.protocol);
}

function loadProducts() {
  return getDefaultProducts();
}

function normalizeProduct(product) {
  const rawStock = product.stock;
  const numericStock = Number(rawStock);
  const hasNumericStock = rawStock !== undefined && rawStock !== null && rawStock !== "" && Number.isFinite(numericStock);
  const normalizedStock = hasNumericStock ? Math.max(0, Math.trunc(numericStock)) : rawStock;
  const inStock = product.inStock !== undefined ? Boolean(product.inStock) : (!hasNumericStock || normalizedStock > 0);
  const showStockQuantity = product.showStockQuantity === true || product.showStockQuantity === 1 || product.showStockQuantity === "1";
  const stockLabel = product.stockLabel || (hasNumericStock
    ? (normalizedStock > 0 ? (showStockQuantity ? `Em estoque: ${normalizedStock}` : "Em estoque") : "Sem estoque")
    : (rawStock || "Consultar disponibilidade"));

  return {
    active: product.active !== false,
    priceMode: product.priceMode || (product.price === null || product.price === undefined ? "consult" : "price"),
    image: product.image || "",
    gallery: Array.isArray(product.gallery) ? product.gallery : [],
    idealFor: product.idealFor || product.category || "Clientes da Magrão Agro Pet",
    benefits: Array.isArray(product.benefits) && product.benefits.length ? product.benefits : [
      "Produto disponível para consulta pelo WhatsApp",
      "Atendimento direto com a loja",
      "Ideal para compor pedidos do catálogo"
    ],
    ...product,
    stock: normalizedStock,
    showStockQuantity,
    stockLabel,
    inStock
  };
}

function productAvailable(product) {
  return product.inStock !== false;
}

function productStockLabel(product) {
  if (!productAvailable(product)) return "Sem estoque";
  const showStockQuantity = product.showStockQuantity === true || product.showStockQuantity === 1 || product.showStockQuantity === "1";
  const stockValue = Number(product.stock);
  if (showStockQuantity && Number.isFinite(stockValue) && stockValue > 0) {
    return `Em estoque: ${Math.trunc(stockValue)}`;
  }
  return product.stockLabel && !/^Em estoque:\s*\d+$/i.test(product.stockLabel) ? product.stockLabel : "Em estoque";
}

let products = loadProducts();

async function loadProductsFromApi() {
  if (!canUseApi()) return;

  try {
    const response = await fetch("api/produtos.php", {
      headers: { "Accept": "application/json" },
      cache: "no-store"
    });
    if (!response.ok) throw new Error("Resposta indisponivel");

    const payload = await response.json();
    if (!payload || payload.success === false || !Array.isArray(payload.products)) {
      throw new Error(payload?.message || "Catalogo indisponivel");
    }

    baseData.products = payload.products;
    baseData.categories = Array.isArray(payload.categories) ? payload.categories : baseData.categories;
    baseData.brands = Array.isArray(payload.brands) ? payload.brands : baseData.brands;
    categories = baseData.categories || [];
    brands = baseData.brands || [];
    products = loadProducts();
    catalogStatus.loadedFromApi = true;
    catalogStatus.error = null;
  } catch (error) {
    catalogStatus.error = "Nao foi possivel carregar o catalogo atualizado agora.";
  }
}

async function loadStoreSettingsFromApi() {
  if (!canUseApi()) {
    applyStoreSettingsToDom();
    return;
  }

  try {
    const response = await fetch("api/config.php", {
      headers: { "Accept": "application/json" },
      cache: "no-store"
    });
    if (!response.ok) throw new Error("Configuração indisponível");

    const payload = await response.json();
    if (!payload || payload.success === false || !payload.store) {
      throw new Error(payload?.message || "Dados da loja indisponíveis");
    }

    Object.assign(store, payload.store);
    Object.assign(baseData.store || {}, payload.store);
    applyStoreSettingsToDom();
  } catch (error) {
    applyStoreSettingsToDom();
  }
}

function buildWhatsAppUrl(text = "") {
  const number = String(store.whatsapp || "5547996329281").replace(/\D+/g, "") || "5547996329281";
  return `https://wa.me/${number}${text ? `?text=${encodeURIComponent(text)}` : ""}`;
}

function applyStoreSettingsToDom() {
  const whatsappNumber = String(store.whatsapp || "5547996329281").replace(/\D+/g, "") || "5547996329281";
  const instagramUrl = store.instagram || "https://www.instagram.com/magraoagropet/";

  document.querySelectorAll('a[href*="wa.me/"]').forEach(link => {
    try {
      const current = new URL(link.href, window.location.href);
      const message = current.searchParams.get("text") || "";
      link.href = `https://wa.me/${whatsappNumber}${message ? `?text=${encodeURIComponent(message)}` : ""}`;
    } catch (error) {
      link.href = `https://wa.me/${whatsappNumber}`;
    }
  });

  document.querySelectorAll('a[href*="instagram.com"]').forEach(link => {
    link.href = instagramUrl;
  });

  document.querySelectorAll('[data-store-address]').forEach(element => {
    if (store.address) element.textContent = store.address;
  });

  document.querySelectorAll('[data-store-footer-description]').forEach(element => {
    if (store.footerDescription) element.textContent = store.footerDescription;
  });

  document.querySelectorAll('[data-store-maps-link]').forEach(link => {
    if (store.mapsLink) link.href = store.mapsLink;
  });

  document.querySelectorAll('[data-store-maps-embed]').forEach(frame => {
    if (store.mapsEmbed) frame.src = store.mapsEmbed;
  });
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

function safeImageSrc(value = "") {
  const src = String(value || "").trim();
  if (!src || /[\u0000-\u001f<>"'`]/.test(src)) return "";
  if (/^(uploads\/produtos\/|assets\/|https?:\/\/)/i.test(src)) return encodeURI(src);
  return "";
}

function readJsonStorage(key, fallback) {
  try {
    const raw = localStorage.getItem(key);
    return raw ? JSON.parse(raw) : fallback;
  } catch (error) {
    try { localStorage.removeItem(key); } catch (storageError) {}
    return fallback;
  }
}

function writeJsonStorage(key, value) {
  try {
    localStorage.setItem(key, JSON.stringify(value));
  } catch (error) {}
}

function cleanFormText(value = "") {
  return String(value || "").replace(/[<>]/g, "").replace(/\s+/g, " ").trim();
}

function catalogNoticeMarkup() {
  if (!catalogStatus.error) return "";
  return `<div class="empty-state catalog-alert">${escapeHtml(catalogStatus.error)} Tente novamente em instantes ou chame a loja pelo WhatsApp.</div>`;
}

const state = {
  category: "Todos",
  subcategory: "Todos",
  brand: "Todas",
  price: "Todos",
  query: "",
  sort: "relevance",
  cart: readJsonStorage("magraoCart", []),
  favorites: readJsonStorage("magraoFavorites", []),
  lastOrder: readJsonStorage("magraoLastOrder", []),
  customer: readJsonStorage("magraoCustomer", {})
};

function money(value) {
  if (value === null || value === undefined || value === "") return "Sob consulta";
  return Number(value || 0).toLocaleString("pt-BR", { style: "currency", currency: "BRL" });
}

function hasPrice(product) {
  return product.priceMode !== "consult" && product.price !== null && product.price !== undefined && product.price !== "";
}

function priceLabel(product) {
  return hasPrice(product) ? money(product.price) : "Preço sob consulta";
}

function normalizeText(value = "") {
  return String(value).normalize("NFD").replace(/[\u0300-\u036f]/g, "").toLowerCase().replace(/ç/g, "c").trim();
}

function expandSearchTerms(value) {
  const text = normalizeText(value);
  const terms = [text];
  const synonyms = {
    "cao": ["cachorro", "caes", "cães"],
    "caes": ["cachorro", "cao", "cães"],
    "cachorro": ["cao", "caes", "cães"],
    "gato": ["gatos", "felino"],
    "areia": ["higienica", "higiênica", "gato"],
    "antipulgas": ["pulgas", "carrapatos", "farmacia", "farmácia"],
    "racao": ["ração", "alimento", "comida"],
    "jardim": ["jardinagem", "agro", "adubo"]
  };
  Object.entries(synonyms).forEach(([key, values]) => {
    if (text.includes(key)) terms.push(...values.map(normalizeText));
  });
  return [...new Set(terms.filter(Boolean))];
}

function saveCart() { writeJsonStorage("magraoCart", state.cart); }
function saveFavorites() { writeJsonStorage("magraoFavorites", state.favorites); }
function saveCustomer() { writeJsonStorage("magraoCustomer", state.customer); }
function saveLastOrder() {
  state.lastOrder = state.cart.map(item => ({ ...item }));
  writeJsonStorage("magraoLastOrder", state.lastOrder);
}

function getParam(name) { return new URLSearchParams(window.location.search).get(name); }
function productUrl(id) { return `produto.html?id=${id}`; }
function categoryUrl(category, subcategory = "") {
  let url = `produtos.html?categoria=${encodeURIComponent(category)}`;
  if (subcategory) url += `&subcategoria=${encodeURIComponent(subcategory)}`;
  return url;
}
function brandUrl(brand) { return `produtos.html?marca=${encodeURIComponent(brand)}`; }
function isHomePage() {
  return window.location.pathname.endsWith("index.html") || window.location.pathname === "/" || window.location.pathname.endsWith("/");
}
function visibleProducts() {
  return products.filter(product => product.active !== false);
}
function isFavorite(id) { return state.favorites.includes(Number(id)); }

function toggleFavorite(id) {
  const productId = Number(id);
  if (isFavorite(productId)) state.favorites = state.favorites.filter(item => item !== productId);
  else state.favorites.push(productId);
  saveFavorites();
  renderAllProductLists();
  renderProductPageFavoriteState();
}

function productVisual(product, className = "") {
  const image = safeImageSrc(product.image);
  const icon = escapeHtml(product.icon || "📦");
  if (image) {
    return `<img class="${escapeHtml(className)}" src="${image}" alt="${escapeHtml(product.name)}" loading="lazy" decoding="async" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';" /><span style="display:none">${icon}</span>`;
  }
  return `<span>${icon}</span>`;
}

function productCard(product) {
  const favorite = isFavorite(product.id);
  const price = hasPrice(product);
  const productId = Number(product.id);
  const available = productAvailable(product);
  const stockText = productStockLabel(product);
  return `
    <article class="product-card ${!price ? "consult-card" : ""} ${!available ? "out-of-stock" : ""}">
      <button class="favorite-btn ${favorite ? "active" : ""}" data-favorite="${productId}" aria-label="Favoritar ${escapeHtml(product.name)}">
        ${favorite ? "♥" : "♡"}
      </button>

      <a class="product-image" href="${productUrl(productId)}" aria-label="Abrir ${escapeHtml(product.name)}">
        ${productVisual(product)}
        <div class="product-tag">${escapeHtml(!price ? "Sob consulta" : (product.tag || "Produto"))}</div>
      </a>

      <div class="product-info">
        <span class="product-category">${escapeHtml(product.category)} - ${escapeHtml(product.subcategory)}</span>
        <h3>${escapeHtml(product.name)}</h3>
        <p>${escapeHtml(product.description)}</p>

        <div class="product-footer">
          <div class="price-box">
            <div>
              ${price && product.oldPrice ? `<del>${money(product.oldPrice)}</del>` : ""}
              <strong>${priceLabel(product)}</strong>
            </div>
            <span class="stock ${!available ? "out" : ""}">${escapeHtml(stockText)}</span>
          </div>

          <div class="card-actions">
            <button class="add-to-cart" ${available ? `data-add="${productId}"` : "disabled"}>${available ? (price ? "Adicionar" : "Consultar") : "Indisponível"}</button>
            <a class="view-product" href="${productUrl(productId)}" aria-label="Ver detalhes">→</a>
          </div>
        </div>
      </div>
    </article>
  `;
}

function productMatchesQuery(product, query) {
  if (!query) return true;
  const terms = expandSearchTerms(query);
  const searchable = normalizeText([
    product.name, product.category, product.subcategory, product.description,
    product.longDescription, product.tag, product.brand, product.weight, product.sku,
    ...(product.tags || [])
  ].join(" "));
  return terms.some(term => searchable.includes(term));
}

function priceFilterMatch(product) {
  if (state.price === "Todos") return true;
  if (state.price === "consult") return !hasPrice(product);
  if (state.price === "offers") return !!product.offer || !!product.oldPrice;
  if (!hasPrice(product)) return false;
  const price = Number(product.price);
  if (state.price === "0-50") return price <= 50;
  if (state.price === "50-100") return price > 50 && price <= 100;
  if (state.price === "100-200") return price > 100 && price <= 200;
  if (state.price === "200+") return price > 200;
  return true;
}

function getFilteredProducts() {
  let list = visibleProducts().filter(product => {
    const matchCategory = state.category === "Todos" || product.category === state.category;
    const matchSubcategory = state.subcategory === "Todos" || product.subcategory === state.subcategory;
    const matchBrand = state.brand === "Todas" || product.brand === state.brand;
    const matchPrice = priceFilterMatch(product);
    const matchQuery = productMatchesQuery(product, state.query);
    return matchCategory && matchSubcategory && matchBrand && matchPrice && matchQuery;
  });

  if (state.sort === "price-asc") list = [...list].sort((a,b) => (hasPrice(a) ? Number(a.price) : Infinity) - (hasPrice(b) ? Number(b.price) : Infinity));
  if (state.sort === "price-desc") list = [...list].sort((a,b) => (hasPrice(b) ? Number(b.price) : -1) - (hasPrice(a) ? Number(a.price) : -1));
  if (state.sort === "popular") list = [...list].sort((a,b) => Number(b.popular || 0) - Number(a.popular || 0));
  if (state.sort === "offers") list = [...list].sort((a,b) => Number(b.offer || !!b.oldPrice) - Number(a.offer || !!a.oldPrice));

  return list;
}

function renderProducts(limit = null) {
  const grid = document.getElementById("productsGrid");
  if (!grid) return;
  let list = getFilteredProducts();
  if (isHomePage()) {
    list = list
      .filter(product => product.showHome !== false)
      .sort((a, b) => Number(b.featured || 0) - Number(a.featured || 0) || Number(b.popular || 0) - Number(a.popular || 0) || Number(a.order || 0) - Number(b.order || 0));
  }
  const shown = limit ? list.slice(0, limit) : list;
  const counter = document.getElementById("productCounter");
  const activeLabel = document.getElementById("activeLabel");
  if (counter) counter.textContent = `${list.length} produto${list.length === 1 ? "" : "s"} encontrado${list.length === 1 ? "" : "s"}${state.query ? ` para “${state.query}”` : ""}`;
  if (activeLabel) {
    const parts = [];
    if (state.category !== "Todos") parts.push(state.category);
    if (state.subcategory !== "Todos") parts.push(state.subcategory);
    if (state.brand !== "Todas") parts.push(state.brand);
    if (state.price !== "Todos") parts.push("Filtro de preço");
    activeLabel.textContent = parts.length ? parts.join(" • ") : "Todos os produtos";
  }
  if (!shown.length && catalogStatus.error) {
    grid.innerHTML = catalogNoticeMarkup();
    return;
  }
  grid.innerHTML = catalogNoticeMarkup() + (shown.length ? shown.map(productCard).join("") : `
    <div class="empty-state">
      Nenhum produto encontrado. Tente limpar os filtros ou buscar outro termo.
      <br><br><button class="outline-btn" data-clear-filters>Limpar filtros</button>
    </div>`);
}

function renderOffersPage() {
  const grid = document.getElementById("offersGrid");
  if (!grid) return;
  let offerProducts = getFilteredProducts().filter(product => product.offer || product.oldPrice);
  const counter = document.getElementById("offersCounter");
  if (counter) counter.textContent = `${offerProducts.length} oferta${offerProducts.length === 1 ? "" : "s"} encontrada${offerProducts.length === 1 ? "" : "s"}`;
  if (!offerProducts.length && catalogStatus.error) {
    grid.innerHTML = catalogNoticeMarkup();
    return;
  }
  grid.innerHTML = catalogNoticeMarkup() + (offerProducts.length ? offerProducts.map(productCard).join("") : `<div class="empty-state">Nenhuma oferta encontrada com esses filtros.</div>`);
}

function renderFavoritesPage() {
  const grid = document.getElementById("favoritesGrid");
  if (!grid) return;
  const favoriteProducts = visibleProducts().filter(product => state.favorites.includes(product.id));
  if (!favoriteProducts.length && catalogStatus.error) {
    grid.innerHTML = catalogNoticeMarkup();
    return;
  }
  grid.innerHTML = favoriteProducts.length ? favoriteProducts.map(productCard).join("") : `
    <div class="empty-state">
      Você ainda não favoritou nenhum produto.
      <br><br><a class="outline-btn" href="produtos.html">Ver produtos</a>
    </div>`;
}

function renderAllProductLists() {
  renderProducts(isHomePage() ? 8 : null);
  renderOffersPage();
  renderFavoritesPage();
}

function renderCategoryCards() {
  const grid = document.getElementById("categoryGrid");
  if (!grid) return;
  grid.innerHTML = categories.map(category => `
    <a class="category-card" href="${categoryUrl(category.filter)}">
      <span>${escapeHtml(category.icon || "📦")}</span>
      <strong>${escapeHtml(category.name)}</strong>
      <small>${(category.subs || []).slice(0,4).map(escapeHtml).join(", ")}</small>
    </a>
  `).join("");
}

function renderBrandCards() {
  const grid = document.getElementById("brandsGrid");
  if (!grid) return;
  const derivedBrands = [...new Set(visibleProducts().map(product => product.brand).filter(Boolean))];
  const brandData = derivedBrands.map(name => {
    const registered = (brands || []).find(item => item.name === name);
    const count = visibleProducts().filter(product => product.brand === name).length;
    return { name, category: registered?.category || "Linha", description: registered?.description || "Linha de produtos disponível no catálogo.", count };
  });
  grid.innerHTML = brandData.map(brand => `
    <a class="brand-card" href="${brandUrl(brand.name)}">
      <span>🏷️</span>
      <strong>${escapeHtml(brand.name)}</strong>
      <small>${escapeHtml(brand.category)}</small>
      <p>${escapeHtml(brand.description)}</p>
      <b>${brand.count} produto${brand.count === 1 ? "" : "s"}</b>
    </a>
  `).join("");
}

function renderMegaMenu() {
  const megaGrid = document.getElementById("megaGrid");
  if (!megaGrid) return;
  megaGrid.innerHTML = categories.map(category => `
    <div class="mega-category">
      <a class="mega-category-top" href="${categoryUrl(category.filter)}">
        <span>${escapeHtml(category.icon || "📦")}</span>
        <strong>${escapeHtml(category.name)}</strong>
      </a>
      ${(category.subs || []).slice(0,6).map(sub => `<a href="${categoryUrl(category.filter, sub)}">${escapeHtml(sub)}</a>`).join("")}
    </div>
  `).join("");
}

function renderMobileCategories() {
  const list = document.getElementById("mobileCategoryList");
  if (!list) return;
  list.innerHTML = categories.map(category => `
    <div class="mobile-category-item">
      <strong><span>${escapeHtml(category.icon || "📦")}</span>${escapeHtml(category.name)}</strong>
      ${(category.subs || []).map(sub => `<a href="${categoryUrl(category.filter, sub)}">${escapeHtml(sub)}</a>`).join("")}
    </div>
  `).join("");
}

function currentSubcategories() {
  if (state.category === "Todos") return [...new Set(visibleProducts().map(product => product.subcategory))].sort();
  return [...new Set(visibleProducts().filter(product => product.category === state.category).map(product => product.subcategory))].sort();
}

function renderFilters() {
  const filterList = document.getElementById("filterList");
  const subfilterList = document.getElementById("subfilterList");
  const brandFilterList = document.getElementById("brandFilterList");
  const priceFilterList = document.getElementById("priceFilterList");
  const mobileFilterBar = document.getElementById("mobileFilterBar");
  const mobileSubFilterBar = document.getElementById("mobileSubFilterBar");

  const categoryButtons = [{ label: "Todos", value: "Todos" }, ...categories.map(cat => ({ label: cat.name, value: cat.filter }))];
  const categoryMarkup = categoryButtons.map(item => `<button class="filter ${state.category === item.value ? "active" : ""}" data-filter="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");
  const mobileCategoryMarkup = categoryButtons.map(item => `<button class="mobile-filter-chip ${state.category === item.value ? "active" : ""}" data-filter="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");
  const subButtons = [{ label: "Todas", value: "Todos" }, ...currentSubcategories().map(sub => ({ label: sub, value: sub }))];
  const subMarkup = subButtons.map(item => `<button class="sub-filter-chip ${state.subcategory === item.value ? "active" : ""}" data-subfilter="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");
  const brandButtons = [{ label: "Todas", value: "Todas" }, ...[...new Set(visibleProducts().map(p => p.brand).filter(Boolean))].sort().map(brand => ({ label: brand, value: brand }))];
  const brandMarkup = brandButtons.map(item => `<button class="sub-filter-chip ${state.brand === item.value ? "active" : ""}" data-brandfilter="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");
  const priceButtons = [
    { label: "Todos", value: "Todos" },
    { label: "Até R$ 50", value: "0-50" },
    { label: "R$ 50 a R$ 100", value: "50-100" },
    { label: "R$ 100 a R$ 200", value: "100-200" },
    { label: "Acima de R$ 200", value: "200+" },
    { label: "Ofertas", value: "offers" },
    { label: "Preço sob consulta", value: "consult" }
  ];
  const priceMarkup = priceButtons.map(item => `<button class="sub-filter-chip ${state.price === item.value ? "active" : ""}" data-pricefilter="${escapeHtml(item.value)}">${escapeHtml(item.label)}</button>`).join("");

  if (filterList) filterList.innerHTML = categoryMarkup;
  if (mobileFilterBar) mobileFilterBar.innerHTML = mobileCategoryMarkup;
  if (subfilterList) subfilterList.innerHTML = `<div class="subfilter-title">Subcategorias</div>${subMarkup}`;
  if (mobileSubFilterBar) mobileSubFilterBar.innerHTML = subMarkup;
  if (brandFilterList) brandFilterList.innerHTML = `<div class="subfilter-title">Marcas/Linhas</div>${brandMarkup}`;
  if (priceFilterList) priceFilterList.innerHTML = `<div class="subfilter-title">Preço</div>${priceMarkup}`;
  updateMobileFilterButtonState();
}

function setCategory(category, shouldScroll = true) {
  state.category = category;
  state.subcategory = "Todos";
  updateUrl();
  renderFilters();
  renderAllProductLists();
  if (shouldScroll && document.getElementById("catalogTop")) document.getElementById("catalogTop").scrollIntoView({ behavior: "smooth" });
}

function setSubcategory(subcategory, shouldScroll = true) {
  state.subcategory = subcategory;
  updateUrl();
  renderFilters();
  renderAllProductLists();
  if (shouldScroll && document.getElementById("catalogTop")) document.getElementById("catalogTop").scrollIntoView({ behavior: "smooth" });
}

function setBrand(brand, shouldScroll = true) {
  state.brand = brand;
  updateUrl();
  renderFilters();
  renderAllProductLists();
  if (shouldScroll && document.getElementById("catalogTop")) document.getElementById("catalogTop").scrollIntoView({ behavior: "smooth" });
}

function setPriceFilter(price, shouldScroll = true) {
  state.price = price;
  updateUrl();
  renderFilters();
  renderAllProductLists();
  if (shouldScroll && document.getElementById("catalogTop")) document.getElementById("catalogTop").scrollIntoView({ behavior: "smooth" });
}

function clearFilters() {
  state.category = "Todos";
  state.subcategory = "Todos";
  state.brand = "Todas";
  state.price = "Todos";
  state.query = "";
  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.value = "";
  updateSearchEnhancementState();
  updateUrl();
  renderFilters();
  renderAllProductLists();
}

const SEARCH_SUGGESTIONS = ["Ração", "Areia para gatos", "Petiscos", "Antipulgas", "Brinquedos"];

function isCatalogSearchPage() {
  return window.location.pathname.includes("produtos.html") || window.location.pathname.includes("ofertas.html");
}

function updateSearchEnhancementState() {
  const searchInput = document.getElementById("searchInput");
  const clearButton = document.querySelector("[data-clear-search]");
  if (!searchInput || !clearButton) return;

  clearButton.hidden = searchInput.value.trim() === "";
}

function performSearch(value, shouldScroll = true) {
  const query = value.trim();
  const searchInput = document.getElementById("searchInput");
  if (searchInput) searchInput.value = query;
  updateSearchEnhancementState();

  if (isCatalogSearchPage()) {
    state.query = query;
    updateUrl();
    renderAllProductLists();
    if (shouldScroll) document.getElementById("catalogTop")?.scrollIntoView({ behavior: "smooth" });
    return;
  }

  window.location.href = `produtos.html${query ? `?busca=${encodeURIComponent(query)}` : ""}`;
}

function clearSearchValue() {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.value = "";
    searchInput.focus();
  }
  updateSearchEnhancementState();

  if (isCatalogSearchPage()) {
    state.query = "";
    updateUrl();
    renderAllProductLists();
    document.getElementById("catalogTop")?.scrollIntoView({ behavior: "smooth" });
  }
}

function enhanceSearch() {
  const searchForm = document.getElementById("searchForm");
  const searchInput = document.getElementById("searchInput");
  if (!searchForm || !searchInput || searchForm.dataset.enhancedSearch === "true") return;

  searchForm.dataset.enhancedSearch = "true";
  searchForm.classList.add("search-enhanced");

  const submitButton = searchForm.querySelector('button[type="submit"], button:not([type])');
  if (submitButton) submitButton.classList.add("search-submit");

  const clearButton = document.createElement("button");
  clearButton.type = "button";
  clearButton.className = "search-clear";
  clearButton.dataset.clearSearch = "true";
  clearButton.setAttribute("aria-label", "Limpar busca");
  clearButton.hidden = true;
  clearButton.textContent = "×";

  const suggestions = document.createElement("div");
  suggestions.className = "search-suggestions";
  suggestions.setAttribute("aria-label", "Sugestões rápidas de busca");
  suggestions.innerHTML = SEARCH_SUGGESTIONS
    .map(suggestion => `<button type="button" data-search-suggestion="${escapeHtml(suggestion)}">${escapeHtml(suggestion)}</button>`)
    .join("");

  if (submitButton) {
    searchForm.insertBefore(clearButton, submitButton);
  } else {
    searchForm.appendChild(clearButton);
  }
  searchForm.appendChild(suggestions);

  searchInput.addEventListener("focus", () => searchForm.classList.add("search-suggestions-open"));
  searchForm.addEventListener("focusin", () => searchForm.classList.add("search-suggestions-open"));
  searchForm.addEventListener("focusout", () => {
    window.setTimeout(() => {
      if (!searchForm.contains(document.activeElement)) {
        searchForm.classList.remove("search-suggestions-open");
      }
    }, 120);
  });
  searchInput.addEventListener("input", updateSearchEnhancementState);

  updateSearchEnhancementState();
}

function updateUrl() {
  if (!window.location.pathname.includes("produtos.html") && !window.location.pathname.includes("ofertas.html")) return;
  const url = new URL(window.location.href);
  state.category === "Todos" ? url.searchParams.delete("categoria") : url.searchParams.set("categoria", state.category);
  state.subcategory === "Todos" ? url.searchParams.delete("subcategoria") : url.searchParams.set("subcategoria", state.subcategory);
  state.brand === "Todas" ? url.searchParams.delete("marca") : url.searchParams.set("marca", state.brand);
  state.price === "Todos" ? url.searchParams.delete("preco") : url.searchParams.set("preco", state.price);
  state.query ? url.searchParams.set("busca", state.query) : url.searchParams.delete("busca");
  history.replaceState(null, "", url);
}

function renderCart() {
  const cartCountElements = document.querySelectorAll("[data-cart-count], #cartCount");
  const cartItems = document.getElementById("cartItems");
  const cartTotal = document.getElementById("cartTotal");
  const pricedItems = state.cart.filter(item => hasPrice(item));
  const totalItems = state.cart.reduce((sum, item) => sum + item.qty, 0);
  const total = pricedItems.reduce((sum, item) => sum + item.qty * Number(item.price || 0), 0);
  const hasConsultItems = state.cart.some(item => !hasPrice(item));

  cartCountElements.forEach(el => el.textContent = totalItems);
  if (cartTotal) cartTotal.textContent = hasConsultItems ? `${money(total)} + itens sob consulta` : money(total);
  if (!cartItems) return;

  if (!state.cart.length) {
    cartItems.innerHTML = `<div class="cart-empty">
      <span>🛒</span>
      <strong>Seu carrinho está vazio.</strong>
      <small>Adicione produtos para montar seu pedido pelo WhatsApp.</small>
    </div>`;
    return;
  }

  cartItems.innerHTML = state.cart.map(item => {
    const price = hasPrice(item);
    const itemId = Number(item.id);
    const itemQty = Math.max(1, Number(item.qty) || 1);
    const itemImage = safeImageSrc(item.image);
    const itemIcon = escapeHtml(item.icon || "📦");
    const thumb = itemImage
      ? `<img src="${itemImage}" alt="${escapeHtml(item.name)}" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';" /><span style="display:none">${itemIcon}</span>`
      : `<span>${itemIcon}</span>`;

    return `
      <div class="cart-item ecommerce-cart-item">
        <div class="cart-thumb">${thumb}</div>

        <div class="cart-main">
          <strong>${escapeHtml(item.name)}</strong>
          <small>${escapeHtml(item.category || "")}${item.subcategory ? ` • ${escapeHtml(item.subcategory)}` : ""}</small>
          <div class="cart-unit-price">${price ? `${money(item.price)} cada` : "Preço sob consulta"}</div>
        </div>

        <div class="cart-side">
          <div class="qty">
            <button data-minus="${itemId}" aria-label="Diminuir quantidade">−</button>
            <input value="${itemQty}" min="1" inputmode="numeric" data-qty="${itemId}" aria-label="Quantidade" />
            <button data-plus="${itemId}" aria-label="Aumentar quantidade">+</button>
          </div>

          <div class="cart-subtotal">
            <span>Subtotal</span>
            <strong>${price ? money(item.price * itemQty) : "Sob consulta"}</strong>
          </div>

          <button class="remove-item" data-remove="${itemId}">Remover</button>
        </div>
      </div>`;
  }).join("");
}

function addToCart(id, qty = 1, open = true) {
  const product = products.find(item => item.id === Number(id));
  if (!product || !productAvailable(product)) return;
  const amount = Math.max(1, Number(qty) || 1);
  const item = state.cart.find(cartItem => cartItem.id === product.id);
  if (item) item.qty += amount;
  else state.cart.push({ ...product, qty: amount });
  saveCart();
  renderCart();
  if (open) openCart();
}

function changeQty(id, delta) {
  const item = state.cart.find(cartItem => cartItem.id === Number(id));
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) state.cart = state.cart.filter(cartItem => cartItem.id !== Number(id));
  saveCart();
  renderCart();
}

function setQty(id, qty) {
  const item = state.cart.find(cartItem => cartItem.id === Number(id));
  if (!item) return;
  item.qty = Math.max(1, Number(qty) || 1);
  saveCart();
  renderCart();
}

function removeFromCart(id) {
  state.cart = state.cart.filter(item => item.id !== Number(id));
  saveCart();
  renderCart();
}

function reorderLast() {
  if (!state.lastOrder.length) return;
  state.cart = state.lastOrder.map(item => ({ ...item }));
  saveCart();
  renderCart();
  openCart();
}

function openCart() {
  fillCustomerFields();
  document.getElementById("cartDrawer")?.classList.add("active");
  document.getElementById("cartOverlay")?.classList.add("active");
  document.body.classList.add("cart-open");
}
function closeCart() {
  saveCustomerFields();
  document.getElementById("cartDrawer")?.classList.remove("active");
  document.getElementById("cartOverlay")?.classList.remove("active");
  document.body.classList.remove("cart-open");
}
function openMobileCategories() {
  document.getElementById("mobileCategoriesPanel")?.classList.add("active");
  document.getElementById("mobileFilterOverlay")?.classList.add("active");
}
function closeMobileCategories() {
  document.getElementById("mobileCategoriesPanel")?.classList.remove("active");
  document.getElementById("mobileFilterOverlay")?.classList.remove("active");
}

function openMobileFilters() {
  const panel = document.getElementById("catalogFiltersPanel") || document.querySelector(".catalog-sidebar");
  if (!panel) return;
  panel.classList.add("active");
  document.getElementById("mobileFilterOverlay")?.classList.add("active");
  document.body.classList.add("mobile-sheet-open");
}

function closeMobileFilters() {
  const panel = document.getElementById("catalogFiltersPanel") || document.querySelector(".catalog-sidebar");
  panel?.classList.remove("active");
  document.getElementById("mobileFilterOverlay")?.classList.remove("active");
  document.body.classList.remove("mobile-sheet-open");
}

function activeFilterCount() {
  return [
    state.category !== "Todos",
    state.subcategory !== "Todos",
    state.brand !== "Todas",
    state.price !== "Todos"
  ].filter(Boolean).length;
}

function updateMobileFilterButtonState() {
  const count = activeFilterCount();
  document.querySelectorAll("[data-open-mobile-filters]").forEach(button => {
    button.classList.toggle("has-filters", count > 0);
    button.innerHTML = `<span>☰</span> Filtrar${count ? ` (${count})` : ""}`;
    button.setAttribute("aria-label", count ? `Abrir filtros, ${count} ativo${count === 1 ? "" : "s"}` : "Abrir filtros");
  });
}

function fillCustomerFields() {
  const name = document.getElementById("clientName");
  const phone = document.getElementById("clientPhone");
  const time = document.getElementById("clientTime");
  if (name && state.customer.name) name.value = state.customer.name;
  if (phone && state.customer.phone) phone.value = state.customer.phone;
  if (time && state.customer.time) time.value = state.customer.time;
}

function saveCustomerFields() {
  state.customer = {
    name: document.getElementById("clientName")?.value.trim() || state.customer.name || "",
    phone: document.getElementById("clientPhone")?.value.trim() || state.customer.phone || "",
    time: document.getElementById("clientTime")?.value.trim() || state.customer.time || ""
  };
  saveCustomer();
}

function buildWhatsappMessage() {
  if (!state.cart.length) return `${store.contactIntro || "Olá, Magrão Agro Pet! Tudo bem?"}\n\nGostaria de atendimento pelo site.`;

  const productLines = state.cart.map((item, index) => {
    const lines = [
      `${index + 1}. ${item.name}`,
      `   Quantidade: ${item.qty}`
    ];

    if (hasPrice(item)) {
      lines.push(`   Valor unitário: ${money(item.price)}`);
      lines.push(`   Subtotal: ${money(item.price * item.qty)}`);
    } else {
      lines.push("   Valor: sob consulta");
    }

    return lines.join("\n");
  });

  const pricedItems = state.cart.filter(item => hasPrice(item));
  const total = pricedItems.reduce((sum, item) => sum + Number(item.price || 0) * item.qty, 0);
  const hasConsultItems = state.cart.some(item => !hasPrice(item));

  return [
    store.checkoutIntro || "Olá, gostaria de fazer este pedido pelo site da Magrão Agro Pet:",
    "",
    "Itens do pedido:",
    ...productLines,
    "",
    hasConsultItems ? `Total estimado dos itens com preço: ${money(total)}` : `Total estimado: ${money(total)}`,
    hasConsultItems ? "Obs.: há item(ns) com preço sob consulta." : "",
    "",
    "Pode confirmar a disponibilidade dos itens e o valor final, por favor?"
  ].filter(line => line !== null && line !== undefined && line !== "").join("\n");
}

function copyOrderToClipboard() {
  const text = buildWhatsappMessage();
  navigator.clipboard?.writeText(text).then(() => alert("Pedido copiado."), () => alert(text));
}

function renderProductPageFavoriteState() {
  const button = document.querySelector("[data-product-favorite]");
  if (!button) return;
  const id = Number(button.dataset.productFavorite);
  const active = isFavorite(id);
  button.classList.toggle("active", active);
  button.textContent = active ? "♥" : "♡";
  button.setAttribute("aria-label", active ? "Remover dos favoritos" : "Favoritar produto");
}

function isMonthlyPurchaseEligible(product = {}) {
  const tags = Array.isArray(product.tags) ? product.tags : [];
  const text = normalizeText([
    product.name,
    product.category,
    product.subcategory,
    product.description,
    product.longDescription,
    ...tags
  ].filter(Boolean).join(" "));

  return /\bracao\b|\bracoes\b/.test(text);
}

function injectProductSchema(product) {
  const old = document.getElementById("productSchema");
  if (old) old.remove();
  const schema = {
    "@context": "https://schema.org",
    "@type": "Product",
    name: product.name,
    sku: product.sku,
    brand: { "@type": "Brand", name: product.brand },
    description: product.longDescription || product.description,
    offers: {
      "@type": "Offer",
      priceCurrency: "BRL",
      price: hasPrice(product) ? product.price : undefined,
      availability: "https://schema.org/InStock"
    }
  };
  const script = document.createElement("script");
  script.type = "application/ld+json";
  script.id = "productSchema";
  script.textContent = JSON.stringify(schema);
  document.head.appendChild(script);
}

function renderProductPage() {
  const root = document.getElementById("productDetail");
  if (!root) return;
  const id = Number(getParam("id")) || 1;
  const product = products.find(item => item.id === id) || visibleProducts()[0] || products[0];
  if (!product) {
    root.innerHTML = catalogNoticeMarkup() || `<div class="empty-state">Produto indisponivel no momento.</div>`;
    return;
  }

  document.title = `${product.name} | Magrão Agro Pet`;
  injectProductSchema(product);

  const productId = Number(product.id);
  const available = productAvailable(product);
  const stockText = productStockLabel(product);
  const stockBadgeText = available ? stockText : "Indisponível";
  const safeGallery = [product.image, ...(product.gallery || [])].map(safeImageSrc).filter(Boolean);
  const safeMainImage = safeImageSrc(product.image);
  const safeIcon = escapeHtml(product.icon || "📦");
  const safeName = escapeHtml(product.name);
  const safeCategory = escapeHtml(product.category || "Produtos");
  const safeSubcategory = escapeHtml(product.subcategory || "");
  const safeDescription = escapeHtml(product.longDescription || product.description || "Produto disponível na Magrão Agro Pet. Consulte a loja para confirmar disponibilidade e retirada.");
  const safeBrand = escapeHtml(product.brand || product.line || "Não informado");
  const safeWeight = escapeHtml(product.weight || product.size || "Consultar");
  const monthlyEligible = isMonthlyPurchaseEligible(product);

  const productWhatsAppText = [
    "Olá, gostaria de consultar este produto da Magrão Agro Pet.",
    "",
    `Produto: ${product.name}`,
    product.weight ? `Tamanho/variação: ${product.weight}` : "",
    product.brand ? `Marca/Linha: ${product.brand}` : "",
    hasPrice(product) ? `Preço exibido no site: ${priceLabel(product)}` : "Preço: sob consulta",
    "",
    "Pode confirmar disponibilidade, prazo e forma de retirada/entrega?"
  ].filter(Boolean).join("\n");

  const productWhatsAppUrl = buildWhatsAppUrl(productWhatsAppText);
  const monthlyUrl = `compra-mensal.html?produto=${encodeURIComponent(product.name)}`;
  const safeMainVisual = safeMainImage
    ? `<img src="${safeMainImage}" alt="${safeName}" onerror="this.style.display='none'; this.nextElementSibling.style.display='grid';" /><span style="display:none">${safeIcon}</span>`
    : `<span>${safeIcon}</span>`;
  const safeThumbMarkup = safeGallery.length > 1
    ? `<div class="thumbs">${safeGallery.slice(0, 4).map(img => `<button><img src="${img}" alt="${safeName}" loading="lazy" /></button>`).join("")}</div>`
    : "";

  const monthlyMarkup = monthlyEligible ? `
    <a class="outline-btn monthly-action" href="${monthlyUrl}">Programar compra mensal</a>
  ` : "";

  root.innerHTML = `
    <div class="breadcrumb product-breadcrumb">
      <a href="index.html">Início</a><span>›</span>
      <a href="produtos.html">Produtos</a><span>›</span>
      <a href="${categoryUrl(product.category)}">${safeCategory}</a>${safeSubcategory ? `<span>›</span><a href="${categoryUrl(product.category, product.subcategory)}">${safeSubcategory}</a>` : ""}<span>›</span>
      <span>${safeName}</span>
    </div>

    <div class="product-detail-grid refined-product-detail">
      <div class="gallery product-gallery-clean">
        <div class="main-product-image refined-product-image">
          <button class="product-image-heart ${isFavorite(productId) ? "active" : ""}" data-product-favorite="${productId}" aria-label="${isFavorite(productId) ? "Remover dos favoritos" : "Favoritar produto"}">${isFavorite(productId) ? "♥" : "♡"}</button>
          ${safeMainVisual}
        </div>
        ${safeThumbMarkup}
      </div>

      <section class="product-panel refined-product-panel">
        <div class="product-title refined-product-title">
          <span class="pill">${escapeHtml(!hasPrice(product) ? "Sob consulta" : (product.tag || "Produto"))}</span>
          <h1>${safeName}</h1>
          <p>${safeDescription}</p>
        </div>

        <div class="product-essential-meta" aria-label="Informações principais do produto">
          <div><small>Tamanho</small><strong>${safeWeight}</strong></div>
          <div><small>Marca/Linha</small><strong>${safeBrand}</strong></div>
        </div>

        <div class="product-price-area refined-price-area">
          <div class="price-box">
            ${hasPrice(product) && product.oldPrice ? `<del>${money(product.oldPrice)}</del>` : ""}
            <strong>${priceLabel(product)}</strong>
          </div>
          <span class="product-status ${!available ? "out" : ""}" title="${escapeHtml(stockText)}">${escapeHtml(stockBadgeText)}</span>
        </div>

        <div class="quantity-row refined-quantity-row">
          <label for="productQty">Quantidade</label>
          <input id="productQty" type="number" min="1" value="1" ${available ? "" : "disabled"} />
        </div>

        <div class="detail-actions refined-detail-actions">
          <button class="primary" ${available ? `data-add-detail="${productId}"` : "disabled"}>${available ? (hasPrice(product) ? "Adicionar ao carrinho" : "Adicionar para consulta") : "Indisponível"}</button>
          <a class="outline-btn buy-whatsapp-action" href="${productWhatsAppUrl}" target="_blank" rel="noopener">Consultar pelo WhatsApp</a>
          ${monthlyMarkup}
        </div>
      </section>
    </div>
  `;

  const safeRelatedGrid = document.getElementById("relatedGrid");
  if (safeRelatedGrid) {
    const safeRelated = visibleProducts().filter(item => item.category === product.category && item.id !== product.id).slice(0, 4);
    safeRelatedGrid.innerHTML = (safeRelated.length ? safeRelated : visibleProducts().filter(item => item.id !== product.id).slice(0, 4)).map(item => `
      <a class="related-card" href="${productUrl(item.id)}"><span>${escapeHtml(item.icon || "📦")}</span><strong>${escapeHtml(item.name)}</strong><small>${priceLabel(item)}</small></a>
    `).join("");
  }
}

function contactFormToWhatsApp(event) {
  const form = event.target.closest("#contactForm");
  if (!form) return;
  event.preventDefault();
  const phoneInput = document.getElementById("contactPhone");
  if (phoneInput) phoneInput.setCustomValidity("");

  if (!form.checkValidity()) {
    form.reportValidity();
    return;
  }

  const name = cleanFormText(document.getElementById("contactName").value);
  const phone = cleanFormText(phoneInput?.value || "");
  const subject = cleanFormText(document.getElementById("contactSubject").value);
  const message = cleanFormText(document.getElementById("contactMessage").value);
  if (phoneInput && phone.replace(/\D+/g, "").length < 10) {
    phoneInput.setCustomValidity("Informe um telefone ou WhatsApp válido.");
    form.reportValidity();
    return;
  }

  const text = [
    store.contactIntro || "Olá, estou entrando em contato pelo site da Magrão Agro Pet.",
    "",
    name ? `Nome: ${name}` : "",
    phone ? `Telefone: ${phone}` : "",
    subject ? `Assunto: ${subject}` : "",
    "",
    message ? `Mensagem: ${message}` : ""
  ].filter(Boolean).join("\n");
  window.open(buildWhatsAppUrl(text), "_blank", "noopener");
}


function initHeroCarousel() {
  const carousel = document.getElementById("heroCarousel");
  if (!carousel) return;

  const slides = [...carousel.querySelectorAll(".hero-slide")];
  if (!slides.length) return;

  let current = Math.max(0, slides.findIndex(slide => slide.classList.contains("active")));
  let intervalId = null;
  let touchStartX = 0;

  function getOrCreateButton(className, label, symbol) {
    let button = carousel.querySelector(`.${className}`);
    if (!button) {
      button = document.createElement("button");
      button.type = "button";
      button.className = `hero-nav ${className}`;
      button.setAttribute("aria-label", label);
      button.innerHTML = `<span aria-hidden="true">${symbol}</span>`;
      carousel.appendChild(button);
    }
    return button;
  }

  function getOrCreateDots() {
    let dots = carousel.querySelector(".hero-dots");
    if (!dots) {
      dots = document.createElement("div");
      dots.className = "hero-dots";
      dots.setAttribute("aria-label", "Selecionar imagem do carrossel");
      carousel.appendChild(dots);
    }
    dots.innerHTML = "";
    return dots;
  }

  const prevButton = getOrCreateButton("hero-prev", "Imagem anterior", "‹");
  const nextButton = getOrCreateButton("hero-next", "Próxima imagem", "›");
  const dots = getOrCreateDots();
  const dotButtons = slides.map((_, index) => {
    const dot = document.createElement("button");
    dot.type = "button";
    dot.setAttribute("aria-label", `Ir para imagem ${index + 1}`);
    dot.addEventListener("click", () => {
      showSlide(index);
      startAutoPlay();
    });
    dots.appendChild(dot);
    return dot;
  });

  function showSlide(index) {
    current = (index + slides.length) % slides.length;
    slides.forEach((slide, i) => {
      const isActive = i === current;
      slide.classList.toggle("active", isActive);
      slide.setAttribute("aria-hidden", String(!isActive));
    });
    dotButtons.forEach((dot, i) => {
      const isActive = i === current;
      dot.classList.toggle("active", isActive);
      dot.setAttribute("aria-current", isActive ? "true" : "false");
    });
  }

  function goToPrevious() {
    showSlide(current - 1);
    startAutoPlay();
  }

  function goToNext() {
    showSlide(current + 1);
    startAutoPlay();
  }

  function startAutoPlay() {
    stopAutoPlay();
    if (slides.length > 1) intervalId = setInterval(() => showSlide(current + 1), 6000);
  }

  function stopAutoPlay() {
    if (intervalId) {
      clearInterval(intervalId);
      intervalId = null;
    }
  }

  prevButton.addEventListener("click", goToPrevious);
  nextButton.addEventListener("click", goToNext);
  carousel.addEventListener("mouseenter", stopAutoPlay);
  carousel.addEventListener("mouseleave", startAutoPlay);
  carousel.addEventListener("focusin", stopAutoPlay);
  carousel.addEventListener("focusout", startAutoPlay);
  carousel.addEventListener("touchstart", event => {
    touchStartX = event.changedTouches[0].clientX;
  }, { passive: true });
  carousel.addEventListener("touchend", event => {
    const touchEndX = event.changedTouches[0].clientX;
    const distance = touchEndX - touchStartX;
    if (Math.abs(distance) > 45) distance > 0 ? goToPrevious() : goToNext();
  }, { passive: true });

  showSlide(current);
  startAutoPlay();
}


const defaultStoreHoursConfig = {
  timezone: "America/Sao_Paulo",
  days: [
    { key: "sun", label: "domingo", short: "Dom", open: false, from: "08:00", to: "14:00" },
    { key: "mon", label: "segunda-feira", short: "Seg", open: true, from: "08:00", to: "19:00" },
    { key: "tue", label: "terça-feira", short: "Ter", open: true, from: "08:00", to: "19:00" },
    { key: "wed", label: "quarta-feira", short: "Qua", open: true, from: "08:00", to: "19:00" },
    { key: "thu", label: "quinta-feira", short: "Qui", open: true, from: "08:00", to: "19:00" },
    { key: "fri", label: "sexta-feira", short: "Sex", open: true, from: "08:00", to: "19:00" },
    { key: "sat", label: "sábado", short: "Sáb", open: true, from: "08:00", to: "14:00" }
  ]
};

let storeHoursConfig = normalizeStoreHoursConfig(store.hoursConfig || defaultStoreHoursConfig);
store.hours = store.hours || buildStoreHoursSummary(storeHoursConfig);

function isValidStoreTime(value) {
  return /^(?:[01]\d|2[0-3]):[0-5]\d$/.test(String(value || ""));
}

function normalizeStoreHoursConfig(config = {}) {
  const receivedDays = Array.isArray(config.days) ? config.days : [];
  const byKey = Object.fromEntries(receivedDays.filter(Boolean).map(day => [day.key, day]));

  const days = defaultStoreHoursConfig.days.map(defaultDay => {
    const current = byKey[defaultDay.key] || {};
    return {
      ...defaultDay,
      open: typeof current.open === "boolean" ? current.open : defaultDay.open,
      from: isValidStoreTime(current.from) ? current.from : defaultDay.from,
      to: isValidStoreTime(current.to) ? current.to : defaultDay.to
    };
  });

  return {
    timezone: typeof config.timezone === "string" && config.timezone.trim() ? config.timezone.trim() : defaultStoreHoursConfig.timezone,
    days,
    summary: typeof config.summary === "string" && config.summary.trim() ? config.summary.trim() : ""
  };
}

function storeTimeToMinutes(value) {
  if (!isValidStoreTime(value)) return 0;
  const [hours, minutes] = value.split(":").map(Number);
  return (hours * 60) + minutes;
}

function formatStoreTime(value) {
  if (!isValidStoreTime(value)) return value;
  const [hours, minutes] = value.split(":");
  return minutes === "00" ? `${Number(hours)}h` : `${Number(hours)}h${minutes}`;
}

function compactDayRange(days) {
  const names = days.map(day => day.short);
  if (names.length === 1) return names[0];
  if (names.length === 2) return `${names[0]} e ${names[1]}`;
  return `${names[0]} a ${names[names.length - 1]}`;
}

function buildStoreHoursSummary(config = storeHoursConfig) {
  const groups = [];
  let current = null;

  config.days.forEach(day => {
    if (!day.open) {
      if (current) groups.push(current);
      current = null;
      return;
    }

    const signature = `${day.from}-${day.to}`;
    if (!current || current.signature !== signature) {
      if (current) groups.push(current);
      current = { signature, from: day.from, to: day.to, days: [day] };
    } else {
      current.days.push(day);
    }
  });

  if (current) groups.push(current);
  if (!groups.length) return "Atendimento temporariamente fechado";

  return groups.map(group => `${compactDayRange(group.days)}: ${formatStoreTime(group.from)} às ${formatStoreTime(group.to)}`).join(" · ");
}

async function loadStoreHoursFromApi() {
  if (!canUseApi()) return;

  try {
    const response = await fetch("api/horarios.php", {
      headers: { "Accept": "application/json" },
      cache: "no-store"
    });
    if (!response.ok) throw new Error("Horários indisponíveis");

    const payload = await response.json();
    if (!payload || !payload.hours) throw new Error("Resposta inválida");

    storeHoursConfig = normalizeStoreHoursConfig(payload.hours);
    storeHoursConfig.summary = payload.hours.summary || buildStoreHoursSummary(storeHoursConfig);
    store.hoursConfig = storeHoursConfig;
    store.hours = storeHoursConfig.summary;
  } catch (error) {
    storeHoursConfig.summary = storeHoursConfig.summary || buildStoreHoursSummary(storeHoursConfig);
    store.hoursConfig = storeHoursConfig;
    store.hours = store.hours || storeHoursConfig.summary;
  }
}

function getStoreDayAndMinutes(timezone = storeHoursConfig.timezone) {
  const weekdayMap = { Sun: 0, Mon: 1, Tue: 2, Wed: 3, Thu: 4, Fri: 5, Sat: 6 };
  let day = new Date().getDay();
  let minutes = new Date().getHours() * 60 + new Date().getMinutes();

  try {
    const parts = new Intl.DateTimeFormat("en-US", {
      timeZone: timezone || "America/Sao_Paulo",
      weekday: "short",
      hour: "2-digit",
      minute: "2-digit",
      hour12: false,
      hourCycle: "h23"
    }).formatToParts(new Date());
    const values = Object.fromEntries(parts.map(part => [part.type, part.value]));
    day = weekdayMap[values.weekday] ?? day;
    minutes = Number(values.hour || 0) * 60 + Number(values.minute || 0);
  } catch (error) {}

  return { day, minutes };
}

function nextOpenLabel(currentDay, currentMinutes, config = storeHoursConfig) {
  for (let offset = 0; offset <= 7; offset += 1) {
    const index = (currentDay + offset) % 7;
    const day = config.days[index];
    if (!day || !day.open) continue;

    const opening = storeTimeToMinutes(day.from);
    if (offset === 0 && currentMinutes >= opening) continue;

    if (offset === 0) return `hoje às ${formatStoreTime(day.from)}`;
    if (offset === 1) return `amanhã às ${formatStoreTime(day.from)}`;
    return `${day.label} às ${formatStoreTime(day.from)}`;
  }

  return "em breve";
}

function getStoreStatusNow() {
  const config = normalizeStoreHoursConfig(storeHoursConfig);
  const { day, minutes } = getStoreDayAndMinutes(config.timezone);
  const today = config.days[day];
  const isOpen = Boolean(today?.open) && minutes >= storeTimeToMinutes(today.from) && minutes < storeTimeToMinutes(today.to);

  return {
    isOpen,
    nextOpen: nextOpenLabel(day, minutes, config),
    summary: config.summary || store.hours || buildStoreHoursSummary(config)
  };
}

function refreshStoreHoursText() {
  const summary = store.hours || buildStoreHoursSummary(storeHoursConfig);
  document.querySelectorAll("[data-store-hours]").forEach(element => {
    element.textContent = summary;
  });
}

function updateStoreStatusBar() {
  const bar = document.getElementById("storeStatusBar") || document.querySelector(".store-status-bar");
  refreshStoreHoursText();
  if (!bar) return;

  const status = getStoreStatusNow();
  if (status.isOpen) {
    bar.hidden = true;
    bar.classList.remove("is-closed");
    return;
  }

  bar.hidden = false;
  bar.classList.add("is-closed");
  bar.innerHTML = `
    <span class="store-status-pill">🔒 Loja fechada agora</span>
    <span>Reabre ${escapeHtml(status.nextOpen)}</span>
    <a href="${buildWhatsAppUrl()}" target="_blank" rel="noopener">💬 Chamar no WhatsApp</a>
    <span>⏰ ${escapeHtml(status.summary)}</span>
  `;
}

setInterval(updateStoreStatusBar, 60000);
updateStoreStatusBar();


function focusMobileSearch() {
  const searchInput = document.getElementById("searchInput");
  if (searchInput) {
    searchInput.focus({ preventScroll: false });
    document.getElementById("searchForm")?.classList.add("search-suggestions-open");
    searchInput.scrollIntoView({ behavior: "smooth", block: "center" });
    return;
  }
  window.location.href = "produtos.html?focusBusca=1";
}

function initMobileProNavigation() {
  const path = (window.location.pathname.split("/").pop() || "index.html").toLowerCase();
  document.querySelectorAll("[data-mobile-bottom-nav] [data-nav]").forEach(item => item.classList.remove("active"));

  let active = "";
  if (path === "" || path === "index.html") active = "home";
  else if (path === "favoritos.html") active = "favorites";
  else if (path === "produtos.html" || path === "ofertas.html") active = "search";

  if (active) {
    document.querySelectorAll(`[data-mobile-bottom-nav] [data-nav="${active}"]`).forEach(item => item.classList.add("active"));
  }

  if (getParam("focusBusca") === "1") {
    window.setTimeout(focusMobileSearch, 180);
  }
}

function initializePage() {
  updateStoreStatusBar();
  enhanceSearch();
  initMobileProNavigation();
  const categoryFromUrl = getParam("categoria");
  const subcategoryFromUrl = getParam("subcategoria");
  const brandFromUrl = getParam("marca");
  const priceFromUrl = getParam("preco");
  const searchFromUrl = getParam("busca");
  if (categoryFromUrl) state.category = categoryFromUrl;
  if (subcategoryFromUrl) state.subcategory = subcategoryFromUrl;
  if (brandFromUrl) state.brand = brandFromUrl;
  if (priceFromUrl) state.price = priceFromUrl;
  if (searchFromUrl) {
    state.query = searchFromUrl;
    const searchInput = document.getElementById("searchInput");
    if (searchInput) searchInput.value = searchFromUrl;
  }
  updateSearchEnhancementState();
  renderMegaMenu();
  renderMobileCategories();
  renderCategoryCards();
  renderFilters();
  renderAllProductLists();
  renderBrandCards();
  renderProductPage();
  renderCart();
  initHeroCarousel();
}

document.addEventListener("click", event => {
  const add = event.target.closest("[data-add]");
  const addDetail = event.target.closest("[data-add-detail]");
  const plus = event.target.closest("[data-plus]");
  const minus = event.target.closest("[data-minus]");
  const remove = event.target.closest("[data-remove]");
  const filter = event.target.closest("[data-filter]");
  const subfilter = event.target.closest("[data-subfilter]");
  const brandfilter = event.target.closest("[data-brandfilter]");
  const pricefilter = event.target.closest("[data-pricefilter]");
  const clearFiltersBtn = event.target.closest("[data-clear-filters]");
  const clearSearchBtn = event.target.closest("[data-clear-search]");
  const focusSearchBtn = event.target.closest("[data-focus-search]");
  const searchSuggestion = event.target.closest("[data-search-suggestion]");
  const favorite = event.target.closest("[data-favorite]");
  const productFavorite = event.target.closest("[data-product-favorite]");
  const openCartBtn = event.target.closest("[data-open-cart], #cartBtn");
  const closeCartBtn = event.target.closest("[data-close-cart], #closeCart");
  const clearCartBtn = event.target.closest("[data-clear-cart], #clearCart");
  const copyOrderBtn = event.target.closest("[data-copy-order]");
  const continueBtn = event.target.closest("[data-continue-shopping]");
  const reorderBtn = event.target.closest("[data-reorder-last]");
  const openCats = event.target.closest("[data-open-categories]");
  const closeCats = event.target.closest("[data-close-categories]");
  const openFilters = event.target.closest("[data-open-mobile-filters]");
  const closeFilters = event.target.closest("[data-close-mobile-filters]");
  const overlay = event.target.closest("#cartOverlay, #mobileFilterOverlay");
  const tab = event.target.closest("[data-tab]");

  if (add) addToCart(add.dataset.add);
  if (addDetail) addToCart(addDetail.dataset.addDetail, document.getElementById("productQty")?.value || 1);
  if (plus) changeQty(plus.dataset.plus, 1);
  if (minus) changeQty(minus.dataset.minus, -1);
  if (remove) removeFromCart(remove.dataset.remove);
  if (filter) setCategory(filter.dataset.filter);
  if (subfilter) setSubcategory(subfilter.dataset.subfilter);
  if (brandfilter) setBrand(brandfilter.dataset.brandfilter);
  if (pricefilter) setPriceFilter(pricefilter.dataset.pricefilter);
  if (clearFiltersBtn) clearFilters();
  if (clearSearchBtn) clearSearchValue();
  if (focusSearchBtn) focusMobileSearch();
  if (searchSuggestion) {
    performSearch(searchSuggestion.dataset.searchSuggestion || "");
    document.getElementById("searchForm")?.classList.remove("search-suggestions-open");
  }
  if (favorite) toggleFavorite(favorite.dataset.favorite);
  if (productFavorite) toggleFavorite(productFavorite.dataset.productFavorite);
  if (openCartBtn) openCart();
  if (closeCartBtn || continueBtn) closeCart();
  if (copyOrderBtn) copyOrderToClipboard();
  if (reorderBtn) reorderLast();
  if (clearCartBtn) { state.cart = []; saveCart(); renderCart(); }
  if (openCats) openMobileCategories();
  if (closeCats) closeMobileCategories();
  if (openFilters) openMobileFilters();
  if (closeFilters) closeMobileFilters();
  if (overlay) { closeCart(); closeMobileCategories(); closeMobileFilters(); }

  if (tab) {
    const name = tab.dataset.tab;
    document.querySelectorAll("[data-tab]").forEach(btn => btn.classList.toggle("active", btn.dataset.tab === name));
    document.querySelectorAll("[data-panel]").forEach(panel => panel.classList.toggle("active", panel.dataset.panel === name));
  }
});

document.addEventListener("input", event => {
  const qtyInput = event.target.closest("[data-qty]");
  if (qtyInput) setQty(qtyInput.dataset.qty, qtyInput.value);
});

document.addEventListener("submit", event => {
  const searchForm = event.target.closest("#searchForm");
  if (searchForm) {
    event.preventDefault();
    const value = document.getElementById("searchInput")?.value.trim() || "";
    performSearch(value);
  }
  contactFormToWhatsApp(event);
});

document.addEventListener("change", event => {
  const sortSelect = event.target.closest("#sortSelect");
  if (sortSelect) {
    state.sort = sortSelect.value;
    renderProducts();
  }
});

document.getElementById("menuBtn")?.addEventListener("click", () => {
  document.getElementById("mobileMenu")?.classList.toggle("active");
});

document.getElementById("whatsappCheckout")?.addEventListener("click", () => {
  saveLastOrder();
  const url = buildWhatsAppUrl(buildWhatsappMessage());
  window.open(url, "_blank", "noopener");
});

document.addEventListener("keydown", event => {
  if (event.key === "Escape") {
    closeCart();
    closeMobileCategories();
    closeMobileFilters();
    document.getElementById("mobileMenu")?.classList.remove("active");
  }
});

Promise.all([loadProductsFromApi(), loadStoreHoursFromApi(), loadStoreSettingsFromApi()]).finally(initializePage);
