document.addEventListener('DOMContentLoaded', function() {
  let widgets = document.querySelectorAll('.elementor-widget-stm_lms_pro_testimonials');
  widgets.forEach(function(widget) {
    let widgetData = JSON.parse(widget.getAttribute('data-settings')),
      bullets = widget.querySelector('.ms-lms-elementor-testimonials-swiper-pagination'),
      sliderWrapper = widget.querySelector('.elementor-testimonials-carousel'),
      sliderContainer = widget.querySelector('.stm-testimonials-carousel-wrapper');

    let nextButton = sliderContainer.querySelector(".swiper-button-next");
    let prevButton = sliderContainer.querySelector(".swiper-button-prev");

    if ( sliderContainer.length !== 0 ) {
      const mySwiper = new Swiper(sliderContainer, {
        slidesPerView: widgetData.per_view ? widgetData.per_view : 1,
        allowTouchMove: true,
        loop: widgetData && widgetData.loop ? true : false,
        autoplay: widgetData && widgetData.autoplay ? { delay: 2000 } : false,
        pagination: {
          el: bullets,
          clickable: true,
          renderBullet: function (index, className) {
            let userThumbnail = "",
                slidesArray = Array.from(sliderWrapper.children);

            slidesArray.push(slidesArray.shift());

            let testimonialItem = slidesArray[index];

            if (testimonialItem && typeof testimonialItem === "object") {
                userThumbnail = testimonialItem.getAttribute("data-thumbnail");
            }

            let span = document.createElement("span");
            span.classList.add(className);
            span.style.backgroundImage = "url(" + userThumbnail + ")";
            return span.outerHTML;
          },
        },
        navigation: {
          nextEl: nextButton,
          prevEl: prevButton,
          disabledClass: "swiper-button-disabled",
        },
        breakpoints: {
          320: {
            slidesPerView: widgetData.per_view_mobile ? widgetData.per_view_mobile : 1,
          },
          640: {
            slidesPerView: widgetData.per_view_mobile ? widgetData.per_view_mobile : 1,
          },
          768: {
            slidesPerView: widgetData.per_view_tablet ? widgetData.per_view_tablet : 1,
          },
          1024: {
            slidesPerView: widgetData.per_view ? widgetData.per_view : 1,
          },
        },
      });
    }
  });
});