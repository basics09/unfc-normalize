/**
 * Javascript for UNFC Nörmalize WP plugin.
 */
/*jslint ass: true, nomen: true, plusplus: true, regexp: true, vars: true, white: true, indent: 4 */
/*global jQuery, wp, commonL10n, unfc_params, console */
/*exported unfc_normalize */

var unfc_normalize = unfc_normalize || {}; // Our namespace.

( function ( $ ) {
	'use strict';

	/**
	 * Helper to normalize text pasted into text-like inputs and textareas.
	 */
	unfc_normalize.input_textarea_normalize_on_paste = function ( context ) {
		// TODO: Other types: "email", "password", "url" ?? More exclusions ??
		$( 'input[type="text"], input[type="search"], textarea', context ).not( '#current-page-selector' ).on( 'paste', function ( event ) {
			var $el = $( this );
			if ( $el.val().normalize ) {
				// http://stackoverflow.com/a/1503425/664741
				setTimeout( function () {
					var before = $el.val(), after = before.normalize(), selection;
					if ( before !== after ) {
						if ( ! ( unfc_params && unfc_params.is.dont_paste ) ) {
							selection = $el.getSelection();
							$el.val( after );
							$el.setSelection( selection.start + ( after.length - before.length ), selection.end + ( after.length - before.length ) );
						}
						$el.change(); // Trigger change.
					}
					if ( unfc_params && unfc_params.is.script_debug ) {
						unfc_normalize.dmp_before_and_after( before, after );
					}
				} );
			}
		} );
	};

	/**
	 * Normalize text pasted into tinymce.
	 */
	unfc_normalize.tinymce_editor_init = function () {
		$( document ).on( 'tinymce-editor-init', function ( event, editor ) {
			// Using PastePreProcess, which is fired with the paste as a HTML string set in event.content.
			// Easy option, may not be the best.
			editor.on( 'PastePreProcess', function( event ) {
				var before; // Keep jshint happy.
				if ( event.content && event.content.length && event.content.normalize ) {
					if ( unfc_params && unfc_params.is.script_debug ) {
						before = event.content;
					}
					event.content = event.content.normalize();
					if ( unfc_params && unfc_params.is.script_debug ) {
						unfc_normalize.dmp_before_and_after( before, event.content );
					}
				}
			} );
		} );
	};

	/**
	 * Call in admin on jQuery ready.
	 * Standard admin inputs and wp.media stuff.
	 */
	unfc_normalize.admin_ready = function () {

		var $wpcontent = $( '#wpcontent' ), old_details_render, old_display_render;

		// Any standard admin text input or textarea. May need refining.
		if ( $wpcontent.length ) {
			unfc_normalize.input_textarea_normalize_on_paste( $wpcontent );
		}

		// Media.
		if ( 'undefined' !== typeof wp && wp.media && wp.media.view ) {
			if ( wp.media.view.Attachment && wp.media.view.Attachment.Details ) {
				// Override render. Probably not the best option.
				old_details_render = wp.media.view.Attachment.Details.prototype.render;
				wp.media.view.Attachment.Details.prototype.render = function () {
					old_details_render.apply( this, arguments );
					unfc_normalize.input_textarea_normalize_on_paste( this.$el );
				};
			}
			if ( wp.media.view.Settings && wp.media.view.Settings.AttachmentDisplay ) {
				// Override render. Again, probably not the best option.
				old_display_render = wp.media.view.Settings.AttachmentDisplay.prototype.render;
				wp.media.view.Settings.AttachmentDisplay.prototype.render = function () {
					old_display_render.apply( this, arguments );
					unfc_normalize.input_textarea_normalize_on_paste( this.$el );
				};
			}
			// TODO: Other media stuff.
		}
		// TODO: Other stuff.

		if ( unfc_params.is.db_check_loaded ) {
			unfc_normalize.db_check();
		}
	};

	/**
	 * Call in front end on jQuery ready.
	 * Standard inputs.
	 */
	unfc_normalize.front_end_ready = function () {

		// Any standard text input or textarea. May need refining.
		unfc_normalize.input_textarea_normalize_on_paste();

	};

	/**
	 * Call in admin.
	 * Customizer stuff.
	 */
	unfc_normalize.customizer_ready = function () {
		// Customizer - do outside jQuery ready otherwise will miss 'ready' event.
		if ( 'undefined' !== typeof wp && wp.customize ) {
			wp.customize.bind( 'ready', function () {
				unfc_normalize.input_textarea_normalize_on_paste();
			} );
		}
	};

	/**
	 * Debug helper - dump before and after.
	 */
	unfc_normalize.dmp_before_and_after = function ( before, after ) {
		if ( 'undefined' !== typeof console && console.log ) {
			var i, before_dmp = '', after_dmp = '';
			if ( before === after ) {
				console.log( 'normalize same' );
			} else {
				for ( i = 0; i < before.length; i++ ) {
					before_dmp += ( '0000' + before.charCodeAt( i ).toString( 16 ) ).slice( -4 ) + ' ';
				}
				for ( i = 0; i < after.length; i++ ) {
					after_dmp += ( '0000' + after.charCodeAt( i ).toString( 16 ) ).slice( -4 ) + ' ';
				}
				console.log( 'normalize different\nbefore_dmp=%s\n after_dmp=%s', before_dmp, after_dmp );
			}
		}
	};

	/**
	 * Some UI feedback for database check tool.
	 */
	unfc_normalize.db_check = function () {
		var $db_check = $( '#unfc_db_check' ), $msgs = $( '.notice, .updated', $db_check ), $forms = $( '.unfc_db_check_form', $db_check );
		if ( $db_check.length ) {
			$( 'input[type="submit"]', $forms ).click( function ( e ) {
				var $this = $( this ), $form = $this.parent(), $msg = $( unfc_params.please_wait_msg );
				$this.hide();
				$forms.not( $form ).hide();
				$( '.unfc_db_check_form_hide', $db_check ).hide();
				$( '#screen-meta-links' ).hide();
				$msgs.hide();
				$( 'h1', $db_check ).first().after( $msg );
				unfc_normalize.makeNoticesDismissible( $db_check );
			} );
		}
	};

	/**
	 * Some stuff for database check tool listings.
	 */
	unfc_normalize.db_check_list = function ( sel ) {
		var $db_check = $( '#unfc_db_check' ), $db_check_list = $( sel, $db_check ), $form;
		if ( $db_check_list.length ) {
			$form = $( 'form.unfc_db_check_list_form', $db_check_list );
			if ( $form.length ) {
				// Remove the "wp-admin/common.js" #17685 submit handler which doesn't apply here & is buggy anyway.
				$form.off( 'submit' );
				// Save some round-tripping to the server for nowt.
				$( '#doaction, #doaction2', $db_check_list ).click( function ( e ) {
					var $bulk_action = $( '#bulk-action-selector-' + ( 'doaction' === this.id ? 'top' : 'bottom' ) + ' option:selected', $db_check_list ),
						action = $bulk_action.val(), $current_page = $( 'input#current-page-selector', $db_check_list ), $msg, $msgs, checkeds;
					if ( '-1' !== action ) {
						checkeds = $.makeArray( $( 'input[name="item[]"]:checked', $db_check_list ).map( function () {
							return this.value;
						} ) );
						if ( ! checkeds.length ) {
							e.preventDefault();
							$msgs = $( '.notice', $db_check );
							if ( $msgs.length ) {
								$msgs.remove(); 
							}
							$msg = $( unfc_params.no_items_selected_msg );
							$( 'h1', $db_check).first().after( $msg );
							unfc_normalize.makeNoticesDismissible( $db_check );
						}
					}
				} );
			}
		}
	};

	/**
	 * Make notices dismissible. Taken (more or less) from WP 4.5.2 "wp-admin/js/common.js".
	 */
	unfc_normalize.makeNoticesDismissible = function ( $db_check ) {
		$( '.notice.is-dismissible', $db_check ).each( function() {
			var $el = $( this ),
				$button = $( '<button type="button" class="notice-dismiss"><span class="screen-reader-text"></span></button>' ),
				btnText = commonL10n.dismiss || '';

			// Ensure plain text
			$button.find( '.screen-reader-text' ).text( btnText );
			$button.off( 'click.wp-dismiss-notice' ).on( 'click.wp-dismiss-notice', function( event ) {
				event.preventDefault();
				$el.fadeTo( 100, 0, function() {
					$el.slideUp( 100, function() {
						$el.remove();
					});
				});
			});

			$el.append( $button );
		});
	};

} )( jQuery );
