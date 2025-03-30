document.addEventListener("DOMContentLoaded", function () {
    const leftSide = document.getElementById("left-side");
    const rightSide = document.getElementById("right-side");

    leftSide.addEventListener("mouseenter", () => {
        leftSide.style.flex = "7";
        rightSide.style.flex = "3";
    });

    rightSide.addEventListener("mouseenter", () => {
        rightSide.style.flex = "7";
        leftSide.style.flex = "3";
    });

    document.querySelector(".split-container").addEventListener("mouseleave", () => {
        leftSide.style.flex = "1";
        rightSide.style.flex = "1";
    });
});
