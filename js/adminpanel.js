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

document.querySelectorAll('.sidebar a').forEach(link => {
    link.addEventListener('click', function() {
      document.querySelectorAll('.sidebar a').forEach(l => l.classList.remove('active'));
      this.classList.add('active');
    });
  });