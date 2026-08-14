/**
 * Slide Captcha - jQuery Plugin
 * 滑块验证码组件
 *
 * Usage:
 *   $.slideCaptcha({
 *     onSuccess: function(token) { console.log('verified', token); },
 *     onError: function() { console.log('failed'); },
 *     onClose: function() { console.log('closed'); }
 *   });
 */
(function($, window, document) {
    'use strict';

    if (typeof $ === 'undefined' || !$ || typeof $.fn !== 'object') {
        if (typeof console !== 'undefined' && console.error) {
            console.error('SlideCaptcha: jQuery is required but not loaded.');
        }
        return;
    }

    var pluginName = 'slideCaptcha';
    var activeInstance = null;

    // 自动探测站点根路径，支持子目录部署
    function getBasePath() {
        var scripts = document.getElementsByTagName('script');
        for (var i = 0; i < scripts.length; i++) {
            var src = scripts[i].src;
            if (src && src.indexOf('slide-captcha.js') !== -1) {
                var idx = src.indexOf('static/js/slide-captcha.js');
                if (idx !== -1) {
                    var path = src.substring(0, idx);
                    return path || '/';
                }
            }
        }
        // 兜底：尝试取当前页面 base href 或根路径
        var base = document.querySelector('base[href]');
        if (base) {
            var href = base.getAttribute('href') || '/';
            return href.charAt(href.length - 1) === '/' ? href : href + '/';
        }
        // 再兜底：根据当前 URL 路径推测根路径
        var path = window.location.pathname || '/';
        if (path.indexOf('/index/') !== -1) {
            return path.substring(0, path.indexOf('/index/')) + '/';
        }
        return '/';
    }

    var basePath = getBasePath();
    if (basePath && basePath.charAt(basePath.length - 1) !== '/') {
        basePath += '/';
    }

    function log(msg) {
        if (typeof console !== 'undefined' && console.log) {
            console.log('[SlideCaptcha] ' + msg);
        }
    }

    function Plugin(options) {
        this.options = $.extend({}, $.fn[pluginName].defaults, options);
        this.init();
    }

    Plugin.prototype.init = function() {
        var self = this;

        // 关闭已存在的实例，防止重复弹窗
        if (activeInstance && activeInstance.$overlay) {
            activeInstance.close();
        }
        activeInstance = self;

        // Create overlay
        this.$overlay = $('<div class="slide-captcha-overlay"></div>');
        this.$box = $('<div class="slide-captcha-box"></div>');

        // Header
        var $header = $('<div class="slide-captcha-header">' +
            '<span>请完成安全验证</span>' +
            '<button class="slide-captcha-close">&times;</button>' +
        '</div>');

        // Image area
        this.$imageWrap = $('<div class="slide-captcha-image-wrap">' +
            '<div class="slide-captcha-loading">加载中...</div>' +
        '</div>');

        // Track
        this.$track = $('<div class="slide-captcha-track">' +
            '<div class="slide-captcha-track-fill"></div>' +
            '<div class="slide-captcha-track-text">拖动滑块完成验证</div>' +
            '<div class="slide-captcha-slider">&rarr;</div>' +
        '</div>');

        // Tip
        this.$tip = $('<div class="slide-captcha-tip"></div>');

        this.$box.append($header, this.$imageWrap, this.$track, this.$tip);
        this.$overlay.append(this.$box);
        $('body').append(this.$overlay);

        // Events
        this.$box.on('click.slideCaptcha', '.slide-captcha-close', function() {
            self.close();
        });

        this.$overlay.on('click.slideCaptcha', function(e) {
            if (e.target === this) {
                self.close();
            }
        });

        // Prevent click on box from closing
        this.$box.on('click.slideCaptcha', function(e) {
            e.stopPropagation();
        });

        // Load captcha
        this.loadCaptcha();

        // Bind slider events
        this.bindSlider();
    };

    Plugin.prototype.loadCaptcha = function() {
        var self = this;
        this.verified = false;
        this.dragging = false;

        this.$imageWrap.html('<div class="slide-captcha-loading">加载中...</div>');
        this.$track.find('.slide-captcha-track-fill').css('width', '0');
        this.$track.find('.slide-captcha-slider').css('left', '0').removeClass('slide-captcha-success slide-captcha-error');
        this.$track.find('.slide-captcha-track-text').text('拖动滑块完成验证');
        this.$tip.text('').removeClass('success error');

        var apiUrl = basePath + 'captcha/generate';
        log('loading captcha from: ' + apiUrl);

        $.ajax({
            url: apiUrl,
            type: 'GET',
            dataType: 'json',
            cache: false,
            success: function(res) {
                if (res.code === 1) {
                    self.captchaToken = res.token || '';
                    self.bgData = res.bg;
                    self.puzzleData = res.puzzle;
                    self.puzzleWidth = res.puzzle_width;
                    self.bgWidth = res.bg_width;
                    self.puzzleY = res.y;
                    self.maxLeft = self.bgWidth - self.puzzleWidth;

                    self.$imageWrap.html(
                        '<img class="slide-captcha-bg" src="' + res.bg + '" alt="">' +
                        '<div class="slide-captcha-puzzle" style="top:' + res.y + 'px; width:' + res.puzzle_width + 'px; height:' + res.puzzle_width + 'px;">' +
                            '<img src="' + res.puzzle + '" style="width:100%;height:100%;display:block;">' +
                        '</div>'
                    );
                    self.$puzzle = self.$imageWrap.find('.slide-captcha-puzzle');
                } else {
                    self.$imageWrap.html('<div class="slide-captcha-loading">加载失败，请刷新</div>');
                }
            },
            error: function(xhr, status, err) {
                log('load captcha error: ' + status + ' ' + (err || ''));
                self.$imageWrap.html('<div class="slide-captcha-loading">加载失败，请刷新 (' + (status || 'error') + ')</div>');
            }
        });
    };

    Plugin.prototype.bindSlider = function() {
        var self = this;
        var $slider = this.$track.find('.slide-captcha-slider');
        var $fill = this.$track.find('.slide-captcha-track-fill');
        var $text = this.$track.find('.slide-captcha-track-text');
        var trackWidth, startX, sliderStartLeft;
        var isDragging = false;

        function getEventX(e) {
            var oe = e.originalEvent || e;
            if (oe && oe.touches && oe.touches.length > 0) {
                return oe.touches[0].clientX;
            }
            if (oe && oe.changedTouches && oe.changedTouches.length > 0) {
                return oe.changedTouches[0].clientX;
            }
            if (oe && typeof oe.clientX !== 'undefined') {
                return oe.clientX;
            }
            return e.clientX || 0;
        }

        function onStart(e) {
            if (self.verified) return;
            if (self.dragging) return;
            if (e && e.preventDefault) e.preventDefault();
            isDragging = true;
            self.dragging = true;
            trackWidth = self.$track.width();
            startX = getEventX(e);
            sliderStartLeft = $slider.position().left;
            $slider.addClass('slide-captcha-active');
        }

        function onMove(e) {
            if (!isDragging) return;
            if (e && e.preventDefault) e.preventDefault();
            var currentX = getEventX(e);
            var deltaX = currentX - startX;
            var newLeft = sliderStartLeft + deltaX;
            var maxLeft = trackWidth - $slider.outerWidth();

            if (newLeft < 0) newLeft = 0;
            if (newLeft > maxLeft) newLeft = maxLeft;

            $slider.css('left', newLeft + 'px');
            $fill.css('width', (newLeft + $slider.outerWidth() / 2) + 'px');

            // Move puzzle piece proportionally
            if (self.$puzzle && self.puzzleWidth) {
                var ratio = newLeft / maxLeft;
                var puzzleLeft = ratio * self.maxLeft;
                self.$puzzle.css('left', puzzleLeft + 'px');
            }
        }

        function onEnd(e) {
            if (!isDragging) return;
            isDragging = false;
            self.dragging = false;
            $slider.removeClass('slide-captcha-active');

            var sliderLeft = $slider.position().left;
            var maxLeft = trackWidth - $slider.outerWidth();
            var ratio = sliderLeft / maxLeft;
            var guessX = Math.round(ratio * self.maxLeft);

            if (sliderLeft < 2) {
                // Didn't really drag
                return;
            }

            // Verify
            var verifyUrl = basePath + 'captcha/verify';
            log('verifying captcha at: ' + verifyUrl + ' x=' + guessX);

            var verifyData = { slide_x: guessX, captcha_token: self.captchaToken || '' };
            if (self.options.email) {
                verifyData.email = self.options.email;
            }
            $.ajax({
                url: verifyUrl,
                type: 'POST',
                data: verifyData,
                dataType: 'json',
                cache: false,
                success: function(res) {
                    if (res.code === 1) {
                        // Success
                        self.verified = true;
                        $slider.addClass('slide-captcha-success');
                        $fill.css({
                            'width': '100%',
                            'border-radius': '20px',
                            'background': 'linear-gradient(90deg, #e8f5e9, #c8e6c9)'
                        });
                        $text.text('验证通过');
                        self.$tip.text('验证通过').addClass('success').removeClass('error');

                        // Generate token
                        var token = 'captcha_' + Date.now() + '_' + Math.random().toString(36).substr(2, 8);

                        if (typeof self.options.onSuccess === 'function') {
                            self.options.onSuccess(token);
                        }

                        // Auto close after delay
                        setTimeout(function() {
                            self.close();
                        }, 800);
                    } else {
                        // Failed
                        self.$tip.text(res.msg || '验证失败，请重试').addClass('error').removeClass('success');
                        $slider.addClass('slide-captcha-error');

                        if (typeof self.options.onError === 'function') {
                            self.options.onError();
                        }

                        // Reset after delay
                        setTimeout(function() {
                            self.resetSlider();
                            self.loadCaptcha();
                        }, 600);
                    }
                },
                error: function(xhr, status, err) {
                    log('verify captcha error: ' + status + ' ' + (err || ''));
                    self.$tip.text('网络错误，请重试').addClass('error').removeClass('success');
                    $slider.addClass('slide-captcha-error');
                    setTimeout(function() {
                        self.resetSlider();
                        self.loadCaptcha();
                    }, 600);
                }
            });
        }

        $slider.on('mousedown.slideCaptcha', onStart);
        $(document).on('mousemove.slideCaptcha', function(e) {
            onMove({ originalEvent: e, preventDefault: function(){} });
        });
        $(document).on('mouseup.slideCaptcha', function(e) {
            onEnd({ originalEvent: e });
        });

        $slider.on('touchstart.slideCaptcha', function(e) {
            onStart(e);
        });
        $(document).on('touchmove.slideCaptcha', function(e) {
            onMove({ originalEvent: e, preventDefault: function(){} });
        });
        $(document).on('touchend.slideCaptcha', function(e) {
            onEnd({ originalEvent: e });
        });
    };

    Plugin.prototype.resetSlider = function() {
        var $slider = this.$track.find('.slide-captcha-slider');
        var $fill = this.$track.find('.slide-captcha-track-fill');
        var $text = this.$track.find('.slide-captcha-track-text');

        $slider.css('left', '0').removeClass('slide-captcha-success slide-captcha-error');
        $fill.css({ 'width': '0', 'border-radius': '20px 0 0 20px', 'background': 'linear-gradient(90deg, #e8f5e9, #c8e6c9)' });
        $text.text('拖动滑块完成验证');
    };

    Plugin.prototype.close = function() {
        if (typeof this.options.onClose === 'function') {
            this.options.onClose();
        }
        this.destroy();
    };

    Plugin.prototype.destroy = function() {
        if (this.$overlay) {
            this.$overlay.remove();
            this.$overlay = null;
        }
        $(document).off('.slideCaptcha');
        if (activeInstance === this) {
            activeInstance = null;
        }
    };

    $.fn[pluginName] = function(options) {
        var plugin = new Plugin(options || {});
        return plugin;
    };

    $.fn[pluginName].defaults = {
        onSuccess: function(token) {},
        onError: function() {},
        onClose: function() {}
    };

    // Also expose as static method for convenience: $.slideCaptcha({...})
    $.slideCaptcha = $.fn.slideCaptcha;
    window.slideCaptcha = $.slideCaptcha;

    log('initialized, basePath=' + basePath);

})(window.jQuery || window.$, window, document);
