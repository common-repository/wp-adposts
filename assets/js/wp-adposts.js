/*!
 * WP-AdPots Plugin (https://ianhub.net/products/wordpress/wp-adposts)
 * Copyright 2018 Kader Bouyakoub (Ianhub - https://github.com/bkader)
 * Licensed under GPLv3 or later (https://opensource.org/licenses/GPL-3.0)
 */
(function ($) {
	// Use to avoid conflict with other libraries.
	jQuery.noConflict();
	"use strict";

	// Use only after DOM is fully ready.
	jQuery(document).ready(function () {
		jQuery(".wp-adpost-link").on("click", function (e) {
			// We prevent default action.
			e.preventDefault();

			// Hold both URL and the Ad ID.
			var that = jQuery(this),
				href = that.attr("href"),
				id = that.attr("data-ad");

			// We prepare the data to be sent.
			var data = {action: "ad_click", ad_id: id};

			// Submit request.
			jQuery.post(AdPosts.ajaxUrl, data, function (response) {
				// There was an error? Stop.
				if ( response !== "success" ) {
					return false;
				}

				// Continue.
				if (href.length) {
					window.open(href, "_blank");
				}
			});
		});
	});
})(jQuery);
