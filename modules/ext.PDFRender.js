$(document).ready(function () {
    const carousels = document.querySelectorAll(".pdf-carousel-container");
    pdfjsLib = window['pdfjsLib']
    pdfjsLib.GlobalWorkerOptions.workerSrc = 'https://mozilla.github.io/pdf.js/build/pdf.worker.mjs';

    carousels.forEach((carouselContainer, index) => {
        const carousel = carouselContainer.querySelector(".pdf-carousel");
        const pdfUrl = carousel.getAttribute("data-pdf-url");

        // Generate unique identifiers for each Swiper instance
        const paginationId = `swiper-pagination-${index}`;
        const nextButtonId = `swiper-button-next-${index}`;
        const prevButtonId = `swiper-button-prev-${index}`;

        // Set the unique IDs to the respective elements
        carousel.querySelector('.swiper-pagination').id = paginationId;
        carousel.querySelector('.swiper-button-next').id = nextButtonId;
        carousel.querySelector('.swiper-button-prev').id = prevButtonId;

        const loadingTask = pdfjsLib.getDocument(pdfUrl);
        loadingTask.promise.then((pdf) => {
            let pagePromises = [];

            for (let pageNumber = 1; pageNumber <= pdf.numPages; pageNumber++) {
                let pagePromise = pdf.getPage(pageNumber).then((page) => {
                    const viewport = page.getViewport({ scale: 1.5 });
                    const canvas = document.createElement("canvas");
                    const context = canvas.getContext("2d");
                    canvas.height = viewport.height;
                    canvas.width = viewport.width;

                    const renderContext = {
                        canvasContext: context,
                        viewport: viewport,
                    };

                    return page.render(renderContext).promise.then(() => {
                        const slide = document.createElement("div");
                        slide.classList.add("swiper-slide");
                        slide.appendChild(canvas);
                        return slide;
                    });
                });

                pagePromises.push(pagePromise);
            }

            // Wait for all page rendering promises to resolve
            Promise.all(pagePromises).then((slides) => {
                const swiperWrapper = carousel.querySelector(".swiper-wrapper");

                // Append slides in order
                slides.forEach(slide => {
                    swiperWrapper.appendChild(slide);
                });

                // Initialize Swiper after all slides have been added
                new Swiper(carousel, {
                    pagination: {
                        el: `#${paginationId}`,
                        clickable: true,
                    },
                    navigation: {
                        nextEl: `#${nextButtonId}`,
                        prevEl: `#${prevButtonId}`,
                    },
                });
                carouselContainer.classList.add("loaded");
            });
        });
    });
});
