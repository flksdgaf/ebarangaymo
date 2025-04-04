document.addEventListener("DOMContentLoaded", function () {
    const carousel = document.querySelector("#carouselExampleIndicators");
    const bgContainer = document.querySelector(".carousel-blur-bg");

    function updateBlurredBackground() {
        let activeSlide = document.querySelector(".carousel-item.active img");
        if (activeSlide) {
            bgContainer.style.backgroundImage = `url('${activeSlide.src}')`;
        }
    }

    // Update background when the slide changes
    carousel.addEventListener("slid.bs.carousel", updateBlurredBackground);

    // Set the initial background
    updateBlurredBackground();
});

[...document.querySelectorAll('*')].forEach(el => {
    if (el.scrollWidth > document.documentElement.clientWidth) {
        console.log(el);
    }
});