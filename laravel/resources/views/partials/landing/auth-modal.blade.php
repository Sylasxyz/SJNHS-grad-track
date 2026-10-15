@guest
<div class="modal fade auth-modal" id="authModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered auth-modal-dialog">
    <div class="modal-content auth-modal-content">
      <div class="auth-modal-head">
        <div class="visually-hidden" id="authModalTitle">Log in</div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="auth-modal-body">
        <iframe id="authFrame" class="auth-frame" src="about:blank" title="Authentication"></iframe>
      </div>
    </div>
  </div>
</div>

@push('scripts')
<script>
  (function () {
    const modalEl = document.getElementById('authModal');
    const frame = document.getElementById('authFrame');
    const title = document.getElementById('authModalTitle');
    if (!modalEl || !frame || !title) return;
    const modal = new bootstrap.Modal(modalEl);
    document.querySelectorAll('.js-open-auth').forEach((btn) => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        const mode = btn.getAttribute('data-auth-mode') || 'login';
        const src = mode === 'register'
          ? @json(route('register', ['embed' => 1]))
          : @json(route('login', ['embed' => 1]));
        title.textContent = mode === 'register' ? 'Create your account' : 'Log in';
        frame.src = src;
        modal.show();
      });
    });
    modalEl.addEventListener('hidden.bs.modal', () => {
      frame.src = 'about:blank';
    });
  })();
</script>
@endpush
@endguest
