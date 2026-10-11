const statusEl = document.getElementById('status');

fetch('api.php')
  .then((res) => res.json())
  .then((data) => {
    statusEl.textContent = `Бэкенд: ${data.status} (PHP ${data.php})`;
    statusEl.className = 'ok';
  })
  .catch(() => {
    statusEl.textContent = 'Бэкенд недоступен';
    statusEl.className = 'fail';
  });