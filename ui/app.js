$(document).ready(function () {
	var skrillUrl = new URL(document.URL);
	var skrillSettingType = skrillUrl.searchParams.get('action');

	var url = '/payreto/settings/'+skrillSettingType+'/';
	$.ajax({
		url : url
	}).done(function (r){
		$('payreto-ui').html(r);
		saveSettings();
	});

	function saveSettings(){
		$('#saveSettings').submit(function(e) {
			e.preventDefault();
			var saveUrl = '/payreto/settings/save';
			$.ajax({
				url : saveUrl,
				type: 'post',
				data: $(this).serialize()
			}).done(function(r){
				if (r == 'success') {
					$('#successMessage').css('display', 'block');
				} else {
					$('#errorMessage').css('display', 'block');
				}
			});
		});
	}
})