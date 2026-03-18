/**
 * Chaos MVC - Minimalist Theme Engine
 * No-Magic, Vanilla JS
 */

document.addEventListener('DOMContentLoaded', () => {
    
    // 1. Mobile Menu Toggle (id="menu-btn" controls id="nav-menu")
    const menuBtn = document.getElementById('menu-btn');
    const navMenu = document.getElementById('nav-menu');

    if (menuBtn && navMenu) {
        menuBtn.addEventListener('click', () => {
            navMenu.classList.toggle('active');
        });
    }

    // 2. Alert Dismissal (Auto-hide or click-to-close)
    const alerts = document.querySelectorAll('.alert-dismiss');
    alerts.forEach(alert => {
        alert.addEventListener('click', () => {
            alert.style.display = 'none';
        });
        
        // Optional: Auto-fade after 5 seconds
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 600);
        }, 5000);
    });

    // 3. Prevent Double Submits on Certification Forms
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('button[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerText = 'Processing...';
            }
        });
    });

});
