const hamBurger = document.querySelector("#hamburger-btn");
const closeBtn = document.querySelector('#close-btn');
const sidebar = document.querySelector("#sidebar");

// Function to manage sidebar and hamburger visibility based on screen width
function adjustSidebarState() {
  if (window.innerWidth >= 768) {
    sidebar.classList.remove("expand"); // Ensure sidebar is expanded in desktop view
    hamBurger.style.display = "none";  // Hide hamburger in desktop view
  } else {
    hamBurger.style.display = "block";  // Show hamburger in mobile view
  }
}

// When clicking the hamburger menu
hamBurger.addEventListener("click", () => {
  sidebar.classList.add("expand");
  hamBurger.style.display = "none"; // Hide hamburger when sidebar is expanded
});

// When clicking the close button on the sidebar
closeBtn.addEventListener("click", () => {
  sidebar.classList.remove("expand");
  hamBurger.style.display = "block"; // Show hamburger again when sidebar is collapsed
});

// Event listener to handle window resizing
window.addEventListener('resize', adjustSidebarState);

// Initial state setup for page load
document.addEventListener("DOMContentLoaded", adjustSidebarState);

// Dropdown functionality (for request items)
document.addEventListener("DOMContentLoaded", function () {
  const dropdownToggles = document.querySelectorAll(".dropdown-toggle");

  dropdownToggles.forEach(toggle => {
    const dropdownMenu = toggle.nextElementSibling;
    const arrow = toggle.querySelector(".arrow");

    toggle.addEventListener("click", () => {
      const isOpen = dropdownMenu.style.display === "flex";
      dropdownMenu.style.display = isOpen ? "none" : "flex";
      arrow.style.transform = isOpen ? "rotate(0deg)" : "rotate(180deg)";

      // Load the content when the dropdown button is clicked (for the main request page)
      const page = toggle.getAttribute("data-page");
      if (page) {
        loadPage(page);
      }
    });
  });
});

// Function to load pages dynamically
function loadPage(page) {
  fetch(`pages/${page}`)
    .then(response => response.text())
    .then(data => {
      document.getElementById("content-wrapper").innerHTML = data;
      history.pushState(null, '', `?page=${page}`);
    })
    .catch(err => {
      document.getElementById("content-wrapper").innerHTML = "<p>Error loading page.</p>";
    });
}

// Handle clicks on nav links (including the dropdown items)
document.querySelectorAll(".nav-link").forEach(link => {
  link.addEventListener("click", function(e) {
    e.preventDefault();
    const page = this.getAttribute("data-page");

    if (page) {
      loadPage(page); // Load the page based on the data-page attribute
    }
  });
});
