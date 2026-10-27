document.addEventListener('DOMContentLoaded', () => {
    const pwdInput = document.querySelector('input[name="new_password"]');
    const indicator = document.getElementById('pwd-strength');
    
    if (pwdInput && indicator) {
        pwdInput.addEventListener('input', () => {
            const val = pwdInput.value;
            let strength = 0;
            
            if (val.length >= 6) strength++;
            if (val.length >= 10) strength++;
            if (/[A-Z]/.test(val)) strength++;
            if (/[0-9]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            if (val.length === 0) {
                indicator.style.display = 'none';
                return;
            }

            indicator.style.display = 'block';
            
            if (strength <= 2) {
                indicator.style.width = '33%';
                indicator.style.background = '#ef4444'; // Красный
            } else if (strength <= 4) {
                indicator.style.width = '66%';
                indicator.style.background = '#f59e0b'; // Желтый
            } else {
                indicator.style.width = '100%';
                indicator.style.background = '#10b981'; // Зеленый
            }
        });
    }
});