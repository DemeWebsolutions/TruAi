// Quick fix: Force show popup
(function() {
    console.log('Quick fix script running...');
    setTimeout(function() {
        const app = document.getElementById('app');
        if (app && app.innerHTML.includes('Loading Tru.ai...')) {
            console.log('App still loading, forcing popup...');
            if (typeof showLegalNoticePopup === 'function') {
                showLegalNoticePopup();
            } else {
                console.error('showLegalNoticePopup function not found!');
            }
        }
    }, 500);
})();
