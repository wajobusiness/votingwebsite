// Function to check the registration status using AJAX
function checkRegistrationStatus() {
    var xhr = new XMLHttpRequest();
    xhr.open('GET', 'check_registration_status.php', true);
    xhr.onreadystatechange = function() {
        if (xhr.readyState === 4 && xhr.status === 200) {
            try {
                var data = JSON.parse(xhr.responseText);
                if (!data.open) {
                    alert('Registration has ended. You will be redirected to the homepage.');
                    window.location.href = 'index.php';
                }
            } catch (e) {
                console.error('Error parsing response:', e);
            }
        }
    };
    xhr.send();
}

// Call checkRegistrationStatus when the page loads
document.addEventListener('DOMContentLoaded', function() {
    checkRegistrationStatus();
});

// Optionally, periodically check the registration status
setInterval(checkRegistrationStatus, 60000); // Check every minute
