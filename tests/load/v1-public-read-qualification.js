import http from 'k6/http';
import { check, sleep } from 'k6';

const profile = __ENV.PROFILE || 'smoke';
const baseUrl = (__ENV.BASE_URL || 'http://127.0.0.1:8000').replace(/\/$/, '');
const target = new URL(baseUrl);
const localHosts = ['127.0.0.1', 'localhost', '::1'];

if (!['http:', 'https:'].includes(target.protocol)) {
    throw new Error('BASE_URL must use http or https.');
}

if (!localHosts.includes(target.hostname) && __ENV.ALLOW_NONLOCAL_TARGET !== '1') {
    throw new Error('Non-local targets require ALLOW_NONLOCAL_TARGET=1.');
}

if (profile === 'qualification' && __ENV.CONFIRM_QUALIFICATION !== 'KAIYO_D006_A2') {
    throw new Error('Qualification traffic requires CONFIRM_QUALIFICATION=KAIYO_D006_A2.');
}

if (!['smoke', 'qualification'].includes(profile)) {
    throw new Error('PROFILE must be smoke or qualification.');
}

export const options = profile === 'qualification'
    ? {
        scenarios: {
            approved_public_read_profile: {
                executor: 'ramping-arrival-rate',
                timeUnit: '1s',
                preAllocatedVUs: 100,
                maxVUs: 500,
                stages: [
                    { target: 50, duration: '1m' },
                    { target: 50, duration: '30m' },
                    { target: 200, duration: '5m' },
                    { target: 0, duration: '1m' },
                ],
            },
        },
        thresholds: {
            http_req_failed: ['rate<0.01'],
            http_req_duration: ['p(95)<500', 'p(99)<1000'],
        },
    }
    : {
        scenarios: {
            local_smoke: {
                executor: 'constant-arrival-rate',
                rate: 5,
                timeUnit: '1s',
                duration: '30s',
                preAllocatedVUs: 5,
                maxVUs: 20,
            },
        },
        thresholds: {
            http_req_failed: ['rate<0.01'],
        },
    };

const publicReadPaths = ['/', '/tim-kiem', '/cau-hoi-thuong-gap', '/ready'];

export default function () {
    const path = publicReadPaths[(__VU + __ITER) % publicReadPaths.length];
    const response = http.get(`${baseUrl}${path}`, {
        headers: { 'X-Load-Test-Profile': profile },
        tags: { route_class: path === '/ready' ? 'readiness' : 'public_read' },
    });

    check(response, {
        'response is successful': (result) => result.status >= 200 && result.status < 400,
        'correlation header is present': (result) => Boolean(result.headers['X-Correlation-Id']),
    });
    sleep(0.1);
}
