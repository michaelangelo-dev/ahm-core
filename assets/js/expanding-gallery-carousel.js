/**
 * Expanding Gallery Carousel (Active Card Enlargement) Controller
 *
 * Handles whole-card click expansion, parent-scoped navigation button relocation,
 * pure CSS flex gap spacing, and dynamic badge numbers (1 / N).
 * Supports both dedicated AHM Elementor widget and standard Elementor Nested Carousel.
 *
 * @package AHM_Core
 */

(function () {
  'use strict';

  /**
   * Main Carousel Initialization Function
   */
  function initExpandingCarousel(targetContext) {
    var context = targetContext || document;
    var containers = context.querySelectorAll(
      '.gallery-section-container, .gallery-accordion-section, .elementor-widget-ahm_expanding_gallery_carousel, .gallery-expanding-carousel'
    );

    if (!containers.length) {
      return;
    }

    containers.forEach(function (container) {
      if (container.dataset.expandingInit === 'true') {
        return;
      }

      // Check for carousel element or container itself if class is on widget
      var carouselEl = container.classList.contains('gallery-expanding-carousel')
        ? container
        : container.querySelector('.gallery-expanding-carousel, .expanding-carousel');

      if (!carouselEl) {
        return;
      }

      var swiperEl = carouselEl.querySelector('.swiper, .swiper-container');
      if (!swiperEl) {
        return;
      }

      var slides = swiperEl.querySelectorAll('.swiper-slide, .e-n-carousel-slide');
      if (!slides.length) {
        return;
      }
      var totalSlides = slides.length;

      // Determine initial active slide
      var currentIndex = -1;
      slides.forEach(function (slide, idx) {
        if (slide.classList.contains('is-expanded')) {
          currentIndex = idx;
        }
      });

      if (currentIndex === -1) {
        currentIndex = 0;
        slides[0].classList.add('is-expanded', 'swiper-slide-active');
        var firstBadge = slides[0].querySelector('.slide-badge');
        if (firstBadge) {
          firstBadge.textContent = '1 / ' + totalSlides;
        }
      }

      // Swiper instance helper
      var getSwiperInstance = function () {
        return swiperEl.swiper || null;
      };

      // State updater function
      var updateExpandedState = function (newIndex) {
        if (newIndex < 0 || newIndex >= totalSlides) {
          return;
        }
        currentIndex = newIndex;

        slides.forEach(function (slide, idx) {
          if (idx === newIndex) {
            slide.classList.add('is-expanded', 'swiper-slide-active');
            var badge = slide.querySelector('.slide-badge');
            if (badge) {
              badge.textContent = (idx + 1) + ' / ' + totalSlides;
            }
          } else {
            slide.classList.remove('is-expanded', 'swiper-slide-active');
          }
        });

        var isStacked = window.innerWidth <= 1024 || document.body.classList.contains('elementor-device-mobile') || document.body.classList.contains('elementor-device-tablet');
        var swiper = getSwiperInstance();
        if (swiper && !isStacked) {
          // If slides overflow container width (e.g. desktop), slide into view
          if (!swiper.isLocked && typeof swiper.slideTo === 'function') {
            swiper.slideTo(newIndex);
          }
          setTimeout(function () {
            if (swiper && typeof swiper.update === 'function') {
              swiper.update();
            }
          }, 550);
        }

        if (isStacked && slides[newIndex]) {
          setTimeout(function () {
            var rect = slides[newIndex].getBoundingClientRect();
            if (rect.top < 60 || rect.bottom > window.innerHeight) {
              slides[newIndex].scrollIntoView({ behavior: 'smooth', block: 'nearest' });
            }
          }, 200);
        }
      };

      // Initialize Swiper if not already active (Native Nested Carousel initializes its own)
      if (!getSwiperInstance()) {
        var SwiperConstructor = window.Swiper;
        if (!SwiperConstructor && window.elementorFrontend && window.elementorFrontend.utils && window.elementorFrontend.utils.swiper) {
          SwiperConstructor = window.elementorFrontend.utils.swiper;
        }

        if (SwiperConstructor) {
          new SwiperConstructor(swiperEl, {
            slidesPerView: 'auto',
            spaceBetween: 0,
            speed: 500,
            grabCursor: false,
            allowTouchMove: true,
            initialSlide: currentIndex,
            breakpoints: {
              0: {
                enabled: false,
              },
              1025: {
                enabled: true,
                slidesPerView: 'auto',
                spaceBetween: 0,
              }
            },
            on: {
              slideChange: function () {
                if (typeof this.activeIndex === 'number' && this.activeIndex !== currentIndex) {
                  updateExpandedState(this.activeIndex);
                }
              }
            }
          });
        }
      }

      // 1. Whole-Card Click Trigger
      slides.forEach(function (slide, index) {
        slide.addEventListener('click', function (e) {
          if (e.target.closest('a, button') && !e.target.closest('.slide-plus-btn')) {
            return;
          }
          if (currentIndex !== index) {
            e.preventDefault();
            updateExpandedState(index);
          }
        });
      });

      // 2. Parent-Scoped Navigation Controls
      var targetSelector = container.dataset.navTarget || '.gallery-nav-box';
      var parentSection = container.closest('.elementor-section, .e-con, .e-con-boxed, .elementor-container') || container.parentElement;
      var navBox = parentSection ? parentSection.querySelector(targetSelector) : null;
      var navSource = container.querySelector('.ahm-nav-source');

      if (navSource) {
        if (navBox) {
          var widgetIdClass = Array.from(container.classList).concat(
            container.parentElement ? Array.from(container.parentElement.classList) : []
          ).find(function (c) {
            return c && c.startsWith('elementor-element-');
          });
          if (widgetIdClass && !navBox.classList.contains(widgetIdClass)) {
            navBox.classList.add(widgetIdClass);
          }
          navBox.classList.add('e-widget-swiper', 'ahm-expanding-gallery-nav');
          navBox.innerHTML = '';
          while (navSource.firstChild) {
            navBox.appendChild(navSource.firstChild);
          }
          navSource.remove();
          navBox.style.display = 'inline-flex';
        } else {
          navSource.remove();
          navBox = null;
        }
      } else if (navBox) {
        // Relocate buttons if Elementor Nested Carousel default arrows are present
        var elemPrevBtn = carouselEl.querySelector('.elementor-swiper-button-prev');
        var elemNextBtn = carouselEl.querySelector('.elementor-swiper-button-next');

        if (elemPrevBtn && elemNextBtn && !navBox.contains(elemPrevBtn)) {
          var widgetIdClass = Array.from(carouselEl.classList).find(function (c) {
            return c.startsWith('elementor-element-');
          });
          if (widgetIdClass) {
            navBox.classList.add(widgetIdClass);
          }
          navBox.classList.add('e-widget-swiper', 'elementor-widget-n-carousel');
          navBox.appendChild(elemPrevBtn);
          navBox.appendChild(elemNextBtn);
        }
      }

      if (navBox) {
        var prevBtn = navBox.querySelector('.prev-btn, .elementor-swiper-button-prev');
        var nextBtn = navBox.querySelector('.next-btn, .elementor-swiper-button-next');

        if (prevBtn && !prevBtn.dataset.expandingBound) {
          prevBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var prevIndex = currentIndex > 0 ? currentIndex - 1 : totalSlides - 1;
            updateExpandedState(prevIndex);
          });
          prevBtn.dataset.expandingBound = 'true';
        }

        if (nextBtn && !nextBtn.dataset.expandingBound) {
          nextBtn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            var nextIndex = currentIndex < totalSlides - 1 ? currentIndex + 1 : 0;
            updateExpandedState(nextIndex);
          });
          nextBtn.dataset.expandingBound = 'true';
        }
      }

      container.dataset.expandingInit = 'true';
    });
  }

  // Auto-init on DOMContentLoaded
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
      initExpandingCarousel();
    });
  } else {
    initExpandingCarousel();
  }

  // Backup check on window load
  window.addEventListener('load', function () {
    initExpandingCarousel();
  });

  // Elementor Frontend & Live Editor Hooks
  window.addEventListener('elementor/frontend/init', function () {
    if (window.elementorFrontend && window.elementorFrontend.hooks) {
      window.elementorFrontend.hooks.addAction(
        'frontend/element_ready/ahm_expanding_gallery_carousel.default',
        function ($scope) {
          initExpandingCarousel($scope[0] || $scope);
        }
      );

      window.elementorFrontend.hooks.addAction(
        'frontend/element_ready/nested-carousel.default',
        function ($scope) {
          initExpandingCarousel($scope[0] || $scope);
        }
      );
    }

    // Live Editor: Listen for device mode changes (Desktop <-> Mobile)
    if (window.elementor && window.elementor.channels && window.elementor.channels.deviceMode) {
      window.elementor.channels.deviceMode.on('change', function () {
        setTimeout(function () {
          initExpandingCarousel();
        }, 150);
      });
    }
  });

  // Export for external invocation
  window.ahmInitExpandingCarousel = initExpandingCarousel;
})();
