function jsUcfirst(string)
{
	return string.charAt(0).toUpperCase() + string.slice(1);
}
function changeLanguage(language) {
	document.getElementById('payretoPaymentNameEn').style.display = 'none';
	document.getElementById('payretoPaymentNameDe').style.display = 'none';
	document.getElementById('payretoPaymentName'+jsUcfirst(language)).style.display = 'block';
}