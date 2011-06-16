// Stuff that happens on the plugin option page

jQuery(document).ready(function($){

	$('#div_h3_twitter, #div_h3_wordpress, #div_h3_buddypress').css('display','none');
	
	$('#advanced_template').css('display','none');
	$('#toggle_advanced_template')
		.css('cursor','pointer')
		.append(' (click to view)')
		.click(function(){$('#advanced_template').toggle(500);});
	
	// Toggle setting sections
	$('.h3_toggle').click(function(){
		var target = 'div_'+$(this).attr('id');
		if( $('#'+target).css('display') == 'none' ) {
			$('.div_h3').slideUp();
			$('.h3_toggle').removeClass('expanded').addClass('folded');
			$('#'+target).slideDown();
			$(this).removeClass('folded').addClass('expanded');
		} else {
			$('.h3_toggle').removeClass('expanded').addClass('folded');
			$(this).removeClass('folded').addClass('expanded');
		}
	});
		
	// stuff for the divs that have to toggle with their select element.
    $('.y_toggle').each(function(){
		$(this).change(function(){
			var source = $(this).attr('id');
			if ( $(this).attr('type') == 'checkbox' ) {
				if ($(this).attr('checked') == true) {
					$('.'+source).fadeIn(100);
				} else {
					$('.'+source).fadeOut(100).find(':checkbox').attr('checked',false);
				}
			} else {
				var target = $(this).val();
				$('.'+source).hide();
				$('#y_show_'+target).fadeIn(300);
			}
		});
	})	
	
	// Twitter sample copy
	$('.tw_msg_sample').click(function(){
		$('#tw_msg').val($(this).html());
	});
	
	// Sanitize Windows paths
	$('#y_path').keypress(function(){
		$(this).val( $.trim( $(this).val().replace(/\\/g, '/') ) );
	});
	
	// Reset button
	$('#reset-yourls,#unlink-yourls').click(function(){
		return confirm('Really do?');
	})
});

/* Ajax Requests on the plugin admin page */
(function($){
	var yourls = {
		// Check location
		check: function( type ) {
			var post = {};
			if( type == 'path' ) {
				post['location'] = $('#y_path').val();
			} else {
				post['location'] = $('#y_url').val();
				post['username'] = $('#y_yourls_login').val();
				post['password'] = $('#y_yourls_passwd').val();
			}
			post['action'] = 'yourls-check';
			post['_ajax_nonce'] = $('#_ajax_yourls').val();
			post['yourls_type'] = type;

			$('#check_'+type).html('Checking...');
			
			$.ajax({
				url : ajaxurl,
				data : post,
				success : function(x) { yourls.check_ok(x, '#check_'+type); },
				error : function(r) { yourls.check_notok(r, '#check_'+type); }
			});
		},
		
		// Check: success
		check_ok : function(x, div) {
			if ( typeof(x) == 'string' ) {
				this.error({'responseText': x}, div);
				return;
			}

			var r = wpAjax.parseAjaxResponse(x);
			if ( r.errors )
				this.error({'responseText': wpAjax.broken}, div);

			r = r.responses[0];
			$(div).html(r.data);
		},

		// Check: failure
		check_notok : function(r, div) {
			var er = r.statusText;
			if ( r.responseText )
				er = r.responseText.replace( /<.[^<>]*?>/g, '' );
			if ( er )
				$(div).html('Error during Ajax request: '+er);
		}
	};
	
	$(document).ready(function(){
		// Check path & URLs
		$('.yourls_check').click(function(){
			var type = $(this).attr('id').replace(/check_/, '');
			yourls.check( type );
		});
	})
})(jQuery);


function toggle_not_ok( el ) {
	jQuery( el ).removeClass( 'ok' ).addClass( 'notok' );
}

function toggle_ok( el ) {
	jQuery( el ).removeClass( 'notok' ).addClass( 'ok' );
}

function toggle_ok_notok( el, status ) {
	if( status == 'ok' ) {
		toggle_ok( el );
	} else {
		toggle_not_ok( el );
	}
}
