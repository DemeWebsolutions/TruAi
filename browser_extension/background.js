/**
 * DemeWebsolutions Biometric Auth — Background Service Worker
 * Relays credential requests from the content script to the native host.
 */

const NATIVE_HOST = 'com.demewebsolutions.biometric';

chrome.runtime.onMessage.addListener((request, sender, sendResponse) => {
    if (request.action === 'getCredentials') {
        getCredentialsFromNativeHost(request.app)
            .then(credentials => sendResponse({ success: true, credentials }))
            .catch(err => {
                console.error('[DemeWebsolutions] Native host error:', err);
                sendResponse({ success: false, error: err.message });
            });
        return true; // async response
    }
});

async function getCredentialsFromNativeHost(app) {
    return new Promise((resolve, reject) => {
        const port = chrome.runtime.connectNative(NATIVE_HOST);

        const timer = setTimeout(() => {
            port.disconnect();
            reject(new Error('Native host timed out'));
        }, 10000);

        port.onMessage.addListener(response => {
            clearTimeout(timer);
            if (response.success) {
                resolve(response.credentials);
            } else {
                reject(new Error(response.error || 'Unknown error from native host'));
            }
        });

        port.onDisconnect.addListener(() => {
            clearTimeout(timer);
            const err = chrome.runtime.lastError;
            reject(new Error(err ? err.message : 'Native host disconnected'));
        });

        port.postMessage({ action: 'getCredentials', app });
    });
}
