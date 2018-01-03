function changeLanguage(language) {
	document.getElementById('payretoPaymentNameEn').style.display = 'none';
	document.getElementById('payretoPaymentNameDe').style.display = 'none';
	document.getElementById('payretoPaymentName'+jsUcfirst(language)).style.display = 'block';
}