document.addEventListener('DOMContentLoaded', function () {
/* FH Mega Menu dropdownHover plugin integration */
(function ($, window, undefined) {
    var $allDropdowns = $();
    $.fn.dropdownHover = function (options) {
        if('ontouchstart' in document) return this;
        $allDropdowns = $allDropdowns.add(this.parent());
        return this.each(function () {
            var $this = $(this),
                $parent = $this.parent(),
                defaults = {
                    delay: 400,
                    instantlyCloseOthers: true
                },
                data = {
                    delay: $(this).data('delay'),
                    instantlyCloseOthers: $(this).data('close-others')
                },
                showEvent   = 'show.bs.dropdown',
                hideEvent   = 'hide.bs.dropdown',
                settings = $.extend(true, {}, defaults, options, data),
                timeout;

            $parent.hover(function (event) {
                if(!$parent.hasClass('open') && !$this.is(event.target)) {
                    return true; 
                }
                if(settings.instantlyCloseOthers === true)
                    $allDropdowns.removeClass('open');
                window.clearTimeout(timeout);
                $parent.addClass('open');
                $this.trigger(showEvent);
            }, function () {
                timeout = window.setTimeout(function () {
                    $parent.removeClass('open');
                    $this.trigger(hideEvent);
                }, settings.delay);
            });
            $this.hover(function () {
                if(settings.instantlyCloseOthers === true)
                    $allDropdowns.removeClass('open');
                window.clearTimeout(timeout);
                $parent.addClass('open');
                $this.trigger(showEvent);
            });
            $parent.find('.dropdown-submenu').each(function (){
                var $this = $(this);
                var subTimeout;
                $this.hover(function () {
                    window.clearTimeout(subTimeout);
                    $this.children('.dropdown-menu').show();
                    // always close submenu siblings instantly
                    $this.siblings().children('.dropdown-menu').hide();
                }, function () {
                    var $submenu = $this.children('.dropdown-menu');
                    subTimeout = window.setTimeout(function () {
                        $submenu.hide();
                    }, settings.delay);
                });
            });
        });
    };
    $(document).ready(function () {
        // Hide all dropdown-menus initially
        $('.dropdown-menu').hide();

        // Toggle dropdown on click
        $('.dropdown-toggle').on('click', function(e) {
          e.preventDefault();
          var $parent = $(this).parent('.dropdown');
          // Close all other dropdowns
          $('.dropdown').not($parent).removeClass('open').find('.dropdown-menu').hide();
          // Toggle current
          $parent.toggleClass('open');
          $parent.find('> .dropdown-menu').toggle($parent.hasClass('open'));
        });

        // Hover for desktop
        $('.dropdown').hover(
          function() {
            $(this).addClass('open');
            $(this).find('> .dropdown-menu').show();
          },
          function() {
            $(this).removeClass('open');
            $(this).find('> .dropdown-menu').hide();
          }
        );

        // Submenu hover
        $('.dropdown-submenu').hover(
          function() {
            $(this).addClass('open');
            $(this).find('> .dropdown-menu').show();
          },
          function() {
            $(this).removeClass('open');
            $(this).find('> .dropdown-menu').hide();
          }
        );
      });
})(jQuery, this);
});
