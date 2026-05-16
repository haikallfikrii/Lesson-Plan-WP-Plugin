/**
 * Enhanced JavaScript for Lesson Plan Editor with Academy LMS Integration
 * Handles 3-step AI generation workflow and chapter management
 */

jQuery(document).ready(function($) {
    'use strict';
    
    // Initialize classes
    var ChapterManager = new ChapterManagerClass();
    var AIGenerator = new AIGeneratorClass();
    var AcademyPublisher = new AcademyPublisherClass();
    
    // Chapter Manager Class
    function ChapterManagerClass() {
        this.currentChapter = null;
        this.chapters = [];
        
        this.init();
    }
    
    ChapterManagerClass.prototype.init = function() {
        this.bindEvents();
    };
    
    ChapterManagerClass.prototype.bindEvents = function() {
        var self = this;
        
        // Book upload handler
        $('#book_upload').on('change', function(e) {
            self.handleBookUpload(e);
        });
        
        // Chapter selection handler
        $(document).on('click', '.chapter-item', function(e) {
            e.preventDefault();
            var chapterId = $(this).data('chapter-id');
            self.selectChapter(chapterId);
        });
        
        // Chapter expand/collapse
        $(document).on('click', '.chapter-toggle', function(e) {
            e.preventDefault();
            var chapterId = $(this).data('chapter-id');
            self.toggleChapter(chapterId);
        });
    };
    
    ChapterManagerClass.prototype.handleBookUpload = function(e) {
        var file = e.target.files[0];
        if (!file) return;
        
        var formData = new FormData();
        formData.append('action', 'my_lesson_editor_parse_book');
        formData.append('nonce', myLessonEditorAjax.nonce);
        formData.append('book_file', file);
        formData.append('book_title', $('#book_title').val() || file.name);
        
        $.ajax({
            url: myLessonEditorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            processData: false,
            contentType: false,
            beforeSend: function() {
                $('#book_upload_status').html('<div class="loading">Parsing book...</div>');
            },
            success: function(response) {
                if (response.success) {
                    ChapterManager.chapters = response.data.chapters;
                    ChapterManager.renderChapters();
                    $('#book_upload_status').html('<div class="success">Book parsed successfully!</div>');
                } else {
                    $('#book_upload_status').html('<div class="error">Error: ' + response.data + '</div>');
                }
            },
            error: function() {
                $('#book_upload_status').html('<div class="error">Upload failed. Please try again.</div>');
            }
        });
    };
    
    ChapterManagerClass.prototype.renderChapters = function() {
        var html = '<div class="chapters-container">';
        html += '<h3>Chapters (' + this.chapters.length + ')</h3>';
        
        this.chapters.forEach(function(chapter, index) {
            html += '<div class="chapter-item" data-chapter-id="' + chapter.id + '">';
            html += '<div class="chapter-header">';
            html += '<span class="chapter-number">Chapter ' + chapter.chapter_number + '</span>';
            html += '<span class="chapter-title">' + chapter.chapter_title + '</span>';
            html += '<span class="chapter-meta">' + chapter.word_count + ' words, ' + chapter.estimated_time + '</span>';
            html += '<button class="chapter-toggle" data-chapter-id="' + chapter.id + '">Expand</button>';
            html += '</div>';
            html += '<div class="chapter-content" style="display: none;">';
            html += '<div class="chapter-text">' + chapter.content.substring(0, 200) + '...</div>';
            html += '<div class="chapter-actions">';
            html += '<button class="btn-generate-content" data-chapter-id="' + chapter.id + '">Generate AI Content</button>';
            html += '<button class="btn-view-published" data-chapter-id="' + chapter.id + '">View Published</button>';
            html += '</div>';
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        $('#chapters_list').html(html);
    };
    
    ChapterManagerClass.prototype.selectChapter = function(chapterId) {
        this.currentChapter = this.chapters.find(function(chapter) {
            return chapter.id == chapterId;
        });
        
        if (this.currentChapter) {
            $('.chapter-item').removeClass('active');
            $('.chapter-item[data-chapter-id="' + chapterId + '"]').addClass('active');
            
            // Load chapter content into editor
            this.loadChapterToEditor();
        }
    };
    
    ChapterManagerClass.prototype.toggleChapter = function(chapterId) {
        var $content = $('.chapter-content[data-chapter-id="' + chapterId + '"]');
        var $toggle = $('.chapter-toggle[data-chapter-id="' + chapterId + '"]');
        
        if ($content.is(':visible')) {
            $content.slideUp();
            $toggle.text('Expand');
        } else {
            $content.slideDown();
            $toggle.text('Collapse');
        }
    };
    
    ChapterManagerClass.prototype.loadChapterToEditor = function() {
        if (!this.currentChapter) return;
        
        $('#new_lesson_name').val(this.currentChapter.chapter_title);
        $('#lesson_content').val(this.currentChapter.content);
        
        // Update TinyMCE if available
        if (typeof tinymce !== 'undefined' && tinymce.get('lesson_content')) {
            tinymce.get('lesson_content').setContent(this.currentChapter.content);
        }
    };
    
    // AI Generator Class
    function AIGeneratorClass() {
        this.currentStep = 1;
        this.maxSteps = 3;
        this.stepData = {};
        this.selectedContentTypes = [];
        
        this.init();
    }
    
    AIGeneratorClass.prototype.init = function() {
        this.bindEvents();
    };
    
    AIGeneratorClass.prototype.bindEvents = function() {
        var self = this;
        
        // Content type selection
        $(document).on('change', '.content-type-checkbox', function() {
            self.updateSelectedContentTypes();
        });
        
        // Start AI generation
        $(document).on('click', '.btn-generate-content', function(e) {
            e.preventDefault();
            var chapterId = $(this).data('chapter-id');
            self.startGeneration(chapterId);
        });
        
        // Step navigation
        $(document).on('click', '.btn-next-step', function() {
            self.nextStep();
        });
        
        $(document).on('click', '.btn-prev-step', function() {
            self.prevStep();
        });
        
        // Generate content
        $(document).on('click', '.btn-generate-final', function() {
            self.generateFinalContent();
        });
    };
    
    AIGeneratorClass.prototype.updateSelectedContentTypes = function() {
        this.selectedContentTypes = [];
        $('.content-type-checkbox:checked').each(function() {
            this.selectedContentTypes.push($(this).val());
        });
        
        // Update selection summary
        this.updateSelectionSummary();
    };
    
    AIGeneratorClass.prototype.updateSelectionSummary = function() {
        var count = this.selectedContentTypes.length;
        var $countElement = $('#selected-count');
        var $typesElement = $('#selected-types');
        
        // Update count
        $countElement.text(count + ' selected');
        
        // Update selected types display
        if (count > 0) {
            var typeLabels = {
                'lesson_plan': 'Lesson Plans',
                'vocabulary': 'Vocabulary Lists',
                'flashcards': 'Flashcards',
                'quiz': 'Quizzes',
                'midterm': 'Midterm Exams',
                'final': 'Final Exams'
            };
            
            var selectedLabels = this.selectedContentTypes.map(function(type) {
                return typeLabels[type] || type;
            });
            
            $typesElement.html('<div class="selected-types-list">' + 
                selectedLabels.map(function(label) {
                    return '<span class="selected-type-tag">' + label + '</span>';
                }).join('') + 
                '</div>');
        } else {
            $typesElement.html('<span class="text-gray-500 italic">No content types selected</span>');
        }
    };
    
    AIGeneratorClass.prototype.startGeneration = function(chapterId) {
        if (this.selectedContentTypes.length === 0) {
            Swal.fire({
                icon: 'warning',
                title: 'No Content Types Selected',
                text: 'Please select at least one content type to generate.',
                confirmButtonText: 'OK'
            });
            return;
        }
        
        this.currentStep = 1;
        this.stepData = {};
        this.chapterId = chapterId;
        
        this.showStepModal();
    };
    
    AIGeneratorClass.prototype.showStepModal = function() {
        var stepContent = this.getStepContent();
        
        Swal.fire({
            title: 'AI Content Generation - Step ' + this.currentStep + ' of ' + this.maxSteps,
            html: stepContent,
            showCancelButton: true,
            confirmButtonText: this.currentStep < this.maxSteps ? 'Next Step' : 'Generate Content',
            cancelButtonText: 'Cancel',
            allowOutsideClick: false,
            didOpen: function() {
                // Bind step-specific events
                AIGenerator.bindStepEvents();
            }
        }).then((result) => {
            if (result.isConfirmed) {
                if (AIGenerator.currentStep < AIGenerator.maxSteps) {
                    AIGenerator.nextStep();
                } else {
                    AIGenerator.generateFinalContent();
                }
            }
        });
    };
    
    AIGeneratorClass.prototype.getStepContent = function() {
        var content = '<div class="ai-step-content">';
        
        switch (this.currentStep) {
            case 1:
                content += '<h4>Step 1: Learning Objectives and Goals</h4>';
                content += '<div class="form-group">';
                content += '<label>Learning Objectives:</label>';
                content += '<textarea id="learning_objectives" class="form-control" rows="3" placeholder="Define what students should learn from this content..."></textarea>';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Target Audience:</label>';
                content += '<select id="target_audience" class="form-control">';
                content += '<option value="beginner">Beginner</option>';
                content += '<option value="intermediate">Intermediate</option>';
                content += '<option value="advanced">Advanced</option>';
                content += '</select>';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Difficulty Level:</label>';
                content += '<select id="difficulty_level" class="form-control">';
                content += '<option value="easy">Easy</option>';
                content += '<option value="medium">Medium</option>';
                content += '<option value="hard">Hard</option>';
                content += '</select>';
                content += '</div>';
                break;
                
            case 2:
                content += '<h4>Step 2: Content Structure and Format</h4>';
                content += '<div class="form-group">';
                content += '<label>Content Format:</label>';
                content += '<select id="content_format" class="form-control">';
                content += '<option value="text">Text-based</option>';
                content += '<option value="interactive">Interactive</option>';
                content += '<option value="multimedia">Multimedia</option>';
                content += '</select>';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Assessment Type:</label>';
                content += '<select id="assessment_type" class="form-control">';
                content += '<option value="quiz">Quiz</option>';
                content += '<option value="assignment">Assignment</option>';
                content += '<option value="project">Project</option>';
                content += '</select>';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Interactive Elements:</label>';
                content += '<textarea id="interactive_elements" class="form-control" rows="2" placeholder="Describe interactive elements (videos, simulations, etc.)..."></textarea>';
                content += '</div>';
                break;
                
            case 3:
                content += '<h4>Step 3: Customization and Preferences</h4>';
                content += '<div class="form-group">';
                content += '<label>Language:</label>';
                content += '<select id="language" class="form-control">';
                content += '<option value="english">English</option>';
                content += '<option value="indonesian">Indonesian</option>';
                content += '<option value="bilingual">Bilingual</option>';
                content += '</select>';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Time Allocation (minutes):</label>';
                content += '<input type="number" id="time_allocation" class="form-control" value="30" min="5" max="180">';
                content += '</div>';
                content += '<div class="form-group">';
                content += '<label>Additional Requirements:</label>';
                content += '<textarea id="additional_requirements" class="form-control" rows="3" placeholder="Any specific requirements or preferences..."></textarea>';
                content += '</div>';
                break;
        }
        
        content += '</div>';
        return content;
    };
    
    AIGeneratorClass.prototype.bindStepEvents = function() {
        // Step-specific event bindings can be added here
    };
    
    AIGeneratorClass.prototype.nextStep = function() {
        this.saveStepData();
        this.currentStep++;
        this.showStepModal();
    };
    
    AIGeneratorClass.prototype.prevStep = function() {
        this.currentStep--;
        this.showStepModal();
    };
    
    AIGeneratorClass.prototype.saveStepData = function() {
        var stepKey = 'step_' + this.currentStep;
        this.stepData[stepKey] = {};
        
        // Save form data based on current step
        switch (this.currentStep) {
            case 1:
                this.stepData[stepKey].learning_objectives = $('#learning_objectives').val();
                this.stepData[stepKey].target_audience = $('#target_audience').val();
                this.stepData[stepKey].difficulty_level = $('#difficulty_level').val();
                break;
            case 2:
                this.stepData[stepKey].content_format = $('#content_format').val();
                this.stepData[stepKey].assessment_type = $('#assessment_type').val();
                this.stepData[stepKey].interactive_elements = $('#interactive_elements').val();
                break;
            case 3:
                this.stepData[stepKey].language = $('#language').val();
                this.stepData[stepKey].time_allocation = $('#time_allocation').val();
                this.stepData[stepKey].additional_requirements = $('#additional_requirements').val();
                break;
        }
    };
    
    AIGeneratorClass.prototype.generateFinalContent = function() {
        this.saveStepData();
        
        var formData = {
            action: 'my_lesson_editor_generate_ai_content',
            nonce: myLessonEditorAjax.nonce,
            chapter_id: this.chapterId,
            content_types: this.selectedContentTypes,
            step_data: this.stepData
        };
        
        $.ajax({
            url: myLessonEditorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                Swal.fire({
                    title: 'Generating Content...',
                    text: 'Please wait while AI generates your content.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                Swal.close();
                
                if (response.success) {
                    AIGenerator.displayGeneratedContent(response.data);
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Generation Failed',
                        text: response.data || 'An error occurred during content generation.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Generation Failed',
                    text: 'Failed to connect to AI service.',
                    confirmButtonText: 'OK'
                });
            }
        });
    };
    
    AIGeneratorClass.prototype.displayGeneratedContent = function(contentData) {
        var html = '<div class="generated-content">';
        html += '<h3>Generated Content</h3>';
        
        Object.keys(contentData).forEach(function(contentType) {
            html += '<div class="content-type-section">';
            html += '<h4>' + contentType.charAt(0).toUpperCase() + contentType.slice(1) + '</h4>';
            html += '<div class="content-preview">';
            html += '<pre>' + JSON.stringify(contentData[contentType], null, 2) + '</pre>';
            html += '</div>';
            html += '<div class="content-actions">';
            html += '<button class="btn-edit-content" data-type="' + contentType + '">Edit</button>';
            html += '<button class="btn-save-draft" data-type="' + contentType + '">Save Draft</button>';
            html += '<button class="btn-publish-content" data-type="' + contentType + '">Publish to Academy LMS</button>';
            html += '</div>';
            html += '</div>';
        });
        
        html += '</div>';
        
        $('#generated_content_container').html(html);
        $('#generated_content_modal').modal('show');
    };
    
    // Academy Publisher Class
    function AcademyPublisherClass() {
        this.init();
    }
    
    AcademyPublisherClass.prototype.init = function() {
        this.bindEvents();
    };
    
    AcademyPublisherClass.prototype.bindEvents = function() {
        var self = this;
        
        // Publish content to Academy LMS
        $(document).on('click', '.btn-publish-content', function(e) {
            e.preventDefault();
            var contentType = $(this).data('type');
            self.showPublishModal(contentType);
        });
        
        // Confirm publish
        $(document).on('click', '.btn-confirm-publish', function(e) {
            e.preventDefault();
            var publishType = $('#publish_type').val();
            var contentType = $(this).data('content-type');
            self.publishToAcademyLMS(contentType, publishType);
        });
    };
    
    AcademyPublisherClass.prototype.showPublishModal = function(contentType) {
        var html = '<div class="publish-modal">';
        html += '<h4>Publish ' + contentType.charAt(0).toUpperCase() + contentType.slice(1) + ' to Academy LMS</h4>';
        html += '<div class="form-group">';
        html += '<label>Publish By:</label>';
        html += '<select id="publish_type" class="form-control">';
        html += '<option value="by_chapter">By Chapter</option>';
        html += '<option value="by_lesson_plan">By Lesson Plan</option>';
        html += '<option value="by_vocabulary">By Vocabulary List</option>';
        html += '<option value="by_flashcard">By Flashcard</option>';
        html += '<option value="by_quiz">By Quiz</option>';
        html += '</select>';
        html += '</div>';
        html += '<div class="form-group">';
        html += '<label>Additional Options:</label>';
        html += '<div class="checkbox-group">';
        html += '<label><input type="checkbox" id="include_video"> Include Video Embed</label>';
        html += '<label><input type="checkbox" id="include_slides"> Include AI-Generated Slides</label>';
        html += '</div>';
        html += '</div>';
        html += '</div>';
        
        Swal.fire({
            title: 'Publish to Academy LMS',
            html: html,
            showCancelButton: true,
            confirmButtonText: 'Publish',
            cancelButtonText: 'Cancel',
            didOpen: function() {
                $('.btn-confirm-publish').data('content-type', contentType);
            }
        });
    };
    
    AcademyPublisherClass.prototype.publishToAcademyLMS = function(contentType, publishType) {
        var formData = {
            action: 'my_lesson_editor_publish_to_academy',
            nonce: myLessonEditorAjax.nonce,
            content_type: contentType,
            publish_type: publishType,
            chapter_id: ChapterManager.currentChapter ? ChapterManager.currentChapter.id : null,
            include_video: $('#include_video').is(':checked'),
            include_slides: $('#include_slides').is(':checked')
        };
        
        $.ajax({
            url: myLessonEditorAjax.ajaxurl,
            type: 'POST',
            data: formData,
            beforeSend: function() {
                Swal.fire({
                    title: 'Publishing...',
                    text: 'Publishing content to Academy LMS.',
                    allowOutsideClick: false,
                    showConfirmButton: false,
                    didOpen: function() {
                        Swal.showLoading();
                    }
                });
            },
            success: function(response) {
                Swal.close();
                
                if (response.success) {
                    Swal.fire({
                        icon: 'success',
                        title: 'Published Successfully!',
                        text: 'Content has been published to Academy LMS.',
                        confirmButtonText: 'OK'
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Publishing Failed',
                        text: response.data || 'An error occurred during publishing.',
                        confirmButtonText: 'OK'
                    });
                }
            },
            error: function() {
                Swal.close();
                Swal.fire({
                    icon: 'error',
                    title: 'Publishing Failed',
                    text: 'Failed to connect to Academy LMS.',
                    confirmButtonText: 'OK'
                });
            }
        });
    };
    
    // Initialize existing functionality
    initializeExistingFeatures();
    
    // Initialize selection summary on page load
    AIGenerator.updateSelectionSummary();
    
    function initializeExistingFeatures() {
        // Your existing code for media uploader, form handling, etc.
        // This ensures compatibility with existing features
        
        // Media Uploader for Lesson Cover (existing code)
        var lessonCoverUploader;
        
        $(document).on('click', '.browse-lesson-cover', function(e) {
            e.preventDefault();
            
            if (lessonCoverUploader) {
                lessonCoverUploader.open();
                return;
            }
            
            lessonCoverUploader = wp.media.frames.file_frame = wp.media({
                title: 'Choose Lesson Cover Image',
                button: { text: 'Select Cover' },
                multiple: false
            });
            
            lessonCoverUploader.on('select', function() {
                var attachment = lessonCoverUploader.state().get('selection').first().toJSON();
                $('#lesson_cover_id').val(attachment.id);
                $('#lesson_cover_url').val(attachment.url);
                $('#lesson-cover-preview img').attr('src', attachment.url);
                $('#lesson-cover-preview').show();
            });
            
            lessonCoverUploader.open();
        });
        
        // Remove image handler
        $(document).on('click', '.remove-lesson-cover', function(e) {
            e.preventDefault();
            $('#lesson_cover_id').val('');
            $('#lesson_cover_url').val('');
            $('#lesson-cover-preview img').attr('src', '');
            $('#lesson-cover-preview').hide();
        });
    }
});

// ========= DOWNLOAD BUTTON HANDLER FOR LESSON PLAN/VOCAB =========
$(document).on('click', '.mle-download-btn', function(e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.hasClass('loading')) return;
    $btn.addClass('loading').prop('disabled', true);
    var id = $btn.data('id');
    var type = $btn.data('type');
    var nonce = $btn.data('nonce');
    fetch(myLessonEditorAjax.ajaxurl, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: new URLSearchParams({
            action: 'mle_download_item',
            id: id,
            type: type,
            nonce: nonce
        })
    })
    .then(function(resp) {
        if (!resp.ok) throw new Error('Gagal mengunduh file!');
        var disp = resp.headers.get('Content-Disposition');
        var filename = 'item-download.pdf';
        if (disp && disp.indexOf('filename=')!==-1) {
            filename = disp.split('filename=')[1].replace(/"/g,'');
        }
        return resp.blob().then(function(blob) { return { blob: blob, filename: filename }; });
    })
    .then(function(obj) {
        var url = URL.createObjectURL(obj.blob);
        var a = document.createElement('a');
        a.href = url;
        a.download = obj.filename;
        document.body.appendChild(a);
        a.click();
        setTimeout(function() {
            URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }, 200);
    })
    .catch(function(err) {
        alert('Gagal download file. Silakan coba lagi.');
    })
    .finally(function() {
        $btn.removeClass('loading').prop('disabled', false);
    });
});

// ========= DELETE BUTTON HANDLER =========
$(document).on('click', '.delete-item', function(e) {
    e.preventDefault();
    var $btn = $(this);
    if ($btn.hasClass('loading')) {
        return;
    }

    var itemId = parseInt($btn.data('id'), 10);
    var contentType = $btn.data('content-type');
    var deleteNonce = $('#caast_delete_nonce').val();
    var ajaxConfig = window.myLessonEditorAjax || window.mylessonEditorAjax || {};

    if (!itemId || !contentType || !deleteNonce) {
        console.warn('Missing delete parameters.', itemId, contentType);
        return;
    }

    if (!ajaxConfig.ajaxurl) {
        console.error('AJAX URL missing for delete request.');
        return;
    }

    var confirmMessage = ajaxConfig.delete_confirm_message || 'Are you sure you want to delete this item?';
    var proceedDeletion = function() {
        $btn.addClass('loading').prop('disabled', true);

        $.ajax({
            url: ajaxConfig.ajaxurl,
            method: 'POST',
            dataType: 'json',
            data: {
                action: 'caast_delete_item',
                nonce: deleteNonce,
                item_id: itemId,
                content_type: contentType
            }
        }).done(function(response) {
            if (!response || !response.success) {
                var errorMsg = response && response.data && response.data.message
                    ? response.data.message
                    : (ajaxConfig.delete_error_message || 'Failed to delete this item. Please try again.');
                showDeleteToast('error', errorMsg);
                return;
            }

            if (response.data && response.data.new_nonce) {
                $('#caast_delete_nonce').val(response.data.new_nonce);
            }

            var $card = $btn.closest('.content-type-card');
            if ($card.length) {
                $card.fadeOut(200, function() {
                    $(this).remove();
                });
            }

            showDeleteToast('success', ajaxConfig.delete_success_message || 'Deleted successfully');
        }).fail(function() {
            showDeleteToast('error', ajaxConfig.delete_error_message || 'Failed to delete this item. Please try again.');
        }).always(function() {
            $btn.removeClass('loading').prop('disabled', false);
        });
    };

    if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire({
            icon: 'warning',
            title: ajaxConfig.delete_confirm_title || 'Delete Item',
            text: confirmMessage,
            showCancelButton: true,
            confirmButtonText: ajaxConfig.delete_confirm_cta || 'Yes, delete it',
            cancelButtonText: ajaxConfig.delete_cancel_cta || 'Cancel',
            focusCancel: true
        }).then(function(result) {
            if (result.isConfirmed) {
                proceedDeletion();
            }
        });
    } else if (window.confirm(confirmMessage)) {
        proceedDeletion();
    }
});

function showDeleteToast(type, message) {
    if (typeof Swal !== 'undefined' && Swal.fire) {
        Swal.fire({
            toast: true,
            icon: type,
            title: message,
            position: 'top-end',
            showConfirmButton: false,
            timer: 2500,
            timerProgressBar: true
        });
    } else {
        if (type === 'error') {
            alert(message);
        } else {
            console.log(message);
        }
    }
}
