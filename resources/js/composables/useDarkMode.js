import { ref, onMounted, watch } from 'vue';

export function useDarkMode() {
    const isDark = ref(false);

    // Check for saved user preference or use system preference
    const getTheme = () => {
        if (import.meta.env.SSR) return 'light';
        if ('theme' in localStorage) {
            return localStorage.theme;
        }
        return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    };

    // Apply theme class to document element
    const applyTheme = (theme) => {
        const root = window.document.documentElement;
        
        if (theme === 'dark') {
            root.classList.add('dark');
            root.style.colorScheme = 'dark';
        } else {
            root.classList.remove('dark');
            root.style.colorScheme = 'light';
        }
    };

    // Toggle between light and dark mode
    const toggleDarkMode = () => {
        isDark.value = !isDark.value;
        const newTheme = isDark.value ? 'dark' : 'light';
        localStorage.theme = newTheme;
        applyTheme(newTheme);
    };

    // Initialize
    onMounted(() => {
        const savedTheme = getTheme();
        isDark.value = savedTheme === 'dark';
        applyTheme(savedTheme);

        // Watch for system theme changes
        const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
        const handleChange = (e) => {
            if (!('theme' in localStorage)) {
                isDark.value = e.matches;
                applyTheme(e.matches ? 'dark' : 'light');
            }
        };
        mediaQuery.addEventListener('change', handleChange);

        return () => {
            mediaQuery.removeEventListener('change', handleChange);
        };
    });

    return {
        isDark,
        toggleDarkMode,
    };
}
