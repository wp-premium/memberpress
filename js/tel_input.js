var telInputs = document.querySelectorAll(".mepr-tel-input");
for (var i = 0; i < telInputs.length; i++) {
  var iti = window.intlTelInput(telInputs[i], {
    separateDialCode: true,
    initialCountry: meprTel.defaultCountry,
    utilsScript: meprTel.utilsUrl,
  });
}