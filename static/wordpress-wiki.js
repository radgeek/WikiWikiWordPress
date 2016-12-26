//function toggle_hide_show() {
//	if (jQuery("#content_list").css('display')!='none') {
//		jQuery('#content_list').hide();
//		jQuery('#hide_show').html('show');
//	} else {
//		jQuery('#content_list').show();
//		jQuery('#hide_show').html('hide');
//	}
//}

function toggle_hide_show(el) {
    if (jQuery(el).hasClass("show")) {
		jQuery(el).removeClass("show").addClass("hide").html('Show').parent().next(".content_list").hide();
    } else {
		jQuery(el).removeClass("hide").addClass("show").html('Hide').parent().next(".content_list").show();
    }
}

function check_toc() {
    if (jQuery("#wiki_page").is(":checked")) {
        jQuery("#wiki_toc").removeAttr("disabled").val(["1"]);
    } else {
        jQuery("#wiki_toc").attr("checked", false).attr("disabled", true);
    }
}

function getParameterByName(name, url) {
    if (!url) {
      url = window.location.href;
    }
    name = name.replace(/[\[\]]/g, "\\$&");
    var regex = new RegExp("[?&]" + name + "(=([^&#]*)|&|#|$)"),
        results = regex.exec(url);
    if (!results) return null;
    if (!results[2]) return '';
    return decodeURIComponent(results[2].replace(/\+/g, " "));
}