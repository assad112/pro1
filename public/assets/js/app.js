document.addEventListener('submit', function (event) {
  const form = event.target;
  if (!(form instanceof HTMLFormElement)) {
    return;
  }
  const button = form.querySelector('button[type="submit"]');
  if (button) {
    button.disabled = true;
    setTimeout(function () {
      button.disabled = false;
    }, 1500);
  }
});
