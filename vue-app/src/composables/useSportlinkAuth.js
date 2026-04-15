import { ref } from 'vue';
import { FAKE_CREDENTIALS } from '@/config';
import { useToast } from 'vue-toastification';

export function useSportlinkAuth() {
    const sportlinkTokenInfo = ref({
        access_token: null,
        refresh_token: null,
        expires_at: null,
    });

    async function login(username, password, appCreds) {
        try {
            const url        = `https://app-${appCreds.apiUrl}-production.sportlink.com/oauth/token`;
            const proxiedUrl = `https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=${encodeURIComponent(url)}`;
            const toast      = useToast();

            const params = new URLSearchParams();
            params.append('grant_type', 'password');
            params.append('username',   username);
            params.append('password',   password);
            params.append('client_id',  appCreds.client_id);
            params.append('secret',     appCreds.secret);

            const response = await fetch(proxiedUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'okhttp/4.12.0' },
                body:    params,
            });

            if (response.status === 401) {
                toast.error(`Inloggen bij Sportlink is mislukt met gebruikersnaam: ${username}`, { timeout: 5000 });
            }
            if (!response.ok) throw new Error(`HTTP error ${response.status}`);

            const { access_token, refresh_token, expires_in } = await response.json();
            sportlinkTokenInfo.value = {
                access_token,
                refresh_token,
                expires_at: Date.now() + expires_in * 1000
            };

            toast.success('Sportlink Proxy met success ingelogd', { timeout: 5000 });
            localStorage.setItem('sportlinkTokenInfo', JSON.stringify(sportlinkTokenInfo.value));
            return true;
        } catch (error) {
            console.error('Login failed', error);
            return false;
        }
    }

    async function refreshToken(appCreds) {
        try {
            if (!sportlinkTokenInfo.value.refresh_token) throw new Error('No refresh token available');

            const url        = `https://app-${appCreds.apiUrl}-production.sportlink.com/oauth/token`;
            const proxiedUrl = `https://cors-proxy.clubinfoproxy.workers.dev/proxy?url=${encodeURIComponent(url)}`;

            const params = new URLSearchParams();
            params.append('grant_type',    'refresh_token');
            params.append('refresh_token', sportlinkTokenInfo.value.refresh_token);
            params.append('client_id',     appCreds.client_id);
            params.append('secret',        appCreds.secret);

            const response = await fetch(proxiedUrl, {
                method:  'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'User-Agent': 'okhttp/4.12.0' },
                body:    params,
            });
            if (!response.ok) throw new Error(`Token refresh failed: ${response.status}`);

            const { access_token, refresh_token, expires_in } = await response.json();
            sportlinkTokenInfo.value = {
                access_token,
                refresh_token,
                expires_at: Date.now() + expires_in * 1000
            };
            localStorage.setItem('sportlinkTokenInfo', JSON.stringify(sportlinkTokenInfo.value));
            return true;
        } catch (error) {
            console.error('Refresh failed', error);
            return false;
        }
    }

    function useFakeCredentials(appCreds) {
        const fakeCred = FAKE_CREDENTIALS.find(credential =>
            credential.sports.some(s => s.sport.toLowerCase() === appCreds.type.toLowerCase())
        );
        if (fakeCred) return login(fakeCred.username, fakeCred.password, appCreds);
        return Promise.resolve(false);
    }

    function loadSavedToken() {
        const saved = localStorage.getItem('sportlinkTokenInfo');
        if (saved) {
            const parsed = JSON.parse(saved);
            if (parsed.expires_at > Date.now()) {
                sportlinkTokenInfo.value = parsed;
                return true;
            }
            localStorage.removeItem('sportlinkTokenInfo');
        }
        return false;
    }

    return { sportlinkTokenInfo, login, refreshToken, useFakeCredentials, loadSavedToken };
}
