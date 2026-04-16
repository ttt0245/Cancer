const handle = document.getElementById('handle');

let isPointerDown = false;
let lastAngle = 0;
let currentRotation = 0;

const getAngle = (event) => {
  const rect = handle.getBoundingClientRect();
  const centerX = rect.left + rect.width / 2;
  const centerY = rect.top + rect.height / 2;
  const x = event.clientX - centerX;
  const y = event.clientY - centerY;
  return Math.atan2(y, x);
};

handle.addEventListener('pointerdown', (event) => {
  event.preventDefault();
  isPointerDown = true;
  handle.setPointerCapture(event.pointerId);
  lastAngle = getAngle(event);
});

handle.addEventListener('pointermove', (event) => {
  if (!isPointerDown) return;
  const angle = getAngle(event);
  let delta = angle - lastAngle;

  if (delta > Math.PI) delta -= 2 * Math.PI;
  if (delta < -Math.PI) delta += 2 * Math.PI;

  currentRotation += delta;
  lastAngle = angle;
  handle.style.transform = `rotate(${currentRotation}rad)`;
});

handle.addEventListener('pointerup', () => {
  isPointerDown = false;
});

handle.addEventListener('pointercancel', () => {
  isPointerDown = false;
});
