const hamBurger = document.querySelector(".toggle-btn");
const closeBtn = document.querySelector('#close-btn');
const sidebar = document.querySelector("#sidebar");

hamBurger.addEventListener("click", () => {
  sidebar.classList.toggle("expand");
});

closeBtn.addEventListener("click", () => {
  sidebar.classList.remove("expand");
});

document.addEventListener("DOMContentLoaded", function () {
    const dropdownToggle = document.querySelector(".dropdown-toggle");
    const dropdownMenu = document.querySelector(".dropdown-menu");
    const arrow = dropdownToggle.querySelector(".arrow");

    dropdownToggle.addEventListener("click", () => {
        const isOpen = dropdownMenu.style.display === "flex";
        dropdownMenu.style.display = isOpen ? "none" : "flex";
        arrow.style.transform = isOpen ? "rotate(0deg)" : "rotate(180deg)";
    });
});
