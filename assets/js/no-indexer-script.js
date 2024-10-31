jQuery(document).ready(function () {
  jQuery("#noindex_page_checkbox").click(function () {
    if (jQuery(this).is(":checked")) {
      jQuery(this).css("background-color", "#3858e9");
    } else {
      jQuery(this).css("background-color", "#fff");
    }
  });
});
