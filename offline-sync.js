// Register Service Worker for Offline Cache
if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => {
        navigator.serviceWorker.register('sw.js')
            .then(reg => console.log('[Service Worker] Registration successful:', reg.scope))
            .catch(err => console.error('[Service Worker] Registration failed:', err));
    });
}

// Dynamic Network Status Banner
function createStatusBanner() {
    let banner = document.getElementById('network-status-banner');
    if (!banner) {
        banner = document.createElement('div');
        banner.id = 'network-status-banner';
        banner.style.position = 'fixed';
        banner.style.top = '0';
        banner.style.left = '0';
        banner.style.width = '100%';
        banner.style.padding = '12px';
        banner.style.textAlign = 'center';
        banner.style.fontWeight = '600';
        banner.style.zIndex = '9999';
        banner.style.boxShadow = '0 2px 10px rgba(0,0,0,0.15)';
        banner.style.transition = 'all 0.3s ease';
        banner.style.display = 'none';
        
        // Adjust body spacing if banner is active
        document.body.appendChild(banner);
    }
    return banner;
}

function updateNetworkStatus() {
    const banner = createStatusBanner();
    if (navigator.onLine) {
        banner.style.backgroundColor = '#27ae60'; // Success green
        banner.style.color = 'white';
        banner.innerHTML = '<i class="fa fa-wifi"></i> Connection Restored! Synchronizing pending applications...';
        
        // Slide out after 3 seconds
        setTimeout(() => {
            banner.style.display = 'none';
        }, 3000);
        
        // Trigger background synchronization
        syncOfflineRecords();
    } else {
        banner.style.backgroundColor = '#e67e22'; // Warning orange
        banner.style.color = 'white';
        banner.innerHTML = '<i class="fa fa-exclamation-triangle"></i> You are currently offline. Running in Offline Mode (data will save locally).';
        banner.style.display = 'block';
    }
}

// Sync Local Offline Records to the database
function syncOfflineRecords() {
    let offlineList = JSON.parse(localStorage.getItem('offline_visa_records') || '[]');
    if (offlineList.length === 0) return;
    
    console.log('[Offline Sync] Syncing ' + offlineList.length + ' records...');
    
    let syncChain = Promise.resolve();
    
    offlineList.forEach(record => {
        syncChain = syncChain.then(() => {
            const formData = new FormData();
            for (const key in record) {
                formData.append(key, record[key]);
            }
            
            return fetch('offline_sync_api.php', {
                method: 'POST',
                body: formData,
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(res => {
                if (res.status === 'success') {
                    console.log('[Offline Sync] Successfully synced record for Passport:', record.passport_number);
                    // Filter out this successfully synced record
                    let currentList = JSON.parse(localStorage.getItem('offline_visa_records') || '[]');
                    currentList = currentList.filter(item => item.passport_number !== record.passport_number);
                    localStorage.setItem('offline_visa_records', JSON.stringify(currentList));
                } else {
                    console.warn('[Offline Sync] Record sync failed: ' + res.message);
                }
            })
            .catch(err => {
                console.error('[Offline Sync] Network request failed during sync:', err);
            });
        });
    });
}

// Event Listeners for Network state
window.addEventListener('online', updateNetworkStatus);
window.addEventListener('offline', updateNetworkStatus);

document.addEventListener('DOMContentLoaded', () => {
    // Initial status check
    if (!navigator.onLine) {
        updateNetworkStatus();
    } else {
        // Trigger sync check on load in case browser came back online
        syncOfflineRecords();
    }

    // Intercept form submissions on new-record page
    const visaForm = document.getElementById('visaForm');
    if (visaForm) {
        visaForm.addEventListener('submit', function(e) {
            if (!navigator.onLine) {
                // Intercept standard submission
                e.preventDefault();
                
                // Trigger form validations
                if (typeof validateForm === 'function' && !validateForm()) {
                    alert('Please fill in all required fields correctly before saving offline.');
                    return;
                }
                
                const formData = new FormData(visaForm);
                const data = {};
                formData.forEach((value, key) => {
                    data[key] = value;
                });
                
                // Add temporary Visa Number if empty
                if (!data['visa_number']) {
                    const tempId = 'TEMP-NIS-' + Math.floor(100000 + Math.random() * 900000);
                    data['visa_number'] = tempId;
                }
                
                // Load and append to local cache list
                let offlineList = JSON.parse(localStorage.getItem('offline_visa_records') || '[]');
                
                // Prevent duplicate offline entries for the same passport
                const alreadyExists = offlineList.some(item => item.passport_number === data.passport_number);
                if (alreadyExists) {
                    alert('An application with this Passport Number is already saved offline and pending synchronization.');
                    return;
                }
                
                offlineList.push(data);
                localStorage.setItem('offline_visa_records', JSON.stringify(offlineList));
                
                alert('Connection Lost! Application has been saved locally on this device. It will automatically sync to the NIS servers as soon as internet connection is restored.');
                
                // Reset form inputs
                visaForm.reset();
                if (typeof toggleSubmitButton === 'function') {
                    toggleSubmitButton();
                }
            }
        });
    }
});
