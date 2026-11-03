// Auto-dismiss alerts after 5s
document.querySelectorAll('.alert-dismissible').forEach(el => {
  setTimeout(() => bootstrap.Alert.getOrCreateInstance(el).close(), 5000);
});
// Confirm delete buttons
document.querySelectorAll('[data-confirm]').forEach(el => {
  el.addEventListener('click', e => {
    if (!confirm(el.getAttribute('data-confirm'))) e.preventDefault();
  });
});