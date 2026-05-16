/**
 * MLEUploader — reusable Upload / Edit / Submit module.
 *
 * Wires a container (data-mle-uploader="<module>") that contains:
 *   <button data-mle-action="upload">  - opens a hidden file picker, then POSTs
 *                                         to the configured "extract" action.
 *   <button data-mle-action="edit">    - moves last text result (or the
 *                                         "previewSource" element value) into
 *                                         the configured TinyMCE editor.
 *   <button data-mle-action="submit">  - POSTs current editor content to the
 *                                         configured "submit" action.
 *   <input  data-mle-file="hidden">    - hidden <input type="file">.
 *   <p      data-mle-status>           - status / error message target.
 *
 * Exposes:
 *   window.MLEUploader.attach(options)
 *
 * options = {
 *   container:     CSS selector or Element (required)
 *   ajaxConfig:    { ajaxurl, nonce } (required)
 *   actions: {
 *     extract:   string   (AJAX action used by Upload button)
 *     submit:    string   (AJAX action used by Submit button)
 *   },
 *   editorId:    string   (TinyMCE id; falls back to <textarea id="...">)
 *   titleInputId:string   (input that holds the title for Submit; optional)
 *   courseSelectId: string (optional; sent as course_id with Submit)
 *   bookNameInputId: string (optional; receives uploaded filename label)
 *   onUploaded:  function(payload)   (optional callback after extract)
 *   onSubmitted: function(payload)   (optional callback after submit)
 *   confirmSubmit: boolean (default true)
 *   submitExtraFields: function() => object (extra POST fields for submit)
 * }
 */
