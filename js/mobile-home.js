(function () {
  const mq = window.matchMedia('(max-width: 768px)');
  function enhanceMobileHome() {
    if (!mq.matches) return;
    const searchInput = document.getElementById('searchInput');
    if (searchInput) searchInput.placeholder = 'O que seu pet precisa?';

    const productsTitle = document.querySelector('#homeProductsSection .section-head h2');
    if (productsTitle) productsTitle.textContent = 'Produtos recomendados';

    document.querySelectorAll('.image-hero-carousel img, .product-card img').forEach(function (img, index) {
      if (!img.hasAttribute('decoding')) img.setAttribute('decoding', 'async');
      if (index > 0 && !img.hasAttribute('loading')) img.setAttribute('loading', 'lazy');
    });
  }
  enhanceMobileHome();
  mq.addEventListener ? mq.addEventListener('change', enhanceMobileHome) : mq.addListener(enhanceMobileHome);
})();
