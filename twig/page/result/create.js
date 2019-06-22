/**
 * Companion script for the create.html twig script.
 *
 * Responsibilities:
 * 		- Disable read-only form fields
 * 		- Disable action buttons when the user clicks them the first time
 * 		- Set initial/empty values on display cards
 *
 */
var disableFormsOnSectionStatus = function(event) {

	// var $row = $(this).parents('.row');
	var $row = $(this).parents('[id^=metric-wrap-]');
	console.info($row);
	var status = $(this).val();

	if ('-' === status) {
		var find = $row.find('input, select:not(.section_status)')
		find.each(function(index, inputEl) {
				$(inputEl).prop('disabled', true);
			});
		} else {
		var find = $row.find('input, select:not(.section_status)')
		find.each(function(index, inputEl) {
				$(inputEl).prop('disabled', false);
			});
	}
};

// Enable read-only form fields
// $("input, select").each(function() {
// 	$(this).attr("readonly", false);
// 	$(this).attr("disabled", false);
// });

// // Disable the action button after user clicks
// $(".form-actions .btn").on('click', function(event) {
// 	$(this).attr('disable', 'disable');
// });

// Set initial values on potency result cards (cannabinoid profile)
$(".potency-result-cards [class^='potency-result-']").each(function(index, spanElm) {
	$(spanElm).text('-.--%');
});


$(".section_status").on('change', disableFormsOnSectionStatus);
// $(".section_status").each(function(index, sectionStatusEl) {
// 	disableFormsOnSectionStatus.bind($(sectionStatusEl)) ();
// });

