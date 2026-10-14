@extends('layouts.app')

@section('title', 'Register')

@php
    $withEmbed = fn (string $name) => route($name, $embed ? ['embed' => 1] : []);
    $selectedRole = old('role', 'Student');
    $registrationErrors = session('registration_errors', []);
    $forms = [
        'Student' => ['prefix' => 'student', 'title' => 'Student Registration Form', 'idField' => 'student_no', 'idLabel' => 'Student No.', 'button' => 'Register as Student'],
        'Instructor' => ['prefix' => 'instructor', 'title' => 'Instructor Registration Form', 'idField' => 'employee_id', 'idLabel' => 'Employee ID', 'button' => 'Register as Instructor'],
    ];
@endphp

@section('content')
  <x-auth-split-card title="Register" side-prompt="Already a member?" side-link-label="Log in" :side-link-url="$withEmbed('login')">
    @if (session('success'))
      <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if (session('warning'))
      <div class="alert alert-warning"><ul class="mb-0"><li>{{ session('warning') }}</li></ul></div>
    @endif

    @if (session('error'))
      <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if ($registrationErrors)
      <div class="alert alert-danger">
        <ul class="mb-0">
          @foreach ($registrationErrors as $message)
            <li>{{ $message }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <form method="post" action="{{ route('register.store') }}" autocomplete="off">
      @csrf
      @if ($embed)<input type="hidden" name="embed" value="1">@endif
      <input type="hidden" name="role" id="role" value="{{ $selectedRole }}">

      <div class="mb-3">
        <div class="fw-semibold mb-2">Choose your role</div>
        <div class="role-cards">
          <div class="role-card {{ $selectedRole === 'Student' ? 'is-selected' : '' }}" data-role="Student" tabindex="0" role="radio" aria-checked="{{ $selectedRole === 'Student' ? 'true' : 'false' }}">
            <div class="role-card-check" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
            <div class="d-flex gap-3 align-items-start">
              <div class="role-card-icon"><i class="bi bi-mortarboard fs-4"></i></div>
              <div>
                <div class="fw-bold">Student / Graduate</div>
                <div class="text-muted small">Register to update your employment / further studies status.</div>
              </div>
            </div>
          </div>
          <div class="role-card {{ $selectedRole === 'Instructor' ? 'is-selected' : '' }}" data-role="Instructor" tabindex="0" role="radio" aria-checked="{{ $selectedRole === 'Instructor' ? 'true' : 'false' }}">
            <div class="role-card-check" aria-hidden="true"><i class="bi bi-check-lg"></i></div>
            <div class="d-flex gap-3 align-items-start">
              <div class="role-card-icon"><i class="bi bi-person-video3 fs-4"></i></div>
              <div>
                <div class="fw-bold">Teacher / Instructor</div>
                <div class="text-muted small">Register to monitor graduates and verify/update records.</div>
              </div>
            </div>
          </div>
        </div>
        <div class="small mt-2">
          Selected role:
          <span class="fw-bold text-success" id="selectedRoleLabel">{{ $selectedRole }}</span>
        </div>
        <div class="form-text">Accounts require Admin verification before login.</div>
      </div>

      @foreach ($forms as $formRole => $f)
        @php $p = $f['prefix']; @endphp
        <div id="{{ $p }}Form" class="d-none register-form-panel" data-role-panel="{{ $formRole }}">
          <h2 class="h6 mb-3">{{ $f['title'] }}</h2>
          <div class="row g-2 mb-3 align-items-end">
            <div class="col-12 col-md-3">
              <label class="form-label" for="{{ $p }}_first_name">First Name</label>
              <input class="form-control" id="{{ $p }}_first_name" name="first_name" value="{{ old('first_name') }}" data-required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="{{ $p }}_last_name">Last Name</label>
              <input class="form-control" id="{{ $p }}_last_name" name="last_name" value="{{ old('last_name') }}" data-required>
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="{{ $p }}_middle_initial">M.I. <span class="text-muted fw-normal">(if you have)</span></label>
              <input class="form-control text-uppercase" maxlength="2" id="{{ $p }}_middle_initial" name="middle_initial" value="{{ old('middle_initial') }}" autocomplete="off">
            </div>
            <div class="col-12 col-md-3">
              <label class="form-label" for="{{ $p }}_name_suffix">Suffix <span class="text-muted fw-normal">(if you have)</span></label>
              <select class="form-select" id="{{ $p }}_name_suffix" name="name_suffix">
                <option value="">None</option>
                @foreach ($suffixes as $suffix)
                  <option value="{{ $suffix }}" @selected(old('name_suffix') === $suffix)>{{ $suffix }}</option>
                @endforeach
              </select>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="{{ $p }}_email">Email Address</label>
            <input class="form-control" type="email" id="{{ $p }}_email" name="{{ $p }}_email" value="{{ old($p.'_email') }}" data-required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="{{ $f['idField'] }}">{{ $f['idLabel'] }}</label>
            <input class="form-control" id="{{ $f['idField'] }}" name="{{ $f['idField'] }}" value="{{ old($f['idField']) }}" data-required>
          </div>
          <div class="mb-3">
            <label class="form-label" for="{{ $p }}_password">Password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="{{ $p }}_password" name="password" autocomplete="new-password" data-required>
              <button class="btn btn-outline-secondary" type="button" data-toggle-password="{{ $p }}_password" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
            <div class="pw-strength mt-2" data-pw-for="{{ $p }}_password">
              <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                <span class="small text-muted">Password strength</span>
                <span class="small fw-semibold pw-strength-label">—</span>
              </div>
              <div class="pw-strength-bar" aria-hidden="true"><span class="pw-strength-fill"></span></div>
              <ul class="pw-strength-rules list-unstyled mb-0 mt-2 small">
                <li data-rule="length"><i class="bi bi-circle"></i> At least 8 characters</li>
                <li data-rule="upper"><i class="bi bi-circle"></i> One uppercase letter (A–Z)</li>
                <li data-rule="lower"><i class="bi bi-circle"></i> One lowercase letter (a–z)</li>
                <li data-rule="number"><i class="bi bi-circle"></i> One number (0–9)</li>
                <li data-rule="symbol"><i class="bi bi-circle"></i> One symbol (e.g. @ # ! $)</li>
                <li data-rule="safe"><i class="bi bi-circle"></i> Not an easy-to-guess password</li>
              </ul>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label" for="{{ $p }}_confirm_password">Confirm Password</label>
            <div class="input-group">
              <input class="form-control" type="password" id="{{ $p }}_confirm_password" name="confirm_password" data-required>
              <button class="btn btn-outline-secondary" type="button" data-toggle-password="{{ $p }}_confirm_password" aria-label="Show password">
                <i class="bi bi-eye"></i>
              </button>
            </div>
          </div>
          <div class="form-check mb-3">
            <input class="form-check-input" type="checkbox" value="1" id="{{ $p }}_agree_terms" name="agree_terms" data-required data-terms @checked(old('agree_terms'))>
            <label class="form-check-label" for="{{ $p }}_agree_terms">
              I agree to the
              <a href="{{ \App\Support\AppLink::legacy('terms') }}" target="_blank" rel="noopener">Terms and Conditions</a>
              and
              <a href="{{ \App\Support\AppLink::legacy('privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>
            </label>
          </div>
          <button class="btn btn-primary w-100 register-submit-btn" type="submit" data-submit disabled>{{ $f['button'] }}</button>
        </div>
      @endforeach

      <div class="text-center mt-3 small">
        Already have an account?
        <a class="fw-semibold text-decoration-none" href="{{ $withEmbed('login') }}">Login</a>
      </div>
    </form>
  </x-auth-split-card>
@endsection

@push('scripts')
  @include('auth.partials.password-toggle')
  <script>
    // Role cards switch between the Student and Instructor panels; inactive panel fields are disabled so they are not submitted.
    (function () {
      const roleInput = document.getElementById('role');
      const roleLabel = document.getElementById('selectedRoleLabel');
      const cards = document.querySelectorAll('.role-card[data-role]');
      const panels = document.querySelectorAll('[data-role-panel]');

      function refreshSubmit(panel) {
        const terms = panel.querySelector('[data-terms]');
        panel.querySelector('[data-submit]').disabled = panel.classList.contains('d-none') || !terms.checked;
      }

      function selectRole(role) {
        roleInput.value = role;
        roleLabel.textContent = role;
        cards.forEach((card) => {
          const selected = card.dataset.role === role;
          card.classList.toggle('is-selected', selected);
          card.setAttribute('aria-checked', selected ? 'true' : 'false');
        });
        panels.forEach((panel) => {
          const active = panel.dataset.rolePanel === role;
          panel.classList.toggle('d-none', !active);
          panel.querySelectorAll('input, select').forEach((el) => {
            el.disabled = !active;
            el.required = active && el.hasAttribute('data-required');
          });
          refreshSubmit(panel);
        });
      }

      cards.forEach((card) => {
        card.addEventListener('click', () => selectRole(card.dataset.role));
        card.addEventListener('keydown', (e) => {
          if (e.key === 'Enter' || e.key === ' ') {
            e.preventDefault();
            selectRole(card.dataset.role);
          }
        });
      });
      panels.forEach((panel) => {
        panel.querySelector('[data-terms]').addEventListener('change', () => refreshSubmit(panel));
      });

      selectRole(roleInput.value || 'Student');
    })();

    // Password strength meter; mirrors App\Support\PasswordPolicy.
    (function () {
      const COMMON_PASSWORDS = @json(\App\Support\PasswordPolicy::COMMON_PASSWORDS);

      function compactPassword(value) {
        return String(value || '').toLowerCase().replace(/[^a-z0-9]/g, '');
      }

      function isEasyGuessPassword(value) {
        if (!value) return false;
        const lower = value.toLowerCase();
        const compact = compactPassword(value);

        for (const word of COMMON_PASSWORDS) {
          const wordCompact = compactPassword(word);
          if (lower === word || compact === wordCompact) return true;
          if (wordCompact.length >= 5 && compact.includes(wordCompact) && compact.length <= wordCompact.length + 4) {
            return true;
          }
        }

        if (/(.)\1{5,}/.test(value) || /^(.)\1{4,}/.test(value)) return true;

        const sequences = [
          '0123456789', '9876543210',
          'abcdefghijklmnopqrstuvwxyz',
          'zyxwvutsrqponmlkjihgfedcba',
          'qwertyuiop', 'asdfghjkl', 'zxcvbnm'
        ];
        for (const seq of sequences) {
          for (let i = 0; i <= seq.length - 5; i++) {
            if (compact.includes(seq.slice(i, i + 5))) return true;
          }
        }

        if (/^[A-Za-z]+[0-9]{1,4}[!@#$%^&*._-]?$/.test(value) && value.length <= 12) {
          const letters = value.toLowerCase().replace(/[^a-z]/g, '');
          const commonWords = ['password', 'pass', 'admin', 'user', 'login', 'welcome', 'qwerty', 'abc', 'test', 'school', 'student'];
          for (const w of commonWords) {
            if (letters === w || letters.startsWith(w)) return true;
          }
        }

        return false;
      }

      function evaluatePassword(value) {
        const policyOk = {
          length: value.length >= 8,
          upper: /[A-Z]/.test(value),
          lower: /[a-z]/.test(value),
          number: /[0-9]/.test(value),
          symbol: /[\W_]/.test(value),
        };
        const easy = value !== '' && isEasyGuessPassword(value);
        return { ...policyOk, safe: value !== '' && !easy, easy };
      }

      function bindStrengthMeter(inputId) {
        const input = document.getElementById(inputId);
        const wrap = document.querySelector('[data-pw-for="' + inputId + '"]');
        if (!input || !wrap) return;

        const fill = wrap.querySelector('.pw-strength-fill');
        const label = wrap.querySelector('.pw-strength-label');
        const rules = wrap.querySelectorAll('.pw-strength-rules [data-rule]');
        const hint = document.createElement('div');
        hint.className = 'pw-strength-hint small mt-1';
        wrap.appendChild(hint);

        function render() {
          const value = input.value || '';
          const checks = evaluatePassword(value);
          const score = ['length', 'upper', 'lower', 'number', 'symbol', 'safe'].filter((key) => checks[key]).length;

          rules.forEach((li) => {
            const ok = !!checks[li.dataset.rule];
            li.classList.toggle('is-met', ok);
            const icon = li.querySelector('i');
            if (icon) icon.className = ok ? 'bi bi-check-circle-fill' : 'bi bi-circle';
          });

          wrap.classList.remove('is-weak', 'is-fair', 'is-good', 'is-strong', 'is-empty');
          if (value === '') {
            wrap.classList.add('is-empty');
            label.textContent = '—';
            fill.style.width = '0%';
            hint.textContent = '';
            return;
          }

          // Even if all character rules pass, an easy-to-guess password is Weak.
          let level = 'strong', text = 'Strong', width = '100%';
          if (checks.easy || score <= 2) {
            level = 'weak'; text = checks.easy ? 'Weak (easy to guess)' : 'Weak'; width = '25%';
          } else if (score <= 4) {
            level = 'fair'; text = 'Fair'; width = '50%';
          } else if (score === 5) {
            level = 'good'; text = 'Good'; width = '75%';
          }

          wrap.classList.add('is-' + level);
          label.textContent = text;
          fill.style.width = width;
          hint.textContent = checks.easy
            ? 'This password is common or easy to guess. Try a longer, less predictable mix.'
            : '';
        }

        input.addEventListener('input', render);
        input.addEventListener('change', render);
        render();
      }

      bindStrengthMeter('student_password');
      bindStrengthMeter('instructor_password');
    })();
  </script>
@endpush
