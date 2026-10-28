/* global FIFU, wp, jQuery */
(function ($) {
    'use strict';

    if (!window.wp || !wp.media || !wp.media.view || !wp.media.View) {
        return;
    }
    var media = wp.media;

    function isFeatured(frame) {
        var state = frame && typeof frame.state === 'function' ? frame.state() : null;
        return !!(state && (state.id === 'featured-image' ||
            (typeof state.get === 'function' && state.get('id') === 'featured-image')));
    }

    function registerFrame(frame) {
        if (!frame || frame._fifuPatched || typeof frame.on !== 'function') {
            return;
        }
        frame._fifuPatched = true;

        frame.on('router:render:browse', function (router) {
            if (router && typeof router.set === 'function') {
                router.set({ fromURL: { text: FIFU.tab, priority: 65 } });
            }
        });

        frame.on('content:render:fromURL', function () {
            var featured = isFeatured(frame);
            var View = media.View.extend({
                className: 'media-frame-content fifu-url-panel',
                events: {
                    'click .fifu-go': 'fetchImage',
                    'keydown .fifu-url': 'keyDown',
                },
                render: function () {
                    var $wrap = $('<div>', {
                        'class': 'fifu-import-form',
                        'css': { padding: '20px', maxWidth: '640px' },
                    });
                    var $label = $('<label>', {
                        'class': 'fifu-label',
                        text: FIFU.label,
                    });
                    var $input = $('<input>', {
                        type: 'url',
                        'class': 'regular-text fifu-url',
                        placeholder: FIFU.ph,
                        'aria-label': FIFU.label,
                    }).css({ display: 'block', width: '100%', margin: '10px 0' });
                    var $button = $('<button>', {
                        type: 'button',
                        'class': 'button button-primary fifu-go',
                        text: featured ? FIFU.featuredBtn : FIFU.btn,
                    });
                    var $status = $('<p>', {
                        'class': 'fifu-msg',
                        role: 'status',
                        'aria-live': 'polite',
                    });
                    this.$el.empty().append($wrap.append($label, $input, $button, $status));
                    return this;
                },
                keyDown: function (event) {
                    if (event.key === 'Enter' || event.keyCode === 13) {
                        event.preventDefault();
                        this.fetchImage(event);
                    }
                },
                fetchImage: function (event) {
                    event.preventDefault();
                    var view = this;
                    var url = $.trim(view.$('.fifu-url').val());
                    if (!url) {
                        view.$('.fifu-url').trigger('focus');
                        return;
                    }
                    var $button = view.$('.fifu-go');
                    var $status = view.$('.fifu-msg');
                    $button.prop('disabled', true);
                    $status.text(FIFU.fetch);

                    var postId = FIFU.postId || 0;
                    // A newly created Gutenberg draft may acquire its post
                    // ID after the original admin page was loaded.
                    try {
                        if (!postId && wp.data && typeof wp.data.select === 'function') {
                            var editorState = wp.data.select('core/editor');
                            if (editorState && typeof editorState.getCurrentPostId === 'function') {
                                postId = editorState.getCurrentPostId() || 0;
                            }
                        }
                    } catch (ignored) {}
                    $.post(FIFU.ajax, {
                        action: 'fifu_fetch_featured_from_url',
                        nonce: FIFU.nonce,
                        postId: postId,
                        mode: featured ? 'featured' : 'library',
                        url: url,
                    }).done(function (response) {
                        if (!response || !response.success || !response.data) {
                            $status.text(response && response.data && response.data.message || FIFU.err);
                            return;
                        }
                        var data = response.data;
                        $status.text(FIFU.ok);

                        // Add the actual WordPress attachment to the current
                        // library/selection. Do not merely hotlink its URL.
                        if (media.model && media.model.Attachment && data.attachment) {
                            var model = media.model.Attachment.get(data.id);
                            model.set(data.attachment);
                            var state = typeof frame.state === 'function' ? frame.state() : null;
                            var library = state && typeof state.get === 'function' ? state.get('library') : null;
                            var selection = state && typeof state.get === 'function' ? state.get('selection') : null;
                            if (library && typeof library.add === 'function') {
                                library.add(model);
                            }
                            if (selection && typeof selection.reset === 'function') {
                                selection.reset([model]);
                            }
                        }

                        if (featured) {
                            if (data.html) {
                                $('#postimagediv .inside').html(data.html);
                            }
                            try {
                                if (wp.data && wp.data.dispatch) {
                                    var editor = wp.data.dispatch('core/editor');
                                    if (editor && editor.editPost) {
                                        editor.editPost({ featured_media: data.id });
                                    }
                                }
                            } catch (ignored) {}
                            if (typeof frame.close === 'function') {
                                frame.close();
                            }
                        } else {
                            // Return to the native Media Library grid, with
                            // the newly imported image selected for insertion.
                            if (frame.content && typeof frame.content.mode === 'function') {
                                frame.content.mode('browse');
                            }
                            if (frame.router && typeof frame.router.navigate === 'function') {
                                frame.router.navigate('browse');
                            }
                        }
                    }).fail(function (xhr) {
                        $status.text(xhr && xhr.responseJSON && xhr.responseJSON.data &&
                            xhr.responseJSON.data.message || FIFU.err);
                    }).always(function () {
                        $button.prop('disabled', false);
                    });
                },
            });
            frame.content.set(new View());
        });
    }

    // WordPress constructs several different frame types depending on the
    // editor, the Media Library, and Featured Image. Hook constructors rather
    // than depending on one particular button or a 50ms click race.
    ['Select', 'Post', 'Manage'].forEach(function (name) {
        var Klass = media.view.MediaFrame && media.view.MediaFrame[name];
        if (!Klass || !Klass.prototype || typeof Klass.prototype.initialize !== 'function') {
            return;
        }
        var original = Klass.prototype.initialize;
        if (original._fifuWrapped) {
            return;
        }
        var wrapped = function () {
            var result = original.apply(this, arguments);
            registerFrame(this);
            return result;
        };
        wrapped._fifuWrapped = true;
        Klass.prototype.initialize = wrapped;
    });

    // Some WordPress versions create a Featured Image frame through this
    // factory before frame-level hooks would otherwise be registered.
    if (media.featuredImage && typeof media.featuredImage.frame === 'function') {
        var originalFeaturedFrame = media.featuredImage.frame;
        media.featuredImage.frame = function () {
            var frame = originalFeaturedFrame.apply(this, arguments);
            registerFrame(frame);
            return frame;
        };
    }
    if (media.frame) {
        registerFrame(media.frame);
    }

    // Media > Library is normally a full-page grid. Offer a direct entry
    // point there too; the same dialog imports into the site's Media Library.
    $(function () {
        if (!$('body').hasClass('upload-php')) {
            return;
        }
        var $addNew = $('.wrap .page-title-action').first();
        if (!$addNew.length || $('.fifu-library-launch').length) {
            return;
        }
        $('<a>', {
            href: '#',
            'class': 'page-title-action fifu-library-launch',
            text: FIFU.libraryBtn,
        }).insertAfter($addNew).on('click', function (event) {
            event.preventDefault();
            var frame = media({
                title: FIFU.libraryTitle,
                multiple: false,
                button: { text: FIFU.btn },
            });
            registerFrame(frame);
            frame.on('open', function () {
                if (frame.content && typeof frame.content.mode === 'function') {
                    frame.content.mode('fromURL');
                }
            });
            frame.open();
        });
    });
})(jQuery);
