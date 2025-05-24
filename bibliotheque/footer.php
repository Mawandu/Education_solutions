    </main>

    <footer style="
        width: 100%;
        background-color: var(--secondary);
        color: white;
        padding: 0.8rem 1rem;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-shrink: 0;

    ">
        <div style="font-size: 0.9rem;">Menkao life | Copyright 2025</div>
        <div style="display: flex; gap: 0.8rem; align-items: center;">
            <button class="theme-toggle" id="themeToggle" style="
                background: none;
                border: none;
                color: white;
                cursor: pointer;
                font-size: 1rem;
            ">
                <i class="fas fa-sun"></i>
                <i class="fas fa-moon"></i>
            </button>
            <a href="#" style="color: white; font-size: 1rem;"><i class="fab fa-facebook"></i></a>
            <a href="#" style="color: white; font-size: 1rem;"><i class="fab fa-twitter"></i></a>
            <a href="#" style="color: white; font-size: 1rem;"><i class="fab fa-instagram"></i></a>
        </div>
    </footer>

    <script>
        // Gestion du thème
        const themeToggle = document.getElementById('themeToggle');
        const htmlElement = document.documentElement;
        
        themeToggle.addEventListener('click', () => {
            const currentTheme = htmlElement.getAttribute('data-theme');
            const newTheme = currentTheme === 'light' ? 'dark' : 'light';
            htmlElement.setAttribute('data-theme', newTheme);
            document.cookie = `theme=${newTheme}; path=/; max-age=31536000`;
            updateThemeIcons();
        });

        function updateThemeIcons() {
            const isDark = htmlElement.getAttribute('data-theme') === 'dark';
            document.querySelectorAll('.theme-toggle i').forEach(icon => {
                icon.style.display = 'none';
            });
            document.querySelector(`.theme-toggle i.fa-${isDark ? 'sun' : 'moon'}`).style.display = 'inline';
        }
        
        updateThemeIcons();
    </script>
</body>
</html>
