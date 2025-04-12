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

window.addEventListener('resize', adjustSidebarState);
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

// Dropdown functionality for the username button
document.querySelector('.username-btn').addEventListener('click', function() {
  var dropdownMenu = this.nextElementSibling; // The dropdown menu
  var dropdownArrow = this.querySelector('.arrow');

  // Toggle the display of the dropdown menu
  dropdownMenu.style.display = (dropdownMenu.style.display === 'block') ? 'none' : 'block';

  // Toggle the 'open' class to rotate the arrow
  this.classList.toggle('open');
});

// Close the dropdown if the user clicks outside of it
window.addEventListener('click', function(event) {
  if (!event.target.closest('.username-btn')) {
      var openDropdowns = document.querySelectorAll('.username-btn.open');
      openDropdowns.forEach(function(button) {
          button.querySelector('.dropdown-menu').style.display = 'none';
          button.classList.remove('open');
      });
  }

  if (!event.target.matches('.action-btn') && !event.target.closest('.dropdown')) {
    document.querySelectorAll('.dropdown-menu').forEach(menu => menu.style.display = 'none');
    document.querySelectorAll('.action-btn').forEach(btn => btn.classList.remove('open'));
  }
});

function toggleDropdown(button) {
  const dropdown = button.nextElementSibling;
  const isOpen = button.classList.contains('open');

  // Close all dropdowns
  document.querySelectorAll('.dropdown-menu').forEach(menu => menu.style.display = 'none');
  document.querySelectorAll('.action-btn').forEach(btn => btn.classList.remove('open'));

  if (!isOpen) {
    dropdown.style.display = 'block';
    button.classList.add('open');
  }
}

