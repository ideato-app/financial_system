// Track user activity and handle session closure
document.addEventListener('DOMContentLoaded', function() {
    console.log('Session tracker initialized');
    
      // Start countdown timer immediately
        timeoutStartTime = new Date();
        showTimerDisplay();
        
        // Set up countdown timer to update every second
        if (!countdownTimer) {
            countdownTimer = setInterval(updateTimerDisplay, 1000);
        }
    }

    // Helper function for API calls with fallback
    async function apiCall(endpoint, fallbackEndpoint) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
        const headers = {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'  // This helps Laravel identify AJAX requests
        };
        
        if (csrfToken) {
            headers['X-CSRF-TOKEN'] = csrfToken;
        }
        
        try {
            console.log(`Attempting API call to ${endpoint}`);
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: headers,
                credentials: 'same-origin'  // Include cookies in the request
            });
            
            if (response.ok) {
                console.log(`API call to ${endpoint} succeeded`);
                return response;
            }
            
            console.warn(`API call to ${endpoint} failed with status: ${response.status}`);
            
            // If primary endpoint fails and we have a fallback, try that
            if (fallbackEndpoint) {
                console.log(`Attempting fallback API call to ${fallbackEndpoint}`);
                const fallbackResponse = await fetch(fallbackEndpoint, {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin'
                });
                
                if (fallbackResponse.ok) {
                    console.log(`Fallback API call to ${fallbackEndpoint} succeeded`);
                    return fallbackResponse;
                }
                
                console.error(`Fallback API call to ${fallbackEndpoint} also failed with status: ${fallbackResponse.status}`);
            }
            
            return response; // Return the original response even if it failed
        } catch (error) {
            console.error(`API call error for ${endpoint}:`, error);
            throw error;
        }
    }NACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutes in milliseconds
    const WARNING_THRESHOLD = 60 * 1000; // Show timer when 1 minute remaining
    
    let inactivityTimer;
    let countdownTimer;
    let lastActivity = new Date();
    let timeoutStartTime;
    let timerDisplayElement = null;
    
    // Check if CSRF token exists
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (!csrfToken) {
        console.warn('CSRF token not found. Session tracking may not work correctly.');
    } else {
        console.log('CSRF token found');
    }
    
    // Create session timer element if it doesn't exist
    function createTimerElement() {
        if (!timerDisplayElement) {
            timerDisplayElement = document.createElement('div');
            timerDisplayElement.id = 'session-timer';
            timerDisplayElement.style.cssText = `
                position: fixed;
                top: 10px;
                right: 10px;
                background-color: #e2f0ff;
                color: #0056b3;
                border: 1px solid #b8daff;
                border-radius: 4px;
                padding: 10px 15px;
                font-weight: bold;
                z-index: 9999;
                cursor: pointer;
                box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            `;
            
            // Add click handler to extend session
            timerDisplayElement.addEventListener('click', function() {
                resetInactivityTimer();
                updateTimerDisplay(); // Just update the display without hiding
            });
            
            // Add info text
            const infoText = document.createElement('div');
            infoText.textContent = 'Click to extend session';
            infoText.style.cssText = 'font-size: 12px; font-weight: normal;';
            timerDisplayElement.appendChild(infoText);
            
            document.body.appendChild(timerDisplayElement);
        }
        return timerDisplayElement;
    }
    
    // Show timer display with countdown
    function showTimerDisplay() {
        const timerElement = createTimerElement();
        updateTimerDisplay();
    }
    
    // Hide timer display - now only used during page unload
    function hideTimerDisplay() {
        if (timerDisplayElement) {
            timerDisplayElement.style.display = 'none';
        }
        if (countdownTimer) {
            clearInterval(countdownTimer);
            countdownTimer = null;
        }
    }
    
    // Update the timer display with remaining time
    function updateTimerDisplay() {
        if (!timeoutStartTime) return;
        
        const now = new Date();
        const elapsedTime = now - timeoutStartTime;
        const remainingTime = INACTIVITY_TIMEOUT - elapsedTime;
        
        if (remainingTime <= 0) {
            // Don't hide, just show 0:00
            if (timerDisplayElement) {
                timerDisplayElement.innerHTML = `Session expiring in: 0:00`;
                
                // Add info text
                const infoText = document.createElement('div');
                infoText.textContent = 'Click to extend session';
                infoText.style.cssText = 'font-size: 12px; font-weight: normal;';
                timerDisplayElement.appendChild(infoText);
                
                timerDisplayElement.style.backgroundColor = '#dc3545';
                timerDisplayElement.style.color = '#fff';
            }
            return;
        }
        
        const minutes = Math.floor(remainingTime / 60000);
        const seconds = Math.floor((remainingTime % 60000) / 1000);
        
        if (timerDisplayElement) {
            timerDisplayElement.innerHTML = `Session expiring in: ${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            
            // Add info text
            const infoText = document.createElement('div');
            infoText.textContent = 'Click to extend session';
            infoText.style.cssText = 'font-size: 12px; font-weight: normal;';
            timerDisplayElement.appendChild(infoText);
            
            // Change color based on remaining time
            if (remainingTime < 60000) { // Less than 1 minute
                timerDisplayElement.style.backgroundColor = '#f8d7da';
                timerDisplayElement.style.color = '#721c24';
                
                if (remainingTime < 30000) { // Less than 30 seconds
                    timerDisplayElement.style.backgroundColor = '#dc3545';
                    timerDisplayElement.style.color = '#fff';
                }
            } else {
                timerDisplayElement.style.backgroundColor = '#e2f0ff';
                timerDisplayElement.style.color = '#0056b3';
            }
        }
    }
    
    // Function to call when the user is leaving the page
    async function handleUserExit() {
        try {
            console.log('Attempting to log user out');
            const response = await fetch('/api/auto-logout', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                    'X-Requested-With': 'XMLHttpRequest'  // This helps Laravel identify AJAX requests
                },
                credentials: 'same-origin'  // Include cookies in the request
            });
            
            if (!response.ok) {
                console.error('Auto-logout failed with status:', response.status);
                
                // Try the web route as fallback
                if (response.status === 404 || response.status === 401) {
                    try {
                        console.log('Trying fallback logout route');
                        const fallbackResponse = await fetch('/auto-logout', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content'),
                                'X-Requested-With': 'XMLHttpRequest'
                            },
                            credentials: 'same-origin'
                        });
                        
                        if (!fallbackResponse.ok) {
                            console.error('Fallback logout also failed:', fallbackResponse.status);
                            window.location.href = '/logout';
                        }
                    } catch (fallbackError) {
                        console.error('Error with fallback logout:', fallbackError);
                        window.location.href = '/logout';
                    }
                }
            } else {
                console.log('Auto-logout successful');
            }
        } catch (error) {
            console.error('Error during auto-logout:', error);
            // If we can't reach the server at all, redirect to logout page as fallback
            window.location.href = '/logout';
        }
    }

    // Function to handle automatic logout after inactivity
    function setupInactivityTimer() {
        clearTimeout(inactivityTimer);
        
        inactivityTimer = setTimeout(function() {
            console.log('User inactive for 5 minutes, logging out...');
            handleUserExit().then(() => {
                // Redirect to login page after logout
                window.location.href = '/login';
            });
        }, INACTIVITY_TIMEOUT);
        
        // Start the countdown timer immediately
        timeoutStartTime = new Date();
        showTimerDisplay();
        
        // Set up countdown timer to update every second
        if (!countdownTimer) {
            countdownTimer = setInterval(updateTimerDisplay, 1000);
        }
    }

    // Reset timer on any user activity
    function resetInactivityTimer() {
        lastActivity = new Date();
        // Don't hide the timer, just reset it
        setupInactivityTimer();
    }

    // Initialize the inactivity timer
    setupInactivityTimer();

    // List of events that indicate user activity
    const activityEvents = [
        'mousedown', 'mousemove', 'keydown',
        'scroll', 'touchstart', 'click', 'keypress'
    ];

    // Add event listeners for user activity
    activityEvents.forEach(function(eventName) {
        document.addEventListener(eventName, resetInactivityTimer, true);
    });

    // Handle page visibility change (when user switches tabs or minimizes browser)
    let hiddenTime = null;
    document.addEventListener('visibilitychange', function() {
        if (document.visibilityState === 'hidden') {
            // User has switched away from the page
            hiddenTime = new Date();
            // Clear the inactivity timer while page is hidden
            clearTimeout(inactivityTimer);
        } else if (document.visibilityState === 'visible' && hiddenTime) {
            // User has returned to the page
            const awayTime = (new Date() - hiddenTime) / 1000; // time away in seconds
            if (awayTime > 300) { // 5 minutes
                // If away for more than 5 minutes, log out
                handleUserExit().then(() => {
                    window.location.href = '/login';
                });
            } else {
                // Reset the inactivity timer and show it
                resetInactivityTimer();
            }
            hiddenTime = null;
        }
    });

    // Handle before unload event (when user closes tab or browser)
    window.addEventListener('beforeunload', function(e) {
        handleUserExit();
        // Modern browsers require returning undefined to avoid showing a confirmation dialog
        // when using the beforeunload event
        return undefined;
    });

    // Send a heartbeat every minute to update the last activity timestamp
    setInterval(async function() {
        // Only send heartbeat if user has been active in the last 5 minutes
        const timeSinceLastActivity = new Date() - lastActivity;
        if (timeSinceLastActivity < INACTIVITY_TIMEOUT) {
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            const headers = {
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            
            if (csrfToken) {
                headers['X-CSRF-TOKEN'] = csrfToken;
            }
            
            try {
                console.log('Sending heartbeat to API');
                const response = await fetch('/api/session-heartbeat', {
                    method: 'POST',
                    headers: headers,
                    credentials: 'same-origin'
                });
                
                if (!response.ok) {
                    console.warn(`Heartbeat failed with status: ${response.status}, trying fallback`);
                    
                    // Try the web route as fallback
                    try {
                        console.log('Trying fallback heartbeat route');
                        const fallbackResponse = await fetch('/session-heartbeat', {
                            method: 'POST',
                            headers: headers,
                            credentials: 'same-origin'
                        });
                        
                        if (!fallbackResponse.ok) {
                            console.error('Fallback heartbeat also failed:', fallbackResponse.status);
                        } else {
                            console.log('Fallback heartbeat succeeded');
                        }
                    } catch (fallbackError) {
                        console.error('Error with fallback heartbeat:', fallbackError);
                    }
                } else {
                    console.log('Heartbeat successful');
                }
            } catch (error) {
                console.error('Heartbeat error:', error);
            }
        }
    }, 60 * 1000); // 1 minute
});
