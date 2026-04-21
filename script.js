const gachaMessage = document.getElementById('gachaMessage');
const handle = document.getElementById('handle');

const ensureAuthenticated = async () => {
  try {
    const response = await fetch('api/me.php', { method: 'GET' });
    if (!response.ok) {
      window.location.href = 'index.html';
      return false;
    }

    const data = await response.json();
    if (!data.success) {
      window.location.href = 'index.html';
      return false;
    }

    return true;
  } catch (error) {
    window.location.href = 'index.html';
    return false;
  }
};

const initGacha = async () => {
  const isAuthenticated = await ensureAuthenticated();
  if (!isAuthenticated || !handle) {
    return;
  }

  let isPointerDown = false;
  let lastAngle = 0;
  let currentRotation = 0;
  let lastSpinTime = 0;

  const getAngle = (event) => {
    const rect = handle.getBoundingClientRect();
    const centerX = rect.left + rect.width / 2;
    const centerY = rect.top + rect.height / 2;
    const x = event.clientX - centerX;
    const y = event.clientY - centerY;
    return Math.atan2(y, x);
  };

  const finishSpin = () => {
    const now = Date.now();
    if (now - lastSpinTime < 500) return;
    lastSpinTime = now;
    if (gachaMessage) {
      gachaMessage.textContent = 'ガチャを回しました！結果を確認しましょう。';
    }
  };

  handle.addEventListener('pointerdown', (event) => {
    event.preventDefault();
    isPointerDown = true;
    handle.setPointerCapture(event.pointerId);
    lastAngle = getAngle(event);
  });

let spinAmount = 0; // 回した量を記録

  handle.addEventListener('pointermove', (event) => {
  if (!isPointerDown) return;

  const angle = getAngle(event);
  let delta = angle - lastAngle;

  // 角度補正
  if (delta > Math.PI) delta -= 2 * Math.PI;
  if (delta < -Math.PI) delta += 2 * Math.PI;

  // ❗ 左回り（マイナス）は無視
  if (delta < 0) delta = 0;

  currentRotation += delta;
  spinAmount += delta; // 回転量を加算
  lastAngle = angle;

  handle.style.transform = `rotate(${currentRotation}rad)`;
  });

  handle.addEventListener('pointerup', () => {
  if (isPointerDown) {
    // 半回転以上でガチャ発動（πラジアン）
    if (spinAmount >= Math.PI) {
      finishSpin();
    } else {
      if (gachaMessage) {
        gachaMessage.textContent = 'もっと回してください！';
      }
    }
  }

  isPointerDown = false;
  spinAmount = 0; // リセット
  });
};

initGacha();