(function() {
	'use strict';

	function $(sel, root) {
		root = root || document;
		return typeof sel === 'string' ? root.querySelector(sel) : sel;
	}

	function spinner(btn, on) {
		if (!btn) {
			return;
		}
		var s = btn.querySelector('.mle-btn-spinner');
		if (s) {
			s.classList.toggle('hidden', !on);
		}
		btn.disabled = !!on;
		btn.classList.toggle('opacity-60', !!on);
	}

	function setStatus(container, type, message) {
		var el = container.querySelector('[data-mle-status]');
		if (!el) {
			return;
		}
		el.textContent = message || '';
		el.classList.remove('text-gray-600', 'text-green-700', 'text-red-700', 'text-blue-700');
		var cls = 'text-gray-600';
		if (type === 'success') {
			cls = 'text-green-700';
		} else if (type === 'error') {
			cls = 'text-red-700';
		} else if (type === 'loading') {
			cls = 'text-blue-700';
		}
		el.classList.add(cls);
	}

	function notify(type, message) {
		if (window.Swal && typeof Swal.fire === 'function') {
			Swal.fire({
				toast: true,
				icon: type === 'error' ? 'error' : (type === 'success' ? 'success' : 'info'),
				title: message,
				position: 'top-end',
				showConfirmButton: false,
				timer: type === 'error' ? 4500 : 2500,
				timerProgressBar: true
			});
			return;
		}
		if (type === 'error') {
			window.alert(message);
		}
	}

	function getEditorContent(editorId) {
		if (!editorId) {
			return '';
		}
		if (window.tinymce && window.tinymce.get(editorId)) {
			return window.tinymce.get(editorId).getContent({ format: 'html' });
		}
		var ta = document.getElementById(editorId);
		return ta ? (ta.value || '') : '';
	}

	function setEditorContent(editorId, content) {
		if (!editorId) {
			return false;
		}
		if (window.tinymce && window.tinymce.get(editorId)) {
			window.tinymce.get(editorId).setContent(content || '');
			return true;
		}
		var ta = document.getElementById(editorId);
		if (ta) {
			ta.value = content || '';
			ta.dispatchEvent(new Event('input', { bubbles: true }));
			return true;
		}
		return false;
	}

	function escapeHtml(s) {
		return String(s == null ? '' : s)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#39;');
	}

	function textToHtml(text) {
		var safe = escapeHtml(text || '');
		var paragraphs = safe.split(/\n\s*\n/).map(function(p) {
			return '<p>' + p.replace(/\n/g, '<br/>') + '</p>';
		});
		return paragraphs.join('\n');
	}

	function attach(options) {
		options = options || {};
		var container = $(options.container);
		if (!container) {
			return null;
		}
		var ajax = options.ajaxConfig || {};
		if (!ajax.ajaxurl || !ajax.nonce) {
			return null;
		}
		var actions = options.actions || {};
		var fileInput = container.querySelector('[data-mle-file="hidden"]');
		var btnUpload = container.querySelector('[data-mle-action="upload"]');
		var btnEdit = container.querySelector('[data-mle-action="edit"]');
		var btnSubmit = container.querySelector('[data-mle-action="submit"]');

		var lastExtracted = { text: '', html: '', filename: '' };

		function pushBookName(name) {
			if (!options.bookNameInputId) {
				return;
			}
			var el = document.getElementById(options.bookNameInputId);
			if (el && !el.value) {
				el.value = name || '';
			}
		}

		function handleUploadClick() {
			if (!fileInput) {
				notify('error', 'Hidden file input is missing.');
				return;
			}
			fileInput.click();
		}

		function handleFileChange() {
			var file = null;
			try {
				if (fileInput && fileInput.files && fileInput.files.length > 0) {
					file = fileInput.files[0];
				}
			} catch (err) {
				file = null;
			}
			if (!file) {
				return;
			}
			if (!actions.extract) {
				notify('error', 'No extract action configured.');
				return;
			}
			spinner(btnUpload, true);
			setStatus(container, 'loading', 'Uploading and extracting "' + file.name + '"…');

			var fd = new FormData();
			fd.append('action', actions.extract);
			fd.append('nonce', ajax.nonce);
			fd.append('upload_file', file);

			fetch(ajax.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			})
				.then(function(resp) {
					return resp.json().catch(function() {
						return { success: false, data: { message: 'Invalid JSON response.' } };
					});
				})
				.then(function(json) {
					if (!json || !json.success) {
						var msg = (json && json.data && json.data.message) ? json.data.message : 'Extraction failed.';
						setStatus(container, 'error', msg);
						notify('error', msg);
						return;
					}
					lastExtracted.text = (json.data && json.data.text) || '';
					lastExtracted.html = (json.data && json.data.html) || textToHtml(lastExtracted.text);
					lastExtracted.filename = (json.data && json.data.filename) || file.name;
					pushBookName(lastExtracted.filename);
					setStatus(container, 'success', 'Extracted ' + (lastExtracted.text.length) + ' characters from "' + lastExtracted.filename + '". Click Edit to load it into the editor.');
					if (typeof options.onUploaded === 'function') {
						try { options.onUploaded(json.data); } catch (e) { /* noop */ }
					}
				})
				.catch(function(err) {
					var msg = (err && err.message) ? err.message : 'Upload request failed.';
					setStatus(container, 'error', msg);
					notify('error', msg);
				})
				.then(function() {
					spinner(btnUpload, false);
					if (fileInput) {
						fileInput.value = '';
					}
				});
		}

		function pickEditPayload() {
			if (lastExtracted.html || lastExtracted.text) {
				return lastExtracted.html || textToHtml(lastExtracted.text);
			}
			if (options.previewSourceId) {
				var el = document.getElementById(options.previewSourceId);
				if (el && el.value) {
					return textToHtml(el.value);
				}
			}
			return '';
		}

		function handleEditClick() {
			var html = pickEditPayload();
			if (!html) {
				setStatus(container, 'error', 'Nothing to load. Upload a file or generate AI content first.');
				return;
			}
			var ok = setEditorContent(options.editorId, html);
			if (!ok) {
				setStatus(container, 'error', 'Editor "' + options.editorId + '" not found.');
				return;
			}
			setStatus(container, 'success', 'Loaded into editor.');
			notify('success', 'Loaded into editor');
		}

		function handleSubmitClick() {
			if (!actions.submit) {
				notify('error', 'No submit action configured.');
				return;
			}
			var content = getEditorContent(options.editorId);
			if (!content || !content.trim()) {
				setStatus(container, 'error', 'Editor is empty. Nothing to submit.');
				return;
			}
			var titleEl = options.titleInputId ? document.getElementById(options.titleInputId) : null;
			var title = titleEl ? (titleEl.value || '').trim() : '';
			if (!title) {
				setStatus(container, 'error', 'Title is required for submission.');
				return;
			}

			if (options.confirmSubmit !== false && window.Swal && typeof Swal.fire === 'function') {
				Swal.fire({
					title: 'Submit content?',
					text: 'This sends the current editor content to the server.',
					icon: 'question',
					showCancelButton: true,
					confirmButtonText: 'Submit',
					cancelButtonText: 'Cancel'
				}).then(function(r) {
					if (r.isConfirmed) {
						doSubmit(title, content);
					}
				});
			} else {
				doSubmit(title, content);
			}
		}

		function doSubmit(title, content) {
			spinner(btnSubmit, true);
			setStatus(container, 'loading', 'Submitting…');

			var fd = new FormData();
			fd.append('action', actions.submit);
			fd.append('nonce', ajax.nonce);
			fd.append('title', title);
			fd.append('content', content);
			if (options.courseSelectId) {
				var courseEl = document.getElementById(options.courseSelectId);
				if (courseEl && courseEl.value) {
					fd.append('course_id', courseEl.value);
				}
			}
			if (typeof options.submitExtraFields === 'function') {
				try {
					var extras = options.submitExtraFields() || {};
					Object.keys(extras).forEach(function(k) {
						fd.append(k, extras[k]);
					});
				} catch (e) { /* noop */ }
			}

			fetch(ajax.ajaxurl, {
				method: 'POST',
				credentials: 'same-origin',
				body: fd
			})
				.then(function(resp) {
					return resp.json().catch(function() {
						return { success: false, data: { message: 'Invalid JSON response.' } };
					});
				})
				.then(function(json) {
					if (!json || !json.success) {
						var msg = (json && json.data && json.data.message) ? json.data.message : 'Submit failed.';
						setStatus(container, 'error', msg);
						notify('error', msg);
						return;
					}
					var msgOk = (json.data && json.data.message) ? json.data.message : 'Submitted.';
					setStatus(container, 'success', msgOk);
					notify('success', msgOk);
					if (typeof options.onSubmitted === 'function') {
						try { options.onSubmitted(json.data); } catch (e) { /* noop */ }
					}
				})
				.catch(function(err) {
					var msg = (err && err.message) ? err.message : 'Submit request failed.';
					setStatus(container, 'error', msg);
					notify('error', msg);
				})
				.then(function() {
					spinner(btnSubmit, false);
				});
		}

		if (btnUpload) { btnUpload.addEventListener('click', handleUploadClick); }
		if (fileInput) { fileInput.addEventListener('change', handleFileChange); }
		if (btnEdit) { btnEdit.addEventListener('click', handleEditClick); }
		if (btnSubmit) { btnSubmit.addEventListener('click', handleSubmitClick); }

		return {
			loadExternalText: function(text, filename) {
				lastExtracted.text = String(text || '');
				lastExtracted.html = textToHtml(lastExtracted.text);
				lastExtracted.filename = filename || lastExtracted.filename || '';
				if (filename) {
					pushBookName(filename);
				}
				setStatus(container, 'success', 'External content ready. Click Edit to load it.');
			},
			setStatus: function(type, msg) { setStatus(container, type, msg); }
		};
	}

	window.MLEUploader = window.MLEUploader || { attach: attach };
})();
