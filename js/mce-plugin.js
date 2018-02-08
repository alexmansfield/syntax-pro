/* global tinymce */
( function() {
	tinymce.PluginManager.add( 'mdlr_syntax_plugin', function( editor ) {

		// Adds the Syntax button to the TinyMCE editor.
		editor.addButton( 'mdlr_syntax', {
			text: 'Syntax',
			icon: 'dashicon dashicons-editor-code',
			onclick: function() {
				wp.mce.syntax.codeEditor(editor, 'new');
			}
		});

		// everything below this is modified from the
		// image edit toolbar setup in WP core.

		var toolbar;

		// Checks if the current node is just a placeholder.
		function isPlaceholder( node ) {
			return !! ( editor.dom.getAttrib( node, 'data-mce-placeholder' ) || editor.dom.getAttrib( node, 'data-mce-object' ) );
		}


		// Adds the Edit button to the Syntax toolbar.
		editor.addButton( 'syntax_edit', {
			tooltip: 'Edit ', // trailing space is needed, used for context
			icon: 'dashicon dashicons-edit',
			onclick: function() {
				wp.mce.syntax.codeEditor(editor);
			}
		} );

		// Adds the Remove button to the Syntax toolbar.
		editor.addButton( 'syntax_remove', {
			tooltip: 'Remove',
			icon: 'dashicon dashicons-no',
			onclick: function() {
				wp.mce.syntax.codeRemove(editor);
			}
		} );

		// Creates toolbar buttons for floating Syntax toolbar.
		editor.once( 'preinit', function() {
			if ( editor.wp && editor.wp._createToolbar ) {
				toolbar = editor.wp._createToolbar( [
					'syntax_edit',
					'syntax_remove'
				] );
			}
		} );

		// Opens the Syntax toolbar when a Syntax block receives focus.
		editor.on( 'wptoolbar', function( event ) {
			if ( event.element.nodeName === 'PRE' && ! isPlaceholder( event.element ) ) {
				classArray = event.element.className.split(' ');

				// Does this element have the .mdlr-syntax class?
				if ( -1 !== jQuery.inArray( 'mdlr-syntax', classArray ) ) {
					if ( jQuery(event.element).attr("data-mce-selected") ) {
						event.toolbar = toolbar;
					}
				}
			}
		} );
	});
})();
