// script.js

// --- REPLACE WITH YOUR ACTUAL VAPID PUBLIC KEY ---
const VAPID_PUBLIC_KEY = 'YOUR_VAPID_PUBLIC_KEY'; 
// --- This key is generated on your server. ---

const subscribeButton = document.getElementById('subscribeButton');
const statusArea = document.getElementById('statusArea');

if (VAPID_PUBLIC_KEY === 'YOUR_VAPID_PUBLIC_KEY') {
    console.error('Error: VAPID_PUBLIC_KEY is not set in script.js. Please update it with your actual key.');
    statusArea.textContent = 'Client-side VAPID public key not set. Subscription disabled.';
    if(subscribeButton) subscribeButton.disabled = true;
}

// Utility function to convert VAPID public key
function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

// Register Service Worker
async function registerServiceWorker() {
    if (!('serviceWorker' in navigator)) {
        statusArea.textContent = 'Service Worker not supported by this browser.';
        console.error('Service Worker not supported.');
        return null;
    }

    try {
        const registration = await navigator.serviceWorker.register('sw.js');
        console.log('Service Worker registered with scope:', registration.scope);
        statusArea.textContent = 'Service Worker registered.';
        return registration;
    } catch (error) {
        console.error('Service Worker registration failed:', error);
        statusArea.textContent = 'Service Worker registration failed: ' + error.message;
        return null;
    }
}

// Subscribe user to push notifications
async function subscribeUser(serviceWorkerRegistration) {
    if (!serviceWorkerRegistration) {
        statusArea.textContent = 'Service Worker registration not available. Cannot subscribe.';
        console.error('No service worker registration to subscribe.');
        return;
    }
    if (!('PushManager' in window)) {
        statusArea.textContent = 'Push Messaging not supported by this browser.';
        console.error('Push Messaging not supported.');
        return;
    }

    try {
        // Check current permission status
        let permission = Notification.permission;
        if (permission === 'default') {
            statusArea.textContent = 'Requesting notification permission...';
            permission = await Notification.requestPermission();
        }
        
        if (permission === 'denied') {
            statusArea.textContent = 'Notification permission was denied.';
            console.warn('Notification permission denied.');
            return;
        }
        
        if (permission === 'granted') {
            statusArea.textContent = 'Notification permission granted. Subscribing...';
            console.log('Notification permission granted.');

            const applicationServerKey = urlBase64ToUint8Array(VAPID_PUBLIC_KEY);
            const subscription = await serviceWorkerRegistration.pushManager.subscribe({
                userVisibleOnly: true,
                applicationServerKey: applicationServerKey
            });
            
            console.log('User is subscribed:', subscription);
            statusArea.textContent = 'User subscribed. Sending to backend...';
            await sendSubscriptionToBackend(subscription);
        }
    } catch (error) {
        console.error('Failed to subscribe the user: ', error);
        statusArea.textContent = 'Failed to subscribe: ' + error.message;
    }
}

// Send subscription to backend
async function sendSubscriptionToBackend(subscription) {
    try {
        const response = await fetch('subscribe.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify(subscription),
        });

        const responseData = await response.json();

        if (response.ok && responseData.success) {
            console.log('Subscription sent to backend successfully:', responseData);
            statusArea.textContent = 'Subscription successful! ' + (responseData.message || '');
        } else {
            console.error('Failed to send subscription to backend:', responseData);
            statusArea.textContent = 'Failed to save subscription on server: ' + (responseData.message || 'Unknown error');
        }
    } catch (error) {
        console.error('Error sending subscription to backend:', error);
        statusArea.textContent = 'Error sending subscription to server: ' + error.message;
    }
}

// Main logic
if (subscribeButton) {
    subscribeButton.addEventListener('click', async () => {
        if (VAPID_PUBLIC_KEY === 'YOUR_VAPID_PUBLIC_KEY') {
            statusArea.textContent = 'Cannot subscribe: VAPID Public Key is not configured in script.js.';
            return;
        }
        subscribeButton.disabled = true;
        statusArea.textContent = 'Processing...';
        const registration = await navigator.serviceWorker.ready; // Ensures SW is active
        await subscribeUser(registration);
        subscribeButton.disabled = false;
    });
} else {
    console.warn('Subscribe button not found.');
}

// Initialize service worker registration on page load
registerServiceWorker();
