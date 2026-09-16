/* Lightweight, one-time legacy-logo fallback. No DOM observers or text scans. */
(() => {
  const brandLogo = 'Cokelat Minimalis Kedai Kopi Logo.png';
  const applyBrand = () => {
    document.querySelectorAll('img[src$="logo.png"]').forEach((image) => {
      image.src = brandLogo;
      image.alt = 'Logo Yojek';
    });
  };

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', applyBrand, { once: true });
  } else {
    applyBrand();
  }
})();
