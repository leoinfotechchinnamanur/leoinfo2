console.log('Theme switcher script loading...');

document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded - Theme switcher initializing');
    
    // Theme switcher functionality
    const themeToggle = document.getElementById('themeToggle');
    const themeOptions = document.querySelectorAll('.theme-option');
    const htmlElement = document.documentElement;
    
    // Check for saved theme preference or default to dark
    let savedTheme = localStorage.getItem('theme');
    console.log('Saved theme:', savedTheme);
    
    if (!savedTheme) {
        savedTheme = 'dark';
        localStorage.setItem('theme', savedTheme);
    }
    
    setTheme(savedTheme);
    updateActiveButton(savedTheme);
    
    // Toggle between dark/light mode
    if (themeToggle) {
        themeToggle.addEventListener('click', function() {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'dark' ? 'light' : 'dark';
            console.log('Toggling theme from', currentTheme, 'to', newTheme);
            setTheme(newTheme);
            localStorage.setItem('theme', newTheme);
            
            // Update icon
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = newTheme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
            
            updateActiveButton(newTheme);
        });
    }
    
    // Theme selection buttons
    themeOptions.forEach(option => {
        option.addEventListener('click', function() {
            const theme = this.getAttribute('data-theme');
            console.log('Setting theme to:', theme);
            setTheme(theme);
            localStorage.setItem('theme', theme);
            updateActiveButton(theme);
        });
    });
    
    // Set theme function
    function setTheme(theme) {
        console.log('Applying theme:', theme);
        htmlElement.setAttribute('data-theme', theme);
        
        // Update theme toggle icon
        if (themeToggle) {
            const icon = themeToggle.querySelector('i');
            if (icon) {
                icon.className = theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon';
            }
        }
    }
    
    // Update active button state
    function updateActiveButton(activeTheme) {
        themeOptions.forEach(opt => {
            opt.classList.remove('active');
            if (opt.getAttribute('data-theme') === activeTheme) {
                opt.classList.add('active');
            }
        });
    }
    
    // Mobile menu toggle
    const menuToggle = document.getElementById('menuToggle');
    const sidebar = document.getElementById('sidebar');
    
    if (menuToggle && sidebar) {
        menuToggle.addEventListener('click', function() {
            sidebar.classList.toggle('active');
        });
        
        // Close sidebar when clicking outside on mobile
        document.addEventListener('click', function(event) {
            const isClickInsideSidebar = sidebar.contains(event.target);
            const isClickOnMenuToggle = menuToggle.contains(event.target);
            
            if (!isClickInsideSidebar && !isClickOnMenuToggle && window.innerWidth < 992) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    // Add animation delays to elements
    const animatedElements = document.querySelectorAll('.animate-slideUp');
    animatedElements.forEach((el, index) => {
        el.style.animationDelay = `${index * 0.1}s`;
    });
    
    console.log('Theme switcher initialized');
});

// Fallback theme setting
(function() {
    const savedTheme = localStorage.getItem('theme') || 'dark';
    document.documentElement.setAttribute('data-theme', savedTheme);
})();
