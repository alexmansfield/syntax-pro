(function($){
	wp.mce = wp.mce || {};
	wp.mce.syntax = {
		codeEditor: function(editor, values, onsubmit_callback){
			currentNode = editor.selection.getNode();
			values = values || [];
			currentID = $(currentNode).attr("data-syntax-id");
			languages = JSON.parse(syntax.languages);


			// Is there an ID for the current element?
			if (currentID) {
				allValues = JSON.parse($('#syntax-storage').val());

				values.id = $(currentNode).attr("data-syntax-id");
				values.title = allValues[values.id].title;
				values.language = allValues[values.id].language;
				values.code = allValues[values.id].code;
			}

			// Are there any languages enabled?
			if (languages) {
				languageSelect = {
					type: 'listbox',
					name: 'language',
					label: syntax.languageText,
					values : languages,
					value: values.language
				};
			} else {
				languageSelect = '';
			}

			if(typeof onsubmit_callback !== 'function'){
				onsubmit_callback = function( e ) {
					savedCharacters = $('#syntax-storage').val();

					// Are we editing an existing code block?
					if (currentID) {
						if (savedCharacters) {
							characters = JSON.parse(savedCharacters);
						}
						characters[currentID] = e.data;
						charactersString = JSON.stringify(characters);

					} else {
						if (savedCharacters) {
							characters = JSON.parse(savedCharacters);

							// This only works on IE9 and above.
							characterKeys = Object.keys(characters);

							// Get highest current key and create new unique key.
							characterKeys.sort(syntaxSort);
							lastKey = parseInt(characterKeys[characterKeys.length - 1]);
							newKey = lastKey + 1;
						} else {
							characters = {};
							newKey = 1;
						}

						characters[newKey] = e.data;
						charactersString = JSON.stringify(characters);

						currentID = newKey;
					}

					// Adds the current code block to the Syntax meta field.
					writeMeta(characters);

					// Generates output for the TinyMCE editor.
					output = '';
					// output += '<div class="wpview-wrap mdlr-syntax" data-syntax-id="' + currentID + '" contenteditable="false">';
					output += '<pre class="wpview-wrap mdlr-syntax language-' + e.data.language + '" data-syntax-id="' + currentID + '" contenteditable="false"><code class="language-' + e.data.language + '">';
					output += htmlEntities(e.data.code);
					output += '</code></pre>';
					// output += '</div>';

					editor.insertContent( output );
					editor.nodeChanged();
				};
			}

			editor.windowManager.open( {
				title: syntax.addCodeText,
				// width: 700,
				// height: 300,
				// minWidth: 1160,
				// minHeight: 160,
				classes: 'mdlr-syntax-modal',
				// style: {
				// 	width: '100%'
				// },

				body: [
					languageSelect,
					{
						type: 'textbox',
						name: 'code',
						label: syntax.codeText,
						multiline: true,
						value: values.code
						// value: $('#syntax-storage').val()
					}
				],
				onsubmit: onsubmit_callback
			} );
		},

		codeRemove: function(editor){
			currentNode = editor.selection.getNode();
			currentID = $(currentNode).attr("data-syntax-id");

			// Removes node from TinyMCE.
			currentNode.remove();

			// Removes text from meta.
			allValues = JSON.parse($('#syntax-storage').val());
			delete allValues[currentID];
			charactersString = JSON.stringify(allValues);
			// $('#syntax-storage').val(charactersString);
			writeMeta(allValues);
			editor.nodeChanged();
		},
	};

	// Helps sort object properties.
	function syntaxSort(a, b) {
		return a - b;
	}

	// Writes JSON data to meta field.
	function writeMeta(syntaxObject) {
		if ($.isEmptyObject(syntaxObject)) {
			syntaxString = '';
		} else {
			syntaxString = JSON.stringify(syntaxObject);
		}

		$('#syntax-storage').val(syntaxString);
	}

	// Converts HTML characters to their respective character entities.
	function htmlEntities(str) {
		return String(str).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;');
	}

}(jQuery));
