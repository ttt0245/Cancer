const circle = document.getElementById("circle");
const log = document.getElementById("log");

let isDrawing = false;
let lastAngle = 0;
let totalRotation = 0;
let count = 0;

circle.addEventListener("pointerdown", (e) => {
  isDrawing = true;
  totalRotation = 0;

  const rect = circle.getBoundingClientRect();
  const x = e.clientX - rect.left - rect.width / 2;
  const y = e.clientY - rect.top - rect.height / 2;
  lastAngle = Math.atan2(y, x);
});

circle.addEventListener("pointerup", () => {
  isDrawing = false;
});

circle.addEventListener("pointermove", (e) => {
  if (!isDrawing) return;

  const rect = circle.getBoundingClientRect();
  const x = e.clientX - rect.left - rect.width / 2;
  const y = e.clientY - rect.top - rect.height / 2;

  const angle = Math.atan2(y, x);

  let diff = angle - lastAngle;

  if (diff > Math.PI) diff -= 2 * Math.PI;
  if (diff < -Math.PI) diff += 2 * Math.PI;

  totalRotation += diff;
  lastAngle = angle;

  if (Math.abs(totalRotation) >= 2 * Math.PI) {
    count++;
    log.textContent = "回転回数：" + count;
    totalRotation = 0;

    console.log("1回ガチャ！");
    // ここに後でESP32通信を追加
  }
});