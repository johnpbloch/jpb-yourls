// Stuff that happens on the plugin option page

jQuery(document).ready(function($){

	$('#div_h3_twitter, #div_h3_wordpress').css('display','none');
	
	$('#advanced_template').css('display','none');
	$('#toggle_advanced_template')
		.css('cursor','pointer')
		.append(' (click to view)')
		.click(function(){$('#advanced_template').toggle(500);});
	
	
	// Check for Consumer key & secret info
	$('#consumer_key').change(function(){ check_keys(); });
	$('#consumer_secret').change(function(){ check_keys(); });
	$('#consumer_secret').click(function(){ check_keys(); });
	var yoursl_check_timer = 0;

	function check_keys() {
		if( !$('#consumer_key').val() || !$('#consumer_secret').val() ) {
			return;
		}
		if( yoursl_check_timer ) {
			clearTimeout ( yoursl_check_timer );
		}
		yoursl_check_timer = setTimeout ( "update_key_msg()", 1000 );
	}
	
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
	
	// Password reveal: create the checkboxes
	$('input:password').each(function(){
		var target = $(this).attr('id');
		$(this).after('&nbsp;<label><input type="checkbox" class="y_reveal" id="y_reveal_'+target+'> Show letters</label>');
		$('#y_reveal_'+target).data('target', target)
	});
	
	// Password reveal: checkboxes behavior
	$('.y_reveal').change(function(){
		var target = $(this).data('target');
		password_toggle(target, $(this).attr('checked'));
		return;
	});
	
	// Twitter sample copy
	$('.tw_msg_sample').click(function(){
		$('#tw_msg').val($(this).html());
	});
	
	// Toggle display between password and text fields
	function password_toggle(target, display) {
		if (display) {
			var pw = $('#'+target).val();
			$('#'+target).hide().after('<input type="text" name="'+target+'_text__" value="'+pw+'" id="'+target+'_text__"/>');
		} else {
			var pw = $('#'+target+'_text__').val();
			$('#'+target).show().val(pw);
			$('#'+target+'_text__').remove();
		}
		// No, you can't change $('#tw_passwd').attr('type') on the fly, in case you're wondering
	}
	
	// Reset all password fields (make them passwords, not texts)
	function password_hide_all() {
		$('input:password').each(function(){
			password_toggle( $(this).attr('id'), false );
		});
	}
	
	// On form submit, first reset all pwd fields
	$('.y_submit').click(function(){
		password_hide_all();
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

function update_key_msg() {
	jQuery('#yourls_now_connect').text( 'Now press "Save Changes". If these information look correct, here will be a "Sign in with Twitter" button' );
}