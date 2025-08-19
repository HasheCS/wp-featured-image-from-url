(function($){
  function addFromUrlTab(frame, postId){
    if (!frame || frame._fifuPatched) return;
    frame._fifuPatched = true;

    // Add a router tab
    frame.on('router:render:browse', function(routerView){
      routerView.set({
        fromURL: { text: FIFU.tab, priority: 60 }
      });
    });

    // Render our custom panel when that tab is selected
    frame.on('content:render:fromURL', function(){
      var View = wp.media.View.extend({
        className: 'media-frame-content',
        render: function(){
          var html = '' +
            '<div style="padding:16px;max-width:560px;">' +
              '<p><input type="url" class="regular-text fifu-url" style="width:100%" placeholder="'+ FIFU.ph +'" aria-label="Image URL" /></p>' +
              '<p><button type="button" class="button button-primary fifu-go" aria-label="Fetch image">'+ FIFU.btn +'</button></p>' +
              '<p class="fifu-msg" style="margin-top:8px;"></p>' +
            '</div>';
          this.$el.html(html);
          return this;
        },
        ready: function(){
          var $root = this.$el;
          $root.on('click', '.fifu-go', function(e){
            e.preventDefault();
            var url = $.trim($root.find('.fifu-url').val());
            if (!url) return;
            var $btn = $(this), $msg = $root.find('.fifu-msg');
            $btn.prop('disabled', true); $msg.text(FIFU.fetch);

            $.post(FIFU.ajax, {
              action: 'fifu_fetch_featured_from_url',
              nonce: FIFU.nonce,
              postId: FIFU.postId || 0,
              url: url
            }).done(function(resp){
              if (resp && resp.success){
                $msg.text(FIFU.ok);
                if (resp.data && resp.data.html){
                  $('#postimagediv .inside').html(resp.data.html);
                }
                // Gutenberg: refresh featured image in block editor
                try {
                  if (wp.data && wp.data.dispatch) {
                    wp.data.dispatch('core/editor').editPost({ featured_media: resp.data.id });
                  }
                } catch(e){}
                try { frame.close(); } catch(e){}
              } else {
                $msg.text((resp && resp.data && resp.data.message) || FIFU.err);
              }
            }).fail(function(){
              $msg.text(FIFU.err);
            }).always(function(){ $btn.prop('disabled', false); });
          });
        }
      });
      frame.content.set(new View());
    });
  }

  // Patch Featured Image frame factory
  if (window.wp && wp.media && wp.media.featuredImage && wp.media.featuredImage.frame){
    var orig = wp.media.featuredImage.frame;
    wp.media.featuredImage.frame = function(){
      var f = orig.apply(this, arguments);
      addFromUrlTab(f, FIFU.postId || 0);
      return f;
    };
  }

  // Also patch generic select frames (block editor, site editor, etc.)
  $(document).on('click', '#set-post-thumbnail, .editor-post-featured-image__toggle, .editor-post-featured-image__edit', function(){
    setTimeout(function(){
      if (window.wp && wp.media && wp.media.frame) addFromUrlTab(wp.media.frame, FIFU.postId || 0);
    }, 50);
  });

})(jQuery);
