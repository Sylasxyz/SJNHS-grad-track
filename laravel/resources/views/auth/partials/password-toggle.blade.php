<script>
  document.querySelectorAll('[data-toggle-password]').forEach((btn) => {
    const input = document.getElementById(btn.dataset.togglePassword);
    if (!input) return;
    btn.addEventListener('click', () => {
      const hidden = input.type === 'password';
      input.type = hidden ? 'text' : 'password';
      const icon = btn.querySelector('i');
      if (icon) icon.className = hidden ? 'bi bi-eye-slash' : 'bi bi-eye';
    });
  });
</script>
