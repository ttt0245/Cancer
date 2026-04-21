const loginForm = document.getElementById('loginForm');
const loginError = document.getElementById('loginError');
const registerForm = document.getElementById('registerForm');
const registerError = document.getElementById('registerError');
const registerSuccess = document.getElementById('registerSuccess');
const authTitle = document.getElementById('authTitle');
const showLoginTab = document.getElementById('showLoginTab');
const showRegisterTab = document.getElementById('showRegisterTab');
const registerPasswordInput = document.getElementById('registerPassword');
const registerStrength = document.getElementById('registerStrength');
const registerStrengthBar = document.getElementById('registerStrengthBar');
const registerStrengthFill = document.getElementById('registerStrengthFill');
const registerStrengthText = document.getElementById('registerStrengthText');

const strengthLabels = ['未入力', 'とても弱い', '弱い', '普通', '強い', 'とても強い'];;

const updatePasswordStrength = (password) => {
  let score = 0;
  //ss

  if (!password) {
    score = 0;
  } else {
    score = 1;

    if (password.length >= 8) {
      score += 1;
    }
    if (password.length >= 10) {
      score += 1;
    }
    if (/[0-9]/.test(password) && /[A-Za-z]/.test(password)) {
      score += 1;
    }
    if (/[^A-Za-z0-9]/.test(password) || (/[a-z]/.test(password) && /[A-Z]/.test(password))) {
      score += 1;
    }
  }

  const clampedScore = Math.max(0, Math.min(5, score));
  const percent = clampedScore * 20;
  registerStrength.dataset.level = String(clampedScore);
  registerStrengthFill.style.width = `${percent}%`;
  registerStrengthBar.setAttribute('aria-valuenow', String(percent));
  registerStrengthText.textContent = `強度: ${strengthLabels[clampedScore]}`;
};

const setupPasswordToggles = () => {
  const toggleButtons = document.querySelectorAll('.password-toggle');

  toggleButtons.forEach((button) => {
    const targetId = button.getAttribute('data-target');
    const targetInput = document.getElementById(targetId);

    if (!targetInput) {
      return;
    }

    button.addEventListener('click', () => {
      const isPassword = targetInput.type === 'password';
      targetInput.type = isPassword ? 'text' : 'password';
      button.classList.toggle('is-visible', isPassword);
      button.setAttribute('aria-label', isPassword ? 'パスワードを非表示' : 'パスワードを表示');
    });
  });
};

const switchToLogin = () => {
  loginForm.classList.remove('hidden');
  registerForm.classList.add('hidden');
  showLoginTab.classList.add('active');
  showRegisterTab.classList.remove('active');
  showLoginTab.setAttribute('aria-selected', 'true');
  showRegisterTab.setAttribute('aria-selected', 'false');
  authTitle.textContent = 'ログイン';
  registerError.textContent = '';
  registerSuccess.textContent = '';
};

const switchToRegister = () => {
  registerForm.classList.remove('hidden');
  loginForm.classList.add('hidden');
  showRegisterTab.classList.add('active');
  showLoginTab.classList.remove('active');
  showRegisterTab.setAttribute('aria-selected', 'true');
  showLoginTab.setAttribute('aria-selected', 'false');
  authTitle.textContent = '新規登録';
  loginError.textContent = '';
};

const validateRegister = () => {
  const username = document.getElementById('registerUsername').value.trim();
  const password = document.getElementById('registerPassword').value.trim();
  const passwordConfirm = document.getElementById('registerPasswordConfirm').value.trim();

  if (!username || !password || !passwordConfirm) {
    registerError.textContent = 'すべての項目を入力してください。';
    return false;
  }

  if (username.length < 3) {
    registerError.textContent = 'ユーザー名は3文字以上で入力してください。';
    return false;
  }

  if (password.length < 6) {
    registerError.textContent = 'パスワードは6文字以上で入力してください。';
    return false;
  }

  if (password !== passwordConfirm) {
    registerError.textContent = 'パスワード確認が一致しません。';
    return false;
  }

  registerError.textContent = '';
  return { username, password };
};

const postJson = async (url, payload) => {
  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
    },
    body: JSON.stringify(payload),
  });

  let data = null;
  try {
    data = await response.json();
  } catch (error) {
    throw new Error('サーバー応答の形式が不正です。');
  }

  if (!response.ok) {
    throw new Error(data.message || 'サーバーエラーが発生しました。');
  }

  return data;
};

setupPasswordToggles();
updatePasswordStrength(registerPasswordInput.value);

registerPasswordInput.addEventListener('input', () => {
  updatePasswordStrength(registerPasswordInput.value);
});

const validateLogin = () => {
  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value.trim();

  if (!username || !password) {
    loginError.textContent = 'ユーザー名とパスワードを両方入力してください。';
    return false;
  }

  loginError.textContent = '';
  return true;
};

loginForm.addEventListener('submit', async (event) => {
  event.preventDefault();

  if (!validateLogin()) {
    return;
  }

  const username = document.getElementById('username').value.trim();
  const password = document.getElementById('password').value.trim();

  try {
    const result = await postJson('api/login.php', { username, password });
    if (result.success) {
      sessionStorage.setItem('currentUser', result.user.username);
      window.location.href = 'gacha.html';
      return;
    }

    loginError.textContent = result.message || 'ログインに失敗しました。';
  } catch (error) {
    loginError.textContent = error.message;
  }
});

showLoginTab.addEventListener('click', switchToLogin);
showRegisterTab.addEventListener('click', switchToRegister);

registerForm.addEventListener('submit', async (event) => {
  event.preventDefault();
  registerSuccess.textContent = '';

  const registerData = validateRegister();
  if (!registerData) {
    return;
  }

  try {
    const result = await postJson('api/register.php', registerData);

    if (result.success) {
      registerForm.reset();
      updatePasswordStrength('');
      registerSuccess.textContent = '登録が完了しました。ログインしてください。';

      const loginUsername = document.getElementById('username');
      loginUsername.value = result.user.username;

      window.setTimeout(() => {
        switchToLogin();
      }, 900);
      return;
    }

    registerError.textContent = result.message || '登録に失敗しました。';
  } catch (error) {
    registerError.textContent = `登録に失敗しました: ${error.message}`;
  }
});
